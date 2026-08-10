<?php

declare(strict_types=1);

namespace App\Services\Lead;

use App\Enums\CrmLeadField;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Decides whether an incoming row is somebody the CRM already knows.
 *
 * Two distinct questions are answered here and it matters that they stay
 * separate:
 *
 *   findDuplicate()      — is this the same *lead*? Drives skip/update/merge.
 *   findMatchingPatient() — is this an existing *patient*? Only ever sets a
 *                           flag, because a lead is not a patient and merging
 *                           the two automatically would corrupt clinical
 *                           records with unverified ad-form data.
 *
 * All lead matching is scoped to the clinic, so two clinics running the same
 * campaign do not collide.
 */
class LeadDuplicateDetectorService
{
    public function __construct(
        protected PhoneNormalizerService $phoneNormalizer,
    ) {}

    /**
     * Find an existing lead matching this row on the configured keys.
     *
     * Keys are evaluated in the order given, most reliable first. Meta's own
     * lead id is unique per submission and present on every export row, which
     * makes it the only key that never produces a false positive — a shared
     * family phone number legitimately belongs to two different leads.
     *
     * @param  array<string, mixed>  $attributes  Normalised core lead attributes.
     * @param  array<int, string>  $matchFields
     */
    public function findDuplicate(array $attributes, int $clinicId, array $matchFields): ?Lead
    {
        foreach ($matchFields as $field) {
            $lead = match ($field) {
                CrmLeadField::FbLeadId->value => $this->matchByLeadId($attributes, $clinicId),
                CrmLeadField::Phone->value => $this->matchByPhone($attributes, $clinicId),
                CrmLeadField::Email->value => $this->matchByEmail($attributes, $clinicId),
                default => null,
            };

            if ($lead !== null) {
                return $lead;
            }
        }

        return null;
    }

    /**
     * Find an existing patient who appears to be this lead.
     *
     * Matched on the last national digits rather than the full string, because
     * patient records predate this module and were never normalised.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function findMatchingPatient(array $attributes, int $clinicId): ?User
    {
        $phoneKey = $this->phoneNormalizer->matchKey($attributes['phone'] ?? null);
        $email = $attributes['email'] ?? null;

        if ($phoneKey === null && $email === null) {
            return null;
        }

        return User::query()
            ->withoutGlobalScopes()
            ->where('clinic_id', $clinicId)
            ->where(function (Builder $query) use ($phoneKey, $email): void {
                if ($phoneKey !== null) {
                    $query->orWhere('mobile', 'like', '%' . $phoneKey);
                }

                if ($email !== null) {
                    $query->orWhere('email', $email);
                }
            })
            ->first();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function matchByLeadId(array $attributes, int $clinicId): ?Lead
    {
        $leadId = $attributes['fb_lead_id'] ?? null;

        if ($leadId === null || $leadId === '') {
            return null;
        }

        return Lead::query()
            ->where('clinic_id', $clinicId)
            ->where('fb_lead_id', $leadId)
            ->first();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function matchByPhone(array $attributes, int $clinicId): ?Lead
    {
        $phoneKey = $this->phoneNormalizer->matchKey($attributes['phone'] ?? null);

        if ($phoneKey === null) {
            return null;
        }

        return Lead::query()
            ->where('clinic_id', $clinicId)
            ->where('phone', 'like', '%' . $phoneKey)
            ->first();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function matchByEmail(array $attributes, int $clinicId): ?Lead
    {
        $email = $attributes['email'] ?? null;

        if ($email === null || $email === '') {
            return null;
        }

        return Lead::query()
            ->where('clinic_id', $clinicId)
            ->where('email', $email)
            ->first();
    }
}
