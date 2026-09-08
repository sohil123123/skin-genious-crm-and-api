<?php

declare(strict_types=1);

namespace App\Services\Call;

use App\DTOs\Call\NormalizedCall;
use App\Enums\Call\CallMatchingMethod;
use App\Enums\Call\CallMatchingStatus;
use App\Models\Call;
use App\Models\CallProviderAgent;
use App\Models\Clinic;
use App\Models\Lead;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Works out who was on a call: which patient or lead, and which staff member.
 *
 * The governing rule is that a wrong match is worse than no match. Attaching a
 * conversation to the wrong patient writes someone else's medical discussion
 * into their file, and nothing downstream would ever flag it — the call would
 * simply appear in their history looking entirely legitimate. So when the
 * evidence is ambiguous this class records the ambiguity and stops, rather than
 * picking the most likely candidate.
 *
 * It also never creates a patient or a lead. An unknown caller is a fact the
 * clinic should see and act on, not a reason to manufacture a duplicate record
 * that then has to be merged by hand.
 */
class CallParticipantResolver
{
    public function __construct(
        protected PhoneNumberNormalizer $phone,
    ) {}

    /**
     * Attach customer, agent and clinic to a call.
     *
     * Mutates the model without saving; the caller decides when to persist, so
     * one save covers the whole ingestion.
     */
    public function resolve(Call $call, ?NormalizedCall $normalized = null): void
    {
        $this->resolveCustomer($call, $normalized);
        $this->resolveAgent($call, $normalized);
        $this->resolveClinic($call);
    }

    // ──────────────── Customer ────────────────

    /**
     * Identify the patient or lead on the other end of the call.
     */
    public function resolveCustomer(Call $call, ?NormalizedCall $normalized = null): void
    {
        // A human has already decided. Nothing automatic may override that,
        // including a later provider event or a full re-sync — which is the
        // whole reason ManuallyMatched is a distinct status rather than a flag.
        if ($call->matching_status?->isHumanDecided()) {
            return;
        }

        if (! (bool) config('calls.matching.auto_attach', true)) {
            return;
        }

        // ─── The provider already told us who this is ───────────────────
        // Callyzer carries a lead_id from its own CRM. When it maps to a lead
        // here, it beats a phone lookup: it is an explicit statement rather
        // than an inference from a number.
        if (filled($normalized?->providerLeadId)) {
            $lead = Lead::query()
                ->where('fb_lead_id', $normalized->providerLeadId)
                ->first();

            if ($lead !== null) {
                $this->attachLead($call, $lead, CallMatchingMethod::ProviderLeadId);

                return;
            }
        }

        $key = $call->client_phone_key;

        if (! $this->phone->isMatchable($key)) {
            // A short code or an anonymised caller. Not a failure — there is
            // genuinely nobody to attach.
            $call->matching_status = CallMatchingStatus::Unmatched;
            $call->matching_method = CallMatchingMethod::Unknown;

            return;
        }

        $patients = $this->findPatients($key);

        if ($patients->count() === 1) {
            $this->attachPatient($call, $patients->first(), CallMatchingMethod::Phone);

            return;
        }

        if ($patients->count() > 1) {
            $this->markAmbiguous($call, $patients->map(fn (User $user): array => [
                'type' => 'patient',
                'id' => $user->getKey(),
                'name' => $user->name,
                'mobile' => $user->mobile,
            ])->all());

            return;
        }

        $leads = $this->findLeads($key);

        if ($leads->count() === 1) {
            $this->attachLead($call, $leads->first(), CallMatchingMethod::Phone);

            return;
        }

        if ($leads->count() > 1) {
            $this->markAmbiguous($call, $leads->map(fn (Lead $lead): array => [
                'type' => 'lead',
                'id' => $lead->getKey(),
                'name' => $lead->display_name,
                'phone' => $lead->phone,
            ])->all());

            return;
        }

        $call->customer_user_id = null;
        $call->lead_id = null;
        $call->matching_status = CallMatchingStatus::Unmatched;
        $call->matching_method = CallMatchingMethod::Unknown;
        $call->match_candidates = null;
        $call->matched_at = null;
    }

