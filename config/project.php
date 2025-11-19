<?php
return [
    'frontend_url' => env('FRONTEND_URL', 'https://aiaesthetics.cbphysiotherapy.in'),
    'mysql_ucwords' => 'CONCAT(UCASE(LEFT(name, 1)), LCASE(SUBSTRING(name, 2)))',
    'mysql_user_ucwords' => "TRIM(
                        CONCAT(
                            UCASE(LEFT(first_name, 1)), LCASE(SUBSTRING(first_name, 2)),
                            ' ',
                            IFNULL(CONCAT(UCASE(LEFT(last_name, 1)), LCASE(SUBSTRING(last_name, 2))), '')
                        )
                    )",
];
