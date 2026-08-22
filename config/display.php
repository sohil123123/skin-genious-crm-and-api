<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Date and time presentation
    |--------------------------------------------------------------------------
    |
    | One place decides how a date reads everywhere in the CRM. These are only
    | the fallbacks: the running values live in the settings table under the
    | "display" group and are edited from the Settings screen, so changing the
    | house style does not need a deploy.
    |
    | Read them through the app_date_format(), app_datetime_format(),
    | app_time_format() and app_timezone() helpers rather than reaching for
    | config() directly — the helpers are what apply the settings override.
    |
    */

    'date_format' => env('DISPLAY_DATE_FORMAT', 'd M Y'),
    'datetime_format' => env('DISPLAY_DATETIME_FORMAT', 'd M Y, h:i A'),
    'time_format' => env('DISPLAY_TIME_FORMAT', 'h:i A'),

    /*
    | Only applied where a value is genuinely stored as UTC — the lead and Meta
    | modules. Appointments and sessions store naive local datetimes, so
    | converting those would shift every one of them.
    */

    'timezone' => env('DISPLAY_TIMEZONE', 'Asia/Kolkata'),

];
