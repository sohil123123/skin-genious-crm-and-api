<?php

declare(strict_types=1);

namespace App\Services\Lead;

use App\Enums\LeadClientStatus;
use App\Models\Lead;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Which enquiries turned into clients.
 *
 * Nothing in the schema records this. Lead::matched_user_id looks like the
 * answer and is not: it is written during import, when the matcher looks for a
 * client who already exists, so it captures the opposite population — the
 * people who were already ours when an ad reached them again. A lead who
 * enquires on Monday and is created as a client on Tuesday never gets it set,
 * because on Monday there was nobody to match.
 *
 * So conversion is derived, from two facts a person would use: the same phone
 * number, and which record came first. Lead first, then the client record,
 * means the enquiry produced the client.
 *
 * Bound scoped in AppServiceProvider, which is what makes the memo below
 * correct: it lasts one request, and is rebuilt on the next. A static would
 * survive between requests under Octane and between jobs in a worker, serving
 * yesterday's answer to today's page — the exact bug this codebase already had
 * once, in LeadActionService::eligibleLeads().
 */
class LeadConversionService
{
    /** @var array<string, Collection<string, Carbon>> */
    protected array $memo = [];

    public function __construct(
        protected PhoneNormalizerService $phones,
    ) {}

    /**
     * The first client record for each phone number that appears on a lead.
     *
     * Carries the id as well as the date because the Client column links
     * through to the person, and matched_user_id cannot supply it for a
     * converted lead — it is null on exactly those.
     *
     * Only numbers that appear on a lead are included: the map exists to answer
     * questions about leads, and a clinic's whole client list would be far
     * larger than it needs to be.
     *
     * @return Collection<string, array{id: int, created_at: Carbon}>
     */
    public function clientsByPhoneKey(?int $clinicId = null): Collection
    {
        $cacheKey = (string) ($clinicId ?? 'all');

        if (isset($this->memo[$cacheKey])) {
            return $this->memo[$cacheKey];
        }

        $leadKeys = [];

        Lead::query()
            ->whereNotNull('phone')
            ->when($clinicId !== null, fn (Builder $query) => $query->where('clinic_id', $clinicId))
            ->select(['id', 'phone'])
            ->cursor()
            ->each(function (Lead $lead) use (&$leadKeys): void {
                $key = $this->phones->matchKey($lead->phone);

                if ($key !== null) {
                    $leadKeys[$key] = true;
                }
            });

        $map = collect();

        if ($leadKeys === []) {
            return $this->memo[$cacheKey] = $map;
        }

        // Matched in PHP rather than SQL because the two sides store the same
        // number differently — "+919610003186" on a lead, "9610003186" on a
        // client — and the normaliser that reconciles them is PHP. Three
        // columns, streamed, so a clinic with thousands of clients stays cheap.
        User::query()
            // whereHas rather than Spatie's role() scope, which throws
            // RoleDoesNotExist when the role row is absent. This runs while
            // rendering the Leads table, and a missing seed row should mean "no
            // clients matched", not a 500 on the page.
            ->whereHas('roles', fn (Builder $query) => $query->where('name', config('project.roles.client')))
            ->whereNotNull('mobile')
            ->when($clinicId !== null, fn (Builder $query) => $query->where('users.clinic_id', $clinicId))
            ->select(['users.id', 'users.mobile', 'users.created_at'])
            ->cursor()
            ->each(function (User $user) use ($leadKeys, $map): void {
                $key = $this->phones->matchKey($user->mobile);

                if ($key === null || ! isset($leadKeys[$key]) || $user->created_at === null) {
                    return;
                }

                // Earliest wins. A person with two client records — a duplicate,
                // or a transfer between clinics — converted on the first.
                $existing = $map->get($key);

                if ($existing === null || $user->created_at->lt($existing['created_at'])) {
                    $map->put($key, [
                        'id' => (int) $user->getKey(),
                        'created_at' => $user->created_at->copy(),
                    ]);
                }
            });

        return $this->memo[$cacheKey] = $map;
    }

    /**
     * The client record behind this enquiry, if there is one.
     *
     * @return array{id: int, created_at: Carbon}|null
     */
    public function clientFor(Lead $lead, ?int $clinicId = null): ?array
    {
        $key = $this->phones->matchKey($lead->phone);

        return $key === null ? null : $this->clientsByPhoneKey($clinicId)->get($key);
    }

    /**
     * Whether this enquiry brought somebody in, reached somebody already ours,
     * or neither.
     *
     * Compared against this lead rather than against the person's first ever
     * enquiry, so a second form filled in by somebody who is already a client
     * reads as Existing. They converted once, on the enquiry that brought them
     * in, and a later form should not claim the credit again.
     */
    public function statusFor(Lead $lead, ?int $clinicId = null): ?LeadClientStatus
    {
        $client = $this->clientFor($lead, $clinicId);

        if ($client === null || $lead->created_at === null) {
            return null;
        }

        return $client['created_at']->gte($lead->created_at)
            ? LeadClientStatus::Converted
            : LeadClientStatus::Existing;
    }

    /**
     * Whether this particular enquiry produced a client.
     */
    public function leadBecameClient(Lead $lead, ?int $clinicId = null): bool
    {
        return $this->statusFor($lead, $clinicId) === LeadClientStatus::Converted;
    }

    /**
     * How many clients exist because a lead enquired.
     *
     * Counted per person rather than per lead: somebody who filled in three ad
     * forms and then came in is one client, not three conversions. Judged
     * against their first enquiry, which is the one that brought them in.
     */
    public function countClientsFromLeads(?int $clinicId = null): int
    {
        $earliestLead = [];

        Lead::query()
            ->whereNotNull('phone')
            ->when($clinicId !== null, fn (Builder $query) => $query->where('clinic_id', $clinicId))
            ->select(['id', 'phone', 'created_at'])
            ->cursor()
            ->each(function (Lead $lead) use (&$earliestLead): void {
                $key = $this->phones->matchKey($lead->phone);

                if ($key === null || $lead->created_at === null) {
                    return;
                }

                $earliestLead[$key] = isset($earliestLead[$key])
                    ? min($earliestLead[$key], $lead->created_at->getTimestamp())
                    : $lead->created_at->getTimestamp();
            });

        return $this->clientsByPhoneKey($clinicId)
            ->filter(fn (array $client, string $key): bool => isset($earliestLead[$key])
                && $client['created_at']->getTimestamp() >= $earliestLead[$key])
            ->count();
    }
}
