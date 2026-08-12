<?php

declare(strict_types=1);

namespace App\Services\Meta;

use App\DTOs\Lead\ColumnMappingDto;
use App\DTOs\Lead\ImportSettingsDto;
use App\Enums\CrmLeadField;
use App\Enums\LeadSource;
use App\Enums\LeadStatus;
use App\Models\Lead;
use App\Models\LeadCustomField;
use App\Models\MetaPage;
use App\Services\Lead\ColumnAutoMapperService;
use App\Services\Lead\MetaPrefixStripper;
use App\Services\Lead\PhoneNormalizerService;
use App\Services\Lead\ValueNormalizerService;

/**
 * Turns one Graph API lead into the shape the CRM's persistence layer expects.
 *
 * Pure transformation: nothing here touches the database, which is what lets
 * the same normalisation be exercised in tests against a fixture payload and
 * reused unchanged by a future historical import.
 *
 * The important decision is that form questions are resolved through the very
 * same ColumnAutoMapperService the CSV importer uses. A question arriving by
 * webhook and the identical question arriving later in a Meta CSV export
 * therefore land on the same lead_custom_fields row, instead of fragmenting
 * into two near-identical questions that filter separately.
 */
class MetaLeadNormalizer
{
    /**
     * Form answers may only fill contact-shaped CRM fields.
     *
     * Attribution columns are populated from the Graph node's own properties,
     * so a form question innocently named "id" or "campaign" must never be
     * allowed to overwrite fb_lead_id or campaign_name. Anything outside this
     * list is stored as a dynamic answer instead.
     */
    protected const ANSWERABLE_CORE_FIELDS = [
        CrmLeadField::FullName,
        CrmLeadField::FirstName,
        CrmLeadField::LastName,
        CrmLeadField::Phone,
        CrmLeadField::Email,
        CrmLeadField::City,
        CrmLeadField::State,
        CrmLeadField::Pincode,
        CrmLeadField::Notes,
    ];

    public function __construct(
        protected ColumnAutoMapperService $autoMapper,
        protected PhoneNormalizerService $phoneNormalizer,
        protected ValueNormalizerService $valueNormalizer,
        protected MetaPrefixStripper $prefixStripper,
    ) {}

    /**
     * @param  array<string, mixed>  $graphLead  The lead node exactly as Graph returned it.
     * @param  string|null  $formName  Resolved separately; the lead node does not carry it.
     * @return array{
     *     attributes: array<string, mixed>,
     *     custom: array<int, array{key: string, label: string, type: \App\Enums\LeadFieldType, value: string}>,
     *     phone_status: string
     * }
     */
    public function normalize(array $graphLead, MetaPage $page, ?string $formName = null): array
    {
        $answers = $this->collectAnswers($graphLead['field_data'] ?? []);

        ['attributes' => $attributes, 'custom' => $custom] = $this->mapAnswers($answers, $page->clinic_id);

        $attributes = $this->applyAttribution($attributes, $graphLead, $page, $formName);
        $attributes = $this->decorate($attributes, $graphLead, $page);

        return [
            'attributes' => $attributes,
            'custom' => $custom,
            'phone_status' => (string) ($attributes['phone_status'] ?? ''),
        ];
    }

    /**
     * Flatten Meta's field_data into question => answer.
     *
     * Every answer is an array because a multi-select question returns one
     * entry per choice. Joining them with the same separator the CSV exports
     * use means ValueNormalizerService::splitMultiValue() handles webhook and
     * exported answers identically.
     *
     * @param  array<int, array{name?: string, values?: array<int, string>}>  $fieldData
     * @return array<string, string>
     */
    protected function collectAnswers(array $fieldData): array
    {
        $separator = (string) config('leads.csv.multi_value_separator', '|');
        $answers = [];

        foreach ($fieldData as $entry) {
            $name = trim((string) ($entry['name'] ?? ''));

            if ($name === '') {
                continue;
            }

            $values = array_values(array_filter(
                array_map(fn ($value): string => trim((string) $value), (array) ($entry['values'] ?? [])),
                fn (string $value): bool => $value !== '',
            ));

            if ($values === []) {
                continue;
            }

            // A form can legitimately repeat a question name; keep both answers
            // rather than letting the later one silently win.
            $answers[$name] = isset($answers[$name])
                ? $answers[$name] . $separator . implode($separator, $values)
                : implode($separator, $values);
        }

        return $answers;
    }

