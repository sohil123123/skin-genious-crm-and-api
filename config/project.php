<?php
return [
    'openai_api_key' => env('OPENAI_API_KEY', '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'),
    'appointment_consult_duration' => env('APPOINTMENT_CONSULT_DURATION', 90),
    'working_hours_per_day' => env('WORKING_HOURS_PER_DAY', 8),
    'pending_limit' => env('PENDING_LIMIT', 2),
    'frontend_url' => env('FRONTEND_URL', 'https://aiaesthetics.cbphysiotherapy.in'),
    'mysql_ucwords' => 'CONCAT(UCASE(LEFT(name, 1)), LCASE(SUBSTRING(name, 2)))',
    'mysql_user_ucwords' => "TRIM(
                        CONCAT(
                            UCASE(LEFT(first_name, 1)), LCASE(SUBSTRING(first_name, 2)),
                            ' ',
                            IFNULL(CONCAT(UCASE(LEFT(last_name, 1)), LCASE(SUBSTRING(last_name, 2))), '')
                        )
                    )",

    'mpdf_config' => [
        'tempDir' => storage_path('app/mpdf'),
        'mode' => 'utf-8',
        'format' => 'A4',
        // 'margin_header' => 10,
        'margin_top' => 16,
        'margin_bottom' => 14,
        'margin_footer' => 5,
        'orientation' => 'P',
        'default_font' => 'dejavusans',
    ],
    'assessment_image_order' => ['white', 'positive', 'negative', 'blue', 'uv', 'woods']
];
