<?php

declare(strict_types=1);

namespace App\DTOs\Lead;

/**
 * The per-import cleaning options chosen on the wizard's settings step.
 */
final readonly class ImportSettingsDto
{
    public function __construct(
        public bool $skipEmptyRows = true,
        public bool $trimSpaces = true,
        public bool $normalizePhone = true,
        public bool $normalizeEmail = true,
        public bool $convertDateFormats = true,
        public bool $ignoreBlankColumns = true,
        public bool $ignoreHiddenColumns = true,
        public bool $ignoreDuplicateHeaders = true,
        public bool $stripMetaPrefixes = true,
        public bool $humanizeAnswers = true,
        public bool $matchExistingPatients = true,
        public bool $autoCreateCustomFields = true,
        public bool $skipInFileDuplicates = true,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $defaults = self::defaults();
        $data = array_merge($defaults, $data);

        return new self(
            skipEmptyRows: (bool) $data['skip_empty_rows'],
            trimSpaces: (bool) $data['trim_spaces'],
            normalizePhone: (bool) $data['normalize_phone'],
            normalizeEmail: (bool) $data['normalize_email'],
            convertDateFormats: (bool) $data['convert_date_formats'],
            ignoreBlankColumns: (bool) $data['ignore_blank_columns'],
            ignoreHiddenColumns: (bool) $data['ignore_hidden_columns'],
            ignoreDuplicateHeaders: (bool) $data['ignore_duplicate_headers'],
            stripMetaPrefixes: (bool) $data['strip_meta_prefixes'],
            humanizeAnswers: (bool) $data['humanize_answers'],
            matchExistingPatients: (bool) $data['match_existing_patients'],
            autoCreateCustomFields: (bool) $data['auto_create_custom_fields'],
            skipInFileDuplicates: (bool) $data['skip_in_file_duplicates'],
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'skip_empty_rows' => $this->skipEmptyRows,
            'trim_spaces' => $this->trimSpaces,
            'normalize_phone' => $this->normalizePhone,
            'normalize_email' => $this->normalizeEmail,
            'convert_date_formats' => $this->convertDateFormats,
            'ignore_blank_columns' => $this->ignoreBlankColumns,
            'ignore_hidden_columns' => $this->ignoreHiddenColumns,
            'ignore_duplicate_headers' => $this->ignoreDuplicateHeaders,
            'strip_meta_prefixes' => $this->stripMetaPrefixes,
            'humanize_answers' => $this->humanizeAnswers,
            'match_existing_patients' => $this->matchExistingPatients,
            'auto_create_custom_fields' => $this->autoCreateCustomFields,
            'skip_in_file_duplicates' => $this->skipInFileDuplicates,
        ];
    }

    /**
     * @return array<string, bool>
     */
    public static function defaults(): array
    {
        return [
            'skip_empty_rows' => true,
            'trim_spaces' => true,
            'normalize_phone' => true,
            'normalize_email' => true,
            'convert_date_formats' => true,
            'ignore_blank_columns' => true,
            'ignore_hidden_columns' => true,
            'ignore_duplicate_headers' => true,
            'strip_meta_prefixes' => true,
            'humanize_answers' => true,
            'match_existing_patients' => (bool) config('leads.duplicates.match_existing_patients', true),
            'auto_create_custom_fields' => (bool) config('leads.import.auto_create_custom_fields', true),
            'skip_in_file_duplicates' => true,
        ];
    }

    /**
     * Human-readable labels and help text for the settings step.
     *
     * @return array<string, array{label: string, help: string}>
     */
    public static function descriptors(): array
    {
        return [
            'skip_empty_rows' => [
                'label' => 'Skip empty rows',
                'help' => 'Rows where every mapped column is blank are ignored instead of failing.',
            ],
            'trim_spaces' => [
                'label' => 'Trim spaces',
                'help' => 'Remove leading and trailing whitespace from every value.',
            ],
            'normalize_phone' => [
                'label' => 'Normalize phone numbers',
                'help' => 'Convert +91XXXXXXXXXX, 91XXXXXXXXXX, 0XXXXXXXXXX and bare 10-digit numbers to one standard format.',
            ],
            'normalize_email' => [
                'label' => 'Normalize emails',
                'help' => 'Lowercase and trim email addresses before validating them.',
            ],
            'convert_date_formats' => [
                'label' => 'Convert date formats',
                'help' => "Parse Meta's ISO timestamps and common date formats into proper datetimes.",
            ],
            'ignore_blank_columns' => [
                'label' => 'Ignore blank columns',
                'help' => 'Columns with no header and no data are dropped before mapping.',
            ],
            'ignore_hidden_columns' => [
                'label' => 'Ignore hidden columns',
                'help' => 'Columns explicitly set to Ignore on the mapping step are not read at all.',
            ],
            'ignore_duplicate_headers' => [
                'label' => 'Ignore duplicate headers',
                'help' => 'When the same header appears twice, only the first occurrence is used.',
            ],
            'strip_meta_prefixes' => [
                'label' => 'Strip Facebook ID prefixes',
                'help' => 'Remove the l:, ag:, as:, c:, f: and p: prefixes Meta adds to IDs and phone numbers.',
            ],
            'humanize_answers' => [
                'label' => 'Humanize answers',
                'help' => 'Show "dullness_/_tanning" as "Dullness / Tanning" while keeping the original value for exact matching.',
            ],
            'match_existing_patients' => [
                'label' => 'Flag existing patients',
                'help' => 'Link a lead to an existing patient when the phone or email matches. The lead is still imported.',
            ],
            'auto_create_custom_fields' => [
                'label' => 'Auto-create custom questions',
                'help' => 'Unmapped columns become dynamic custom fields instead of being discarded.',
            ],
            'skip_in_file_duplicates' => [
                'label' => 'Skip repeats within this file',
                'help' => 'When the same person appears twice in one file, import only the first occurrence.',
            ],
        ];
    }
}