    /**
     * Split the answers into core lead columns and dynamic questions.
     *
     * @param  array<string, string>  $answers
     * @return array{attributes: array<string, mixed>, custom: array<int, array<string, mixed>>}
     */
    protected function mapAnswers(array $answers, int $clinicId): array
    {
        $settings = ImportSettingsDto::fromArray([]);
        $questions = array_keys($answers);

        // Sample values let the mapper infer a usable field type, so a
        // single-choice question becomes a filterable select rather than text.
        $distinctValues = array_map(fn (string $value): array => [$value], $answers);

        $mapping = $this->autoMapper->map($questions, $clinicId, $distinctValues);

        $attributes = [];
        $custom = [];

        foreach ($mapping as $question => $dto) {
            $raw = $this->prefixStripper->strip($answers[$question] ?? null);

            if ($raw === '') {
                continue;
            }

            $field = $dto->isCore() ? $dto->coreField() : null;

            if ($field !== null && in_array($field, self::ANSWERABLE_CORE_FIELDS, true)) {
                $attributes = $this->applyCoreAnswer($attributes, $field, $raw, $settings);

                continue;
            }

            $custom[] = $this->buildCustomAnswer($dto, $question, $raw);
        }

        return ['attributes' => $attributes, 'custom' => $custom];
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    protected function applyCoreAnswer(
        array $attributes,
        CrmLeadField $field,
        string $raw,
        ImportSettingsDto $settings,
    ): array {
        // Phone goes through its own path so the salvage outcome survives into
        // phone_status and phone_raw, exactly as the CSV importer does it.
        if ($field === CrmLeadField::Phone) {
            $result = $this->phoneNormalizer->normalize($raw);

            $attributes['phone'] = $result->value;
            $attributes['phone_raw'] = $result->raw;
            $attributes['phone_status'] = $result->status->value;

            return $attributes;
        }

        if ($field === CrmLeadField::Email) {
            $attributes['email'] = $this->valueNormalizer->normalizeEmail($raw);
            $attributes['email_raw'] = $raw;

            return $attributes;
        }

        $attributes[$field->value] = $this->valueNormalizer->normalizeForField($field, $raw, $settings);

        return $attributes;
    }

    /**
     * @return array{key: string, label: string, type: \App\Enums\LeadFieldType, value: string}
     */
    protected function buildCustomAnswer(ColumnMappingDto $dto, string $question, string $value): array
    {
        // A question the mapper matched to an existing field carries that
        // field's key, which is what folds it into the existing question rather
        // than creating a parallel one.
        $label = $dto->isCustom() ? $dto->resolvedCustomLabel() : $question;

        return [
            'key' => $dto->customKey() ?? LeadCustomField::makeKey($label),
            'label' => $label,
            'type' => $dto->customType,
            'value' => $value,
        ];
    }

    /**
     * Copy Meta's attribution onto the lead.
     *
     * Names are only present when the token carries ads permissions, so each is
     * taken when offered and left null otherwise. The ids are always stored,
     * which keeps the lead answerable — "which campaign was this?" — even when
     * the friendly name is not available.
     *
     * @param  array<string, mixed>  $attributes
     * @param  array<string, mixed>  $graphLead
     * @return array<string, mixed>
     */
    protected function applyAttribution(array $attributes, array $graphLead, MetaPage $page, ?string $formName): array
    {
        $attributes['fb_lead_id'] = $this->identifier($graphLead['id'] ?? null);
        $attributes['fb_created_time'] = $this->valueNormalizer->normalizeDateTime(
            isset($graphLead['created_time']) ? (string) $graphLead['created_time'] : null
        );

        $attributes['campaign_id'] = $this->identifier($graphLead['campaign_id'] ?? null);
        $attributes['campaign_name'] = $this->text($graphLead['campaign_name'] ?? null);
        $attributes['adset_id'] = $this->identifier($graphLead['adset_id'] ?? null);
        $attributes['adset_name'] = $this->text($graphLead['adset_name'] ?? null);
        $attributes['ad_id'] = $this->identifier($graphLead['ad_id'] ?? null);
        $attributes['ad_name'] = $this->text($graphLead['ad_name'] ?? null);
        $attributes['form_id'] = $this->identifier($graphLead['form_id'] ?? null);
        $attributes['form_name'] = $this->text($formName);

        $attributes['page_name'] = $page->page_name;

        // The column is varchar(20); Meta sends "ig" or "fb" but the value is
        // clamped rather than trusted.
        $platform = $this->text($graphLead['platform'] ?? null);
        $attributes['platform'] = $platform === null ? null : mb_substr($platform, 0, 20);

        // An organic lead has no paid ad behind it. Meta omits the flag more
        // often than it sends false, so absence is treated as paid.
        $attributes['is_organic'] = filter_var(
            $graphLead['is_organic'] ?? false,
            FILTER_VALIDATE_BOOLEAN,
        );

        return $attributes;
    }

    /**
     * Fill in everything the payload does not state directly.
     *
     * Mirrors LeadRowImporterService::decorate() so a webhook lead and an
     * imported lead are indistinguishable once written.
     *
     * @param  array<string, mixed>  $attributes
     * @param  array<string, mixed>  $graphLead
     * @return array<string, mixed>
     */
    protected function decorate(array $attributes, array $graphLead, MetaPage $page): array
    {
        $attributes['clinic_id'] = $page->clinic_id;

        // Null rather than a synthetic batch: this lead did not come from a
        // file, and the Filament UI already treats a null import correctly.
        $attributes['lead_import_id'] = null;

        if (! empty($attributes['full_name'])) {
            $parts = $this->valueNormalizer->splitName((string) $attributes['full_name']);
            $attributes['first_name'] ??= $parts['first'];
            $attributes['last_name'] ??= $parts['last'];
        }

        // "ig" and "fb" say which channel this actually came from, which is
        // more precise than a blanket "facebook".
        $attributes['source'] = LeadSource::fromMetaPlatform(
            isset($attributes['platform']) ? (string) $attributes['platform'] : null
        )->value;

        $attributes['status'] = LeadStatus::New->value;

        // The whole Graph response, so a mapping mistake can be replayed
        // without going back to Meta.
        $attributes['raw_payload'] = $graphLead;

        $attributes['row_hash'] = Lead::buildRowHash(
            $attributes['fb_lead_id'] ?? null,
            $attributes['phone'] ?? null,
            $attributes['email'] ?? null,
        );

        return $attributes;
    }

    /**
     * Graph returns bare ids, but exports prefix them. Stripping defensively
     * keeps a webhook id byte-identical to the same lead's id from a CSV, which
     * is what lets duplicate detection see them as one lead.
     */
    protected function identifier(mixed $value): ?string
    {
        $value = $this->prefixStripper->strip($value === null ? null : (string) $value);

        return $value === '' ? null : mb_substr($value, 0, 64);
    }

    protected function text(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));

        return $value === '' ? null : mb_substr($value, 0, 255);
    }
}
