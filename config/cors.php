<?php

return [
    'paths' => ['api/*', 'sanctum/csrf-cookie', 'storage/*'],
    'allowed_methods'   => ['*'],
    'allowed_origins'   => ['http://localhost:9000', 'http://skin-genious-assessment.test:9000', 'https://aiaesthetics.cbphysiotherapy.in'],
    'allowed_origins_patterns' => [
        '/^https?:\/\/([a-z0-9-]+\.)?annaponsprojects\.com$/',
    ],
    'allowed_headers'   => ['*'],
    'exposed_headers'   => [],
    'max_age'           => 3600,
    'supports_credentials' => false,
];