    /**
     * Patients whose mobile ends in the same subscriber digits.
     *
     * Compared with a suffix LIKE rather than an equality test because
     * users.mobile predates any normalisation in this application: the same
     * person exists as "9876543210", "+919876543210" and "0 9876543210"
     * depending on who typed them in and when. Matching only on an exact string
     * would leave years of patients permanently unreachable by their own calls.
     *
     * The global active-status scope is deliberately left in place, so a
     * deactivated record does not start collecting calls.
     *
     * @return Collection<int, User>
     */
    protected function findPatients(string $key): Collection
    {
        return User::query()
            ->where(fn (Builder $query): Builder => $query
                ->where('mobile', $key)
                ->orWhere('mobile', 'LIKE', '%' . $key))
            // Staff numbers must not be matched as patients: an agent calling
            // from a personal phone would otherwise attach the call to
            // themselves as the customer.
            ->whereDoesntHave('roles', fn (Builder $roles): Builder => $roles
                ->whereIn('name', array_values(array_diff(
                    (array) config('project.roles'),
                    [config('project.roles.client')],
                ))))
            ->limit(5)
            ->get();
    }

    /**
     * Leads whose phone ends in the same subscriber digits.
     *
     * @return Collection<int, Lead>
     */
    protected function findLeads(string $key): Collection
    {
        return Lead::query()
            ->where(fn (Builder $query): Builder => $query
                ->where('phone', 'LIKE', '%' . $key)
                ->orWhere('phone_raw', 'LIKE', '%' . $key))
            ->limit(5)
            ->get();
    }

    protected function attachPatient(Call $call, User $user, CallMatchingMethod $method): void
    {
        $call->customer_user_id = $user->getKey();

        // A lead that already became this patient stays attached — the call
        // belongs to both, and dropping the lead link would break the lead's
        // own timeline.
        if ($call->lead_id === null) {
            $call->lead_id = Lead::query()
                ->where('matched_user_id', $user->getKey())
                ->value('id');
        }

        $call->matching_status = CallMatchingStatus::Matched;
        $call->matching_method = $method;
        $call->matched_at = now();
        $call->match_candidates = null;
    }

    protected function attachLead(Call $call, Lead $lead, CallMatchingMethod $method): void
    {
        $call->lead_id = $lead->getKey();

        // A lead flagged as an existing patient carries that patient across, so
        // the call lands on the patient timeline where staff will look for it.
        if ($call->customer_user_id === null && $lead->matched_user_id !== null) {
            $call->customer_user_id = $lead->matched_user_id;
        }

        $call->clinic_id ??= $lead->clinic_id;
        $call->matching_status = CallMatchingStatus::Matched;
        $call->matching_method = $method;
        $call->matched_at = now();
        $call->match_candidates = null;
    }

    /**
     * @param  array<int, array<string, mixed>>  $candidates
     */
    protected function markAmbiguous(Call $call, array $candidates): void
    {
        $call->customer_user_id = null;
        $call->lead_id = null;
        $call->matching_status = CallMatchingStatus::Ambiguous;
        $call->matching_method = CallMatchingMethod::Unknown;
        $call->match_candidates = $candidates;
        $call->matched_at = null;

        Log::channel('calls')->info('Call could not be attributed: the number matches more than one record.', [
            'call_uuid' => $call->uuid,
            'candidates' => count($candidates),
        ]);
    }

    // ──────────────── Agent ────────────────

