<?php

declare(strict_types=1);

namespace App\Services\Lead;

use App\Actions\Lead\PersistLeadAction;
use App\DTOs\Lead\ColumnMappingDto;
use App\DTOs\Lead\ImportRowResult;
use App\DTOs\Lead\ImportSettingsDto;
use App\Enums\CrmLeadField;
use App\Enums\DuplicateStrategy;
use App\Enums\ImportFailureReason;
use App\Enums\LeadSource;
use App\Enums\LeadStatus;
use App\Enums\PhoneStatus;
use App\Models\Lead;
use App\Models\LeadImport;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Throwable;

/**
 * Turns one CSV row into one lead.
 *
 * prepare() is called once per chunk so the mapping, settings and strategy are
 * resolved a single time rather than per row; import() is then called for each
 * row and is deliberately total — it never throws, it always returns a result
 * describing what happened, so one bad row can never abort a 100k-row file.
 */
class LeadRowImporterService
{
    protected LeadImport $import;

    /** @var array<string, ColumnMappingDto> */
    protected array $mapping = [];

    protected ImportSettingsDto $settings;

    protected DuplicateStrategy $strategy;

    /** @var array<int, string> */
    protected array $matchFields = [];

    public function __construct(
        protected MetaPrefixStripper $prefixStripper,
        protected ValueNormalizerService $valueNormalizer,
        protected PhoneNormalizerService $phoneNormalizer,
        protected LeadDuplicateDetectorService $duplicateDetector,
        protected PersistLeadAction $persistLead,
        protected LeadFieldResolverService $fieldResolver,
    ) {}

    /**
     * Resolve everything that is constant for the whole import.
     */
    public function prepare(LeadImport $import): static
    {
        $this->import = $import;
        $this->settings = ImportSettingsDto::fromArray($import->settings ?? []);
        $this->strategy = $import->duplicate_strategy ?? DuplicateStrategy::Skip;
        $this->matchFields = $import->duplicate_match_fields ?: (array) config('leads.duplicates.default_match_fields', ['fb_lead_id']);

        $this->mapping = [];

        foreach ($import->column_mapping ?? [] as $column => $definition) {
            $dto = ColumnMappingDto::fromArray(is_array($definition) ? $definition : ['csv_column' => $column]);

            if (! $dto->isIgnored()) {
                $this->mapping[$dto->csvColumn] = $dto;
            }
        }

        return $this;
    }

    /**
     * Import a single row.
     *
     * @param  array<string, string>  $row
     */
    public function import(array $row, int $rowNumber): ImportRowResult
    {
        try {
            return $this->process($row, $rowNumber);
        } catch (QueryException $exception) {
            // The unique index on (clinic_id, fb_lead_id) is the last line of
            // defence when two chunk jobs process the same lead concurrently.
            // Hitting it means another worker already imported this row, which
            // is a duplicate rather than an error.
            if ($this->isUniqueViolation($exception)) {
                return ImportRowResult::skipped($rowNumber, 'Already imported by a concurrent chunk of this file.');
            }

            return ImportRowResult::failed(
                $rowNumber,
                ImportFailureReason::Database,
                'Database error: ' . $this->firstLine($exception->getMessage()),
            );
        } catch (Throwable $exception) {
            return ImportRowResult::failed(
                $rowNumber,
                ImportFailureReason::Transform,
                'Unexpected error: ' . $this->firstLine($exception->getMessage()),
            );
        }
    }

    /**
     * @param  array<string, string>  $row
     */
    protected function process(array $row, int $rowNumber): ImportRowResult
    {
        if ($this->settings->stripMetaPrefixes) {
            $row = $this->prefixStripper->stripRow($row);
        }

        if ($this->settings->skipEmptyRows && $this->isBlankRow($row)) {
            return ImportRowResult::skipped($rowNumber, 'The row is empty.');
        }

        ['attributes' => $attributes, 'custom' => $customAnswers, 'phone' => $phoneResult]
            = $this->mapRow($row);

        // ─── Required fields ────────────────────────────────────────────
        if ($phoneResult !== null && $phoneResult->status === PhoneStatus::Invalid) {
            return ImportRowResult::failed(
                $rowNumber,
                ImportFailureReason::Validation,
                $phoneResult->reason ?? 'The phone number is not valid.',
                ['phone' => [$phoneResult->reason ?? 'The phone number is not valid.']],
            );
        }

        if (($attributes['phone'] ?? null) === null) {
            return ImportRowResult::failed(
                $rowNumber,
                ImportFailureReason::MissingRequired,
                'The row has no phone number, so the lead cannot be contacted.',
                ['phone' => ['A phone number is required.']],
            );
        }

        // ─── Validation ─────────────────────────────────────────────────
        $validator = Validator::make($attributes, $this->validationRules());

        if ($validator->fails()) {
            return ImportRowResult::failed(
                $rowNumber,
                ImportFailureReason::Validation,
                implode(' ', $validator->errors()->all()),
                $validator->errors()->toArray(),
            );
        }

        $attributes = $this->decorate($attributes, $row);

        // ─── Duplicates ─────────────────────────────────────────────────
        $existing = $this->strategy === DuplicateStrategy::CreateDuplicate
            ? null
            : $this->duplicateDetector->findDuplicate($attributes, $this->import->clinic_id, $this->matchFields);

        if ($existing !== null && $this->strategy === DuplicateStrategy::Skip) {
            return ImportRowResult::skipped(
                $rowNumber,
                sprintf('Matches existing lead #%d on %s.', $existing->getKey(), implode(' or ', $this->matchFields)),
                $existing->getKey(),
            );
        }

        // ─── Persist ────────────────────────────────────────────────────
        return DB::transaction(function () use ($existing, $attributes, $customAnswers, $rowNumber): ImportRowResult {
            if ($existing !== null) {
                $lead = $this->persistLead->apply($existing, $attributes, $customAnswers, $this->strategy, $this->settings);

                return ImportRowResult::updated($rowNumber, $lead->getKey());
            }

            $lead = $this->persistLead->create($attributes, $customAnswers, $this->settings);

            return ImportRowResult::imported($rowNumber, $lead->getKey());
        });
    }

