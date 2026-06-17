<?php

// INFO: Mpdf Configration
$defaultConfig = (new \Mpdf\Config\ConfigVariables())->getDefaults();
$fontDirs = $defaultConfig['fontDir'];
$defaultFontConfig = (new \Mpdf\Config\FontVariables())->getDefaults();
$fontData = $defaultFontConfig['fontdata'];

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
    'assessment_image_order_5' => ['red', 'subsurface_polarized', 'surface_polarized', 'white', 'woods_uv'],
    'assessment_image_order' => ['white', 'positive', 'negative', 'blue', 'uv', 'woods'],
    'mpdf_config' => [
        'margin_left' => 15,
        'margin_right' => 15,
        'margin_top' => 15,
        'margin_bottom' => 35,
        'margin_header' => 0,
        'margin_footer' => 0,
        'format' => [215.9, 279.4],
        'tempDir' => storage_path('app/public/tmp'),
        'fontDir' => array_merge($fontDirs, [
        base_path('public/assets/fonts'),
        ]),
        'fontdata' => $fontData + [
            'montserrat' => [
                'R' => 'Montserrat-Regular.ttf',
            ],
            'montserratbold' => [
                'R' => 'Montserrat-Bold.ttf',
            ],
            'montserratlight' => [
                'R' => 'Montserrat-ExtraLight.ttf',
            ],
            'montserratmedium' => [
                'R' => 'Montserrat-Medium.ttf',
            ],
        ],
    ],
    'mpdf_font_dir' => '/assets/fonts/Montserrat',

    'python_path' => env('PYTHON_PATH', 'C:\\laragon\\bin\\python\\python-3.13\\python.exe'),

    'roles' => [
        'super_admin' => 'super_admin',
        'therapist' => 'therapist',
        'clinic_manager' => 'clinic_manager',
        'client' => 'client',
        'clinic_head' => 'clinic_head',
    ],

    'company_state_code' => env('COMPANY_STATE_CODE', 'MH'),

    'hsn_sac_codes' => [
        'product' => env('DEFAULT_HSN_PRODUCT', '999729'),
        'service' => env('DEFAULT_HSN_SERVICE', '330499'),
        'iv_product' => env('DEFAULT_HSN_IV_PRODUCT', '330499'),
    ],

    'indian_states' => [
        // 'AN' => 'Andaman & Nicobar',
        // 'AP' => 'Andhra Pradesh',
        // 'AR' => 'Arunachal Pradesh',
        // 'AS' => 'Assam',
        // 'BR' => 'Bihar',
        // 'CH' => 'Chandigarh',
        // 'CT' => 'Chhattisgarh',
        // 'DL' => 'Delhi',
        // 'DN' => 'Dadra & Nagar Haveli and Daman & Diu',
        // 'GA' => 'Goa',
        // 'GJ' => 'Gujarat',
        // 'HR' => 'Haryana',
        // 'HP' => 'Himachal Pradesh',
        // 'JK' => 'Jammu & Kashmir',
        // 'JH' => 'Jharkhand',
        // 'KA' => 'Karnataka',
        // 'KL' => 'Kerala',
        // 'LA' => 'Ladakh',
        // 'MP' => 'Madhya Pradesh',
        'MH' => 'Maharashtra',
        // 'MN' => 'Manipur',
        // 'ML' => 'Meghalaya',
        // 'MZ' => 'Mizoram',
        // 'NL' => 'Nagaland',
        // 'OD' => 'Odisha',
        // 'PY' => 'Puducherry',
        // 'PB' => 'Punjab',
        'RJ' => 'Rajasthan',
        // 'SK' => 'Sikkim',
        // 'TN' => 'Tamil Nadu',
        // 'TS' => 'Telangana',
        // 'TR' => 'Tripura',
        // 'UP' => 'Uttar Pradesh',
        // 'UK' => 'Uttarakhand',
        // 'WB' => 'West Bengal',
    ],
];