    /**
     * Identify the staff member who handled the call.
     *
     * Goes through the mapping table first, because the provider's idea of an
     * agent is a phone number and staff phone numbers are not reliable
     * identities — they get swapped, shared and are frequently absent from
     * users.mobile entirely.
     */
    public function resolveAgent(Call $call, ?NormalizedCall $normalized = null): void
    {
        // Never re-derive an agent a person set by hand.
        if ($call->exists && $call->agent_user_id !== null && $call->call_provider_agent_id === null) {
            return;
        }

        $key = $call->employee_phone_key;
        $code = $call->employee_code;

        if (blank($key) && blank($code)) {
            return;
        }

        $mapping = CallProviderAgent::resolve(
            provider: $call->provider,
            phoneKey: $key,
            employeeCode: $code,
            employeeName: $call->employee_name,
            rawNumber: $call->employee_phone,
            metadata: array_filter([
                'emp_tags' => $normalized?->providerData['emp_tags'] ?? null,
            ]),
        );

        if ($mapping === null) {
            return;
        }

        $call->call_provider_agent_id = $mapping->getKey();

        // The clinic set on the mapping applies whether or not a CRM user is
        // linked to it.
        //
        // It used to be read only inside the branch below, so an administrator
        // who picked a clinic for an agent but left the staff member blank got
        // nothing at all - and that is the normal case for a call-centre agent
        // who dials for a branch without holding a CRM login. Their calls then
        // fell through to being filed under whichever clinic the customer
        // happened to belong to, which is a different question entirely.
        //
        // Highest confidence of anything here: somebody stated it deliberately.
        $call->clinic_id ??= $mapping->clinic_id;

        if ($mapping->user_id !== null) {
            $call->agent_user_id = $mapping->user_id;
            $call->assigned_user_id ??= $mapping->user_id;
            $call->clinic_id ??= $mapping->user?->clinic_id;

            return;
        }

        // The mapping exists but nobody has completed it. Fall back to a direct
        // lookup on users.mobile, which covers the common case where staff do
        // call from the number the CRM already knows about, and leaves the
        // mapping row visible for the cases where they do not.
        if ($this->phone->isMatchable($key)) {
            $staff = User::query()
                ->where(fn (Builder $query): Builder => $query
                    ->where('mobile', $key)
                    ->orWhere('mobile', 'LIKE', '%' . $key))
                ->limit(2)
                ->get();

            if ($staff->count() === 1) {
                $call->agent_user_id = $staff->first()->getKey();
                $call->assigned_user_id ??= $staff->first()->getKey();
                $call->clinic_id ??= $staff->first()->clinic_id;

                // Remember the answer so the next call from this handset does
                // not repeat the lookup, and so an administrator can see and
                // correct the association.
                $mapping->forceFill([
                    'user_id' => $staff->first()->getKey(),
                    'clinic_id' => $staff->first()->clinic_id,
                ])->save();
            }
        }
    }

    // ──────────────── Clinic ────────────────

    /**
     * Decide which clinic owns the call.
     *
     * Everything else in this CRM is clinic-scoped, so a call with no clinic is
     * invisible to every user except a super admin. The order below is by
     * confidence: the agent knows best, then the customer, then the Exophone
     * that was dialled, and finally the single-clinic fallback.
     */
    public function resolveClinic(Call $call): void
    {
        if ($call->clinic_id !== null) {
            return;
        }

        $call->clinic_id = $call->agent?->clinic_id
            ?? $call->customer?->clinic_id
            ?? $call->lead?->clinic_id
            ?? $this->clinicForVirtualNumber($call->virtual_number_normalized)
            ?? $this->soleClinicId();
    }

    /**
     * The clinic that owns an Exophone, from the virtual number mapping.
     *
     * Stored as a setting rather than a table: a clinic has one or two virtual
     * numbers and they change about once a year, which does not earn a schema.
     */
    protected function clinicForVirtualNumber(?string $number): ?int
    {
        if (blank($number)) {
            return null;
        }

        $map = json_decode((string) Setting::getValue('call_exotel_number_map', '{}'), true);

        if (! is_array($map)) {
            return null;
        }

        $key = $this->phone->matchKey($number);

        foreach ($map as $mappedNumber => $clinicId) {
            if ($this->phone->matchKey((string) $mappedNumber) === $key) {
                return (int) $clinicId ?: null;
            }
        }

        return null;
    }

    /**
     * When there is only one clinic, every call belongs to it.
     *
     * Guarded on the count rather than just taking the first: in a multi-clinic
     * installation, guessing would file conversations under the wrong branch.
     */
    protected function soleClinicId(): ?int
    {
        $clinics = Clinic::query()->where('is_active', true)->limit(2)->pluck('id');

        return $clinics->count() === 1 ? (int) $clinics->first() : null;
    }
}