    /**
     * Apply the confirmed mapping to a raw row.
     *
     * @param  array<string, string>  $row
     * @return array{attributes: array<string, mixed>, custom: array<int, array<string, mixed>>, phone: ?\App\DTOs\Lead\PhoneNormalizationResult}
     */
    public function mapRow(array $row): array
    {
        $attributes = [];
        $custom = [];
        $phoneResult = null;

        foreach ($this->mapping as $column => $dto) {
            $raw = $row[$column] ?? null;

            if ($dto->isCore()) {
                $field = $dto->coreField();

                if ($field === null) {
                    continue;
                }

                // The phone is normalised through its own path so the salvage
                // outcome is available for phone_status and phone_raw.
                if ($field === CrmLeadField::Phone) {
                    $phoneResult = $this->phoneNormalizer->normalize($raw);
                    $attributes['phone'] = $phoneResult->value;
                    $attributes['phone_raw'] = $phoneResult->raw;
                    $attributes['phone_status'] = $phoneResult->status->value;

                    continue;
                }

                if ($field === CrmLeadField::Email) {
                    $attributes['email'] = $this->valueNormalizer->normalizeEmail($raw);
                    $attributes['email_raw'] = trim((string) $raw) ?: null;

                    continue;
                }

                $attributes[$field->value] = $this->valueNormalizer->normalizeForField($field, $raw, $this->settings);

                continue;
            }

            if ($dto->isCustom() && $this->settings->autoCreateCustomFields) {
                $value = trim((string) $raw);

                if ($value === '') {
                    continue;
                }

                $custom[] = [
                    'key' => $dto->customKey() ?? \App\Models\LeadCustomField::makeKey($dto->resolvedCustomLabel()),
                    'label' => $dto->resolvedCustomLabel(),
                    'type' => $dto->customType,
                    'value' => $value,
                ];
            }
        }

        return ['attributes' => $attributes, 'custom' => $custom, 'phone' => $phoneResult];
    }

    /**
     * Fill in everything the file does not state directly.
     *
     * @param  array<string, mixed>  $attributes
     * @param  array<string, string>  $row
     * @return array<string, mixed>
     */
    protected function decorate(array $attributes, array $row): array
    {
        $attributes['clinic_id'] = $this->import->clinic_id;
        $attributes['lead_import_id'] = $this->import->getKey();

        // Meta gives one name field; the CRM wants it split for sorting and
        // salutations.
        if (! empty($attributes['full_name'])) {
            $parts = $this->valueNormalizer->splitName($attributes['full_name']);
            $attributes['first_name'] ??= $parts['first'];
            $attributes['last_name'] ??= $parts['last'];
        }

        // "ig" and "fb" in the platform column tell us which channel this
        // actually came from, which is more precise than a blanket "facebook".
        $attributes['source'] = isset($attributes['platform'])
            ? LeadSource::fromMetaPlatform((string) $attributes['platform'])->value
            : LeadSource::Facebook->value;

        // status on the leads table is the CRM pipeline stage, not Meta's
        // completion flag. Meta's value is preserved separately.
        if (isset($attributes['status'])) {
            $attributes['fb_lead_status'] ??= $attributes['status'];
        }

        $attributes['status'] = LeadStatus::New->value;

        if ($this->settings->matchExistingPatients) {
            $patient = $this->duplicateDetector->findMatchingPatient($attributes, $this->import->clinic_id);
            $attributes['matched_user_id'] = $patient?->getKey();
        }

        $attributes['raw_payload'] = $row;
        $attributes['row_hash'] = Lead::buildRowHash(
            $attributes['fb_lead_id'] ?? null,
            $attributes['phone'] ?? null,
            $attributes['email'] ?? null,
        );

        return $attributes;
    }

    /**
     * Build validation rules from the fields this import actually maps.
     *
     * Rules come from CrmLeadField so the importer and the manual lead form can
     * never disagree about what a valid phone number is.
     *
     * @return array<string, array<int, string>>
     */
    protected function validationRules(): array
    {
        $rules = [];

        foreach ($this->mapping as $dto) {
            $field = $dto->coreField();

            if ($field !== null) {
                $rules[$field->value] = $field->rules();
            }
        }

        // The phone has already been normalised and salvaged by this point, so
        // the rule here only guards against it having been dropped entirely.
        $rules['phone'] = ['required', 'string', 'max:20'];

        return $rules;
    }

    /**
     * @param  array<string, string>  $row
     */
    protected function isBlankRow(array $row): bool
    {
        foreach ($this->mapping as $column => $dto) {
            if (trim((string) ($row[$column] ?? '')) !== '') {
                return false;
            }
        }

        return true;
    }

    /**
     * Flush the deferred custom-field usage counters.
     */
    public function flushFieldUsage(): void
    {
        $this->fieldResolver->recordUsage($this->persistLead->pullFieldUsage());
    }

    protected function isUniqueViolation(QueryException $exception): bool
    {
        return in_array((string) ($exception->errorInfo[1] ?? ''), ['1062'], true)
            || str_contains($exception->getMessage(), 'Integrity constraint violation');
    }

    protected function firstLine(string $message): string
    {
        $message = strtok($message, "\n") ?: $message;

        return mb_substr($message, 0, 300);
    }
}
