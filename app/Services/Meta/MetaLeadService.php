<?php

declare(strict_types=1);

namespace App\Services\Meta;

use App\Actions\Lead\PersistLeadAction;
use App\DTOs\Lead\ImportSettingsDto;
use App\Enums\PhoneStatus;
use App\Models\Lead;
use App\Models\MetaLeadSyncLog;
use App\Models\MetaPage;
use App\Services\Lead\LeadDuplicateDetectorService;
use App\Services\Lead\LeadFieldResolverService;
use App\Services\Meta\Exceptions\MetaApiException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Fetches one Meta lead and files it as an ordinary CRM lead.
 *
 * This is the join between the Meta integration and the existing lead system,
 * and it deliberately owns no persistence of its own: the write goes through
 * PersistLeadAction, the same action the CSV importer uses, so both paths
 * produce byte-identical leads and dynamic answers.
 */
class MetaLeadService
{
    public function __construct(
        protected MetaGraphApiService $api,
        protected MetaLeadNormalizer $normalizer,
        protected LeadDuplicateDetectorService $duplicateDetector,
        protected PersistLeadAction $persistLead,
        protected LeadFieldResolverService $fieldResolver,
    ) {}

    /**
     * Process one lead end to end, recording the outcome on the sync log.
     *
     * Transient Graph failures are rethrown so the queue can retry them.
     * Everything else is resolved here, because a permanently broken lead
     * should stop consuming attempts and start being visible instead.
     *
     * @throws MetaApiException on a failure worth retrying
     */
    public function process(MetaLeadSyncLog $syncLog): ?Lead
    {
        if ($syncLog->isSettled()) {
            // Layer two of the duplicate defence: a retry of an already
            // finished record must not fetch or write anything.
            return $syncLog->lead;
        }

        $page = $syncLog->metaPage;

        if ($page === null) {
            $syncLog->markFailed('This sync record is not linked to a Meta Page.');

            return null;
        }

        if (! $page->hasUsableToken()) {
            $syncLog->markFailed(
                'No Meta access token is configured. Add one on the Meta Lead Settings screen, then retry this lead.'
            );

            return null;
        }

        $syncLog->markProcessing();

        try {
            $lead = $this->import($syncLog, $page);
        } catch (MetaApiException $exception) {
            $syncLog->markFailed($exception->getMessage());

            // Only a transient failure is worth another attempt; a revoked
            // token would fail identically five more times and bury the
            // message that explains the real problem.
            if ($exception->isTransient()) {
                throw $exception;
            }

            return null;
        }

        return $lead;
    }

    /**
     * @throws MetaApiException
     */
    protected function import(MetaLeadSyncLog $syncLog, MetaPage $page): ?Lead
    {
        $leadgenId = (string) $syncLog->leadgen_id;

        $clinicId = $page->resolveClinicId();

        if ($clinicId === null) {
            $syncLog->markFailed('No clinic exists to file this lead against.');

            return null;
        }

        // Layer two, continued: the CRM may already hold this lead from a CSV
        // import of the same campaign. Matching is on fb_lead_id alone — never
        // phone, because one person may legitimately submit several forms.
        $existing = $this->duplicateDetector->findDuplicate(
            ['fb_lead_id' => $leadgenId],
            $clinicId,
            ['fb_lead_id'],
        );

        if ($existing !== null) {
            $syncLog->markSkipped($existing, sprintf('Lead #%d already exists for this Meta lead id.', $existing->getKey()));

            return $existing;
        }

        $token = $this->tokenFor($page);

        $graphLead = $this->api->getLead($leadgenId, $token);

        // Fill in the Page's name now that a Graph call is affordable. This is
        // what completes a Page that registered itself from a webhook holding
        // nothing but an id.
        $this->refreshPageName($page, $token);

        $formId = $graphLead['form_id'] ?? null;
        $formName = $formId !== null
            ? $this->api->getFormName((string) $formId, $token)
            : null;

        $normalized = $this->normalizer->normalize($graphLead, $page, $clinicId, $formName);
        $attributes = $normalized['attributes'];

        // A lead with no reachable phone number is still worth keeping — it
        // carries campaign attribution and answers — but it is flagged so staff
        // are not left wondering why it cannot be called.
        if (blank($attributes['phone'] ?? null)) {
            $attributes['phone_status'] = PhoneStatus::Invalid->value;
        }

        $attributes['matched_user_id'] = $this->duplicateDetector
            ->findMatchingPatient($attributes, $clinicId)
            ?->getKey();

        try {
            $lead = DB::transaction(fn (): Lead => $this->persistLead->create(
                $attributes,
                $normalized['custom'],
                ImportSettingsDto::fromArray([]),
            ));
        } catch (QueryException $exception) {
            // Layer three: the unique index on (clinic_id, fb_lead_id) is the
            // backstop when two deliveries of the same lead are processed
            // concurrently by different workers. Hitting it means the other
            // worker won, which is success, not an error.
            if (! $this->isUniqueViolation($exception)) {
                throw $exception;
            }

            $winner = $this->duplicateDetector->findDuplicate(
                ['fb_lead_id' => $leadgenId],
                $clinicId,
                ['fb_lead_id'],
            );

            $syncLog->markSkipped($winner, 'Imported concurrently by another worker.');

            return $winner;
        }

        // Usage counters are accumulated by the action and flushed by its
        // caller, so a bulk import can avoid one UPDATE per row.
        $this->fieldResolver->recordUsage($this->persistLead->pullFieldUsage());

        $syncLog->markSuccess($lead);
        $page->forceFill(['last_lead_at' => now()])->save();

        Log::channel('meta_leads')->info('Meta lead imported.', [
            'leadgen_id' => $leadgenId,
            'lead_id' => $lead->getKey(),
            'clinic_id' => $clinicId,
            'page_id' => $page->page_id,
            'answers' => count($normalized['custom']),
        ]);

        return $lead;
    }

    /**
     * The token to call Graph with for this Page.
     *
     * A Page token is derived from the system token where Meta allows it, so
     * that one credential in settings serves every Page. When it cannot be
     * derived — the system token is already a Page token, or lacks
     * pages_show_list — the system token is used directly, which is correct in
     * both of those cases.
     */
    protected function tokenFor(MetaPage $page): string
    {
        // process() has already refused the lead if no token exists at all, so
        // by here this cannot be null.
        return (string) $this->api->tokenForPage($page);
    }

    /**
     * Give a self-registered Page its human-readable name.
     *
     * Best-effort by design: a Page whose name cannot be read still works
     * perfectly well for filing leads, and losing the lead over a cosmetic
     * lookup would be absurd.
     */
    protected function refreshPageName(MetaPage $page, string $token): void
    {
        if (filled($page->page_name) && $page->last_synced_at !== null) {
            return;
        }

        $name = $this->api->getPageName($page->page_id, $token);

        $page->forceFill([
            'page_name' => $name ?: $page->page_name,
            'last_synced_at' => now(),
        ])->save();
    }

    /**
     * Mirrors LeadRowImporterService::isUniqueViolation() so both ingestion
     * paths agree on what a duplicate looks like at the database level.
     */
    protected function isUniqueViolation(QueryException $exception): bool
    {
        return in_array((string) ($exception->errorInfo[1] ?? ''), ['1062'], true)
            || str_contains($exception->getMessage(), 'Integrity constraint violation');
    }
}
