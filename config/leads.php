<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Storage
    |--------------------------------------------------------------------------
    |
    | Uploaded lead CSV files contain personally identifiable information, so
    | they are stored on the private "local" disk and are only ever served
    | back through an authorised Filament action, never a public URL.
    |
    */

    'storage' => [
        'disk' => env('LEAD_IMPORT_DISK', 'local'),
        'directory' => 'lead-imports',
        'failed_directory' => 'lead-imports/failed',
        // Uploaded originals older than this are prunable. Null disables pruning.
        'retention_days' => env('LEAD_IMPORT_RETENTION_DAYS', 180),
    ],

    /*
    |--------------------------------------------------------------------------
    | Upload constraints
    |--------------------------------------------------------------------------
    |
    | Meta exports arrive as UTF-16 tab-separated files that still carry a .csv
    | extension. Browsers therefore report them as text/plain or as a generic
    | binary stream rather than text/csv, so those MIME types must be allowed
    | or every genuine Meta export would be rejected at the door.
    |
    */

    'upload' => [
        'max_size_kb' => env('LEAD_IMPORT_MAX_SIZE_KB', 51200), // 50 MB
        'max_rows' => env('LEAD_IMPORT_MAX_ROWS', 250000),
        'allowed_extensions' => ['csv', 'txt', 'tsv'],
        'allowed_mime_types' => [
            'text/csv',
            'text/plain',
            'text/tab-separated-values',
            'application/csv',
            'application/vnd.ms-excel',
            'application/octet-stream',
        ],
        // Uploads allowed per user per decay window.
        'rate_limit' => [
            'attempts' => env('LEAD_IMPORT_RATE_LIMIT', 20),
            'decay_minutes' => env('LEAD_IMPORT_RATE_DECAY', 60),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | CSV parsing
    |--------------------------------------------------------------------------
    |
    | Encodings are probed by byte-order mark first and then by heuristic. The
    | delimiter is sniffed from the header line by scoring each candidate, so
    | a tab-separated ".csv" is handled without the user having to know.
    |
    */

    'csv' => [
        'default_encoding' => 'UTF-8',
        'supported_encodings' => ['UTF-8', 'UTF-16LE', 'UTF-16BE', 'UTF-32LE', 'UTF-32BE', 'ISO-8859-1', 'Windows-1252'],
        'delimiters' => ["\t", ',', ';', '|'],
        'default_delimiter' => ',',
        'enclosure' => '"',
        'escape' => '',
        // Rows shown on the preview screen before importing.
        'preview_rows' => 20,
        // A column with at most this many distinct values is treated as a
        // select/multiselect custom field rather than free text.
        'max_distinct_for_select' => 25,
        // Meta serialises multi-answer questions as a pipe-joined string.
        'multi_value_separator' => '|',
        // Cells beginning with these are neutralised to defuse CSV formula
        // injection when the file is later opened in Excel or Sheets.
        'formula_injection_prefixes' => ['=', '+', '-', '@', "\t", "\r"],
    ],

    /*
    |--------------------------------------------------------------------------
    | Meta identifier prefixes
    |--------------------------------------------------------------------------
    |
    | Meta prefixes every identifier it exports, including the phone number:
    | id=l:123, ad_id=ag:123, adset_id=as:123, campaign_id=c:123, form_id=f:123
    | and phone=p:+919876543210. These are stripped before any other parsing.
    |
    */

    'meta_prefixes' => ['l', 'ag', 'as', 'c', 'f', 'p'],

    /*
    |--------------------------------------------------------------------------
    | Phone normalisation
    |--------------------------------------------------------------------------
    |
    | Real Meta exports contain concatenated numbers ("+9196362378507976709545"),
    | numbers with embedded whitespace and numbers that are simply too short.
    | Rather than discarding the row we salvage the first plausible subscriber
    | number and flag it for review, so no lead is silently lost.
    |
    */

    'phone' => [
        'default_country_code' => env('LEAD_PHONE_COUNTRY_CODE', '91'),
        'national_number_length' => 10,
        // Valid Indian mobile numbers begin with 6, 7, 8 or 9.
        'national_number_pattern' => '/^[6-9]\d{9}$/',
        // Digit counts above the expected length that we still attempt to salvage.
        'max_salvage_digits' => 30,
        'store_format' => '+%s%s', // country code, national number
    ],

    /*
    |--------------------------------------------------------------------------
    | Display
    |--------------------------------------------------------------------------
    |
    | Meta stamps created_time in the ad account's timezone (US Pacific for this
    | account). Values are persisted as UTC and rendered in the clinic timezone.
    |
    */

    'display' => [
        'timezone' => env('LEAD_DISPLAY_TIMEZONE', 'Asia/Kolkata'),
        'datetime_format' => 'd M Y, h:i A',
    ],

    /*
    |--------------------------------------------------------------------------
    | Import execution
    |--------------------------------------------------------------------------
    |
    | Files smaller than the sync threshold are imported by the coordinator job
    | itself, which finishes in a second or two and avoids the bookkeeping of a
    | batch. Larger files fan out into chunk jobs that each seek to their own
    | offset, so memory stays flat regardless of file size.
    |
    */

    'import' => [
        'queue' => env('LEAD_IMPORT_QUEUE', 'lead-imports'),
        'connection' => env('LEAD_IMPORT_QUEUE_CONNECTION', null),
        'chunk_size' => env('LEAD_IMPORT_CHUNK_SIZE', 500),
        'sync_threshold' => env('LEAD_IMPORT_SYNC_THRESHOLD', 2000),
        'job_timeout' => 1800,
        'job_tries' => 3,
        'job_backoff' => [30, 120, 300],
        // Unknown columns automatically become custom fields when true.
        'auto_create_custom_fields' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Automatic column mapping
    |--------------------------------------------------------------------------
    |
    | A header is matched against CrmLeadField aliases first, then against the
    | labels of custom fields that already exist. The second pass is what stops
    | "what_is_your_main_skin_concern?" and "..._right_now?" from fragmenting
    | into two unrelated fields.
    |
    */

    'auto_mapping' => [
        'similarity_threshold' => 82.0,
        'custom_field_similarity_threshold' => 78.0,
        'max_suggestions' => 3,
    ],

    /*
    |--------------------------------------------------------------------------
    | Duplicate handling defaults
    |--------------------------------------------------------------------------
    |
    | Meta's own lead id is globally unique and present on every export row,
    | which makes it the most reliable match key. Phone is the sensible
    | secondary for manually assembled sheets that have no lead id.
    |
    */

    'duplicates' => [
        'default_match_fields' => ['fb_lead_id'],
        'available_match_fields' => ['fb_lead_id', 'phone', 'email'],
        'default_strategy' => 'skip',
        // Also look for an existing patient in the users table and link it.
        'match_existing_patients' => true,
    ],

];
