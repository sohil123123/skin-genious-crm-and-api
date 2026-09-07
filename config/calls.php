<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Unified call management
|--------------------------------------------------------------------------
|
| One configuration file for both call providers, because the CRM deliberately
| does not care which one a call came from. Everything provider-shaped is
| namespaced under its provider key so a third provider is a new block here
| plus an adapter class, not a change to the schema or to any CRM feature.
|
| Credentials are fallbacks only. The running values live in the settings
| table and are edited on the Call Settings page, matching how the Meta and
| WhatsApp integrations already store theirs — there is one way credentials
| are stored in this application, not three.
|
*/

return [

    /*
    |--------------------------------------------------------------------------
    | Phone normalisation
    |--------------------------------------------------------------------------
    |
    | Deliberately more permissive than the lead importer's rules. A lead's
    | phone must look like a reachable Indian mobile or it is worthless; a call
    | leg may legitimately be an Exophone, a landline, a toll-free number or an
    | international caller, and refusing to normalise those would lose real
    | calls. Matching against leads and patients still goes through the lead
    | importer's match key, so "the same person" means the same thing here as
    | it does everywhere else in the CRM.
    |
    */

    'phone' => [
        'default_country_code' => env('CALL_PHONE_COUNTRY_CODE', '91'),
        'national_number_length' => (int) env('CALL_PHONE_NATIONAL_LENGTH', 10),
        // Digits kept when building the key two numbers are compared on.
        'match_key_length' => (int) env('CALL_PHONE_MATCH_KEY_LENGTH', 10),
        'store_format' => '+%s%s',
    ],

    /*
    |--------------------------------------------------------------------------
    | Lead and patient matching
    |--------------------------------------------------------------------------
    |
    | A call whose number matches more than one record is marked ambiguous and
    | left for a human rather than attached to a guess — attaching a call to
    | the wrong patient writes someone else's conversation into their file.
    |
    */

    'matching' => [
        // Attach to a patient/lead automatically when exactly one matches.
        'auto_attach' => (bool) env('CALL_MATCH_AUTO_ATTACH', true),
        // Never create a patient or lead from a call. An unknown caller is a
        // fact worth seeing, not a reason to manufacture a duplicate record.
        'create_missing_customer' => false,
        // Prefer a patient over a lead when the same number is both, because a
        // lead who became a patient is the patient from then on.
        'prefer' => env('CALL_MATCH_PREFER', 'customer'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Recording storage
    |--------------------------------------------------------------------------
    |
    | Recordings are customer conversations, so the default disk is the private
    | one. Nothing in this system ever writes a recording to a public disk or
    | hands out a permanent URL; playback goes through a short-lived signed
    | route that checks the call policy first.
    |
    */

    'recording' => [
        'enabled' => (bool) env('CALL_RECORDING_DOWNLOAD_ENABLED', true),
        'disk' => env('CALL_RECORDING_DISK', 'call_recordings'),
        // {year}, {month} and {uuid} are substituted per call.
        'path_template' => env('CALL_RECORDING_PATH', '{year}/{month}/{uuid}'),
        'max_bytes' => (int) env('CALL_RECORDING_MAX_BYTES', 104857600), // 100 MB
        'timeout' => (int) env('CALL_RECORDING_TIMEOUT', 120),
        'connect_timeout' => (int) env('CALL_RECORDING_CONNECT_TIMEOUT', 15),
        // Signed playback URL lifetime, in minutes.
        'signed_url_ttl' => (int) env('CALL_RECORDING_SIGNED_URL_TTL', 10),
        'allowed_mime_prefixes' => ['audio/', 'video/', 'application/octet-stream'],
        'extension_map' => [
            'audio/mpeg' => 'mp3',
            'audio/mp3' => 'mp3',
            'audio/wav' => 'wav',
            'audio/x-wav' => 'wav',
            'audio/wave' => 'wav',
            'audio/ogg' => 'ogg',
            'audio/mp4' => 'm4a',
            'audio/x-m4a' => 'm4a',
            'audio/aac' => 'aac',
            'audio/amr' => 'amr',
        ],
        'default_extension' => 'mp3',
    ],

    /*
    |--------------------------------------------------------------------------
    | Transcription
    |--------------------------------------------------------------------------
    |
    | Off by default and bound to a null driver, so the whole pipeline is
    | present and testable before anyone signs up to a speech provider. Turning
    | it on is a setting, not a deployment.
    |
    */

    'transcription' => [
        'enabled' => (bool) env('CALL_TRANSCRIPTION_ENABLED', true),
        'driver' => env('CALL_TRANSCRIPTION_DRIVER', 'null'),
        // Clinic conversations here run in Gujarati, Hindi and English, often
        // in the same sentence, so no single language is assumed.
        'language' => env('CALL_TRANSCRIPTION_LANGUAGE', null),
        'model' => env('CALL_TRANSCRIPTION_MODEL', 'whisper-1'),
        // Sample text in the expected style and script. Whisper treats Hindi
        // and Urdu as interchangeable and will happily render one as the other,
        // so this is what pins the output to the script the clinic reads.
        'prompt' => env('CALL_TRANSCRIPTION_PROMPT'),
        'timeout' => (int) env('CALL_TRANSCRIPTION_TIMEOUT', 600),
        // Recordings shorter than this are almost always a ring-out or a
        // wrong number; transcribing them costs money and returns nothing.
        'min_duration_seconds' => (int) env('CALL_TRANSCRIPTION_MIN_DURATION', 5),
        'drivers' => [
            'openai' => [
                'base_url' => env('CALL_TRANSCRIPTION_OPENAI_URL', 'https://api.openai.com/v1'),
                'api_key' => env('CALL_TRANSCRIPTION_OPENAI_KEY'),
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | AI analysis
    |--------------------------------------------------------------------------
    |
    | The tables, job and status flow exist now so analysis can be switched on
    | later without touching the calls schema. No analyser ships enabled.
    |
    */

    /*
    |---------------------------------------------------------------------------
    | Screen pop
    |---------------------------------------------------------------------------
    |
    | How recently a call must have started for its card to appear on the
    | clinic's screens. This is the only thing separating a live announcement
    | from a replay: an hourly Callyzer sync returns calls up to an hour old,
    | and without a window every one of them would pop.
    |
    | Raise it and a backfill starts leaking onto reception's screens. Lower it
    | past about three minutes and genuine Callyzer calls, which arrive a minute
    | or two after the handset hangs up, stop announcing at all.
    |
    */

    'popup' => [
        'recent_minutes' => (int) env('CALL_POPUP_RECENT_MINUTES', 5),
    ],

    'analysis' => [
        'enabled' => (bool) env('CALL_ANALYSIS_ENABLED', true),
        'driver' => env('CALL_ANALYSIS_DRIVER', 'null'),
        'model' => env('CALL_ANALYSIS_MODEL', 'gpt-4o-mini'),
        // v2 tightened what appointment_booked means and let the judgement
        // booleans be null. An analysis stamped v1 answered a different
        // question, so the two are not comparable and the version is stored
        // next to every result.
        'version' => env('CALL_ANALYSIS_VERSION', 'v2'),

        /*
         * The shortest transcript worth sending to a model.
         *
         * "Hello? Wrong number." is not a sales enquiry, and asking for twenty
         * judgements about it produces twenty inventions at full price. The
         * floor is counted in whitespace-separated words, so it means the same
         * thing in Hindi as in English.
         *
         * Overridable from Call Settings, because the right number depends on
         * how a clinic answers its phone: a reception that opens with a scripted
         * greeting clears fifteen words before anyone has said anything.
         */
        'min_words' => (int) env('CALL_ANALYSIS_MIN_WORDS', 15),
    ],

    /*
    |--------------------------------------------------------------------------
    | Queues
    |--------------------------------------------------------------------------
    |
    | Recording downloads get their own queue: a 40 MB download must never sit
    | in front of the webhook processing that decides which patient a call
    | belongs to.
    |
    */

    'queue' => [
        'connection' => env('CALL_QUEUE_CONNECTION', null),
        'webhooks' => env('CALL_QUEUE_WEBHOOKS', 'call-webhooks'),
        'sync' => env('CALL_QUEUE_SYNC', 'sync'),
        'recordings' => env('CALL_QUEUE_RECORDINGS', 'call-recordings'),
        'transcription' => env('CALL_QUEUE_TRANSCRIPTION', 'call-transcription'),
        'analysis' => env('CALL_QUEUE_ANALYSIS', 'call-analysis'),
        'tries' => (int) env('CALL_JOB_TRIES', 5),
        'timeout' => (int) env('CALL_JOB_TIMEOUT', 180),
        // Long-tailed: the usual causes of failure are a provider outage or a
        // recording that is not published yet, neither of which clears in
        // seconds.
        'backoff' => [30, 120, 300, 900, 1800],
    ],

    /*
    |--------------------------------------------------------------------------
    | Retention
    |--------------------------------------------------------------------------
    |
    | Null means keep forever. Nothing is ever deleted unless a value is set
    | here AND the prune command is scheduled — deleting a customer
    | conversation must be a decision somebody made on purpose.
    |
    */

    'retention' => [
        'recording_days' => env('CALL_RECORDING_RETENTION_DAYS', 90),
        'transcript_days' => env('CALL_TRANSCRIPT_RETENTION_DAYS', 90),
        'payload_days' => env('CALL_PAYLOAD_RETENTION_DAYS', 90),
        'webhook_event_days' => env('CALL_WEBHOOK_EVENT_RETENTION_DAYS', 90),
    ],

    /*
    |--------------------------------------------------------------------------
    | Callyzer — outgoing calls
    |--------------------------------------------------------------------------
    |
    | Endpoint paths and request parameter names are configuration rather than
    | constants because Callyzer has moved both between versions. The mapper
    | reads the response defensively for the same reason: an unrecognised field
    | is kept in provider_data instead of being dropped.
    |
    | Verify base_url and the endpoint paths against Connectors > API & Webhook
    | in your own Callyzer dashboard before enabling the sync.
    |
    */

    'callyzer' => [
        'enabled' => (bool) env('CALLYZER_ENABLED', true),
        'base_url' => env('CALLYZER_BASE_URL', 'https://api1.callyzer.co'),
        'endpoints' => [
            'call_history' => env('CALLYZER_CALL_HISTORY_PATH', '/admin/api/call/callHistory'),
            'call_history_by_ids' => env('CALLYZER_CALL_HISTORY_BY_IDS_PATH', '/admin/api/call/callHistoryByIds'),
        ],
        'api_token' => env('CALLYZER_API_TOKEN'),
        'webhook_secret' => env('CALLYZER_WEBHOOK_SECRET'),
        // Callyzer allows roughly one request every two seconds. Exceeding it
        // returns 429s that look exactly like an outage, so the sync paces
        // itself rather than discovering the limit.
        'rate_limit' => [
            'requests' => (int) env('CALLYZER_RATE_LIMIT_REQUESTS', 1),
            'per_seconds' => (int) env('CALLYZER_RATE_LIMIT_SECONDS', 2),
            'lock_key' => 'callyzer:api',
            // How long a request will wait for its turn before giving up and
            // letting the job retry.
            'wait_seconds' => (int) env('CALLYZER_RATE_LIMIT_WAIT', 30),
        ],
        'http' => [
            'timeout' => (int) env('CALLYZER_TIMEOUT', 30),
            'connect_timeout' => (int) env('CALLYZER_CONNECT_TIMEOUT', 10),
            'retry_times' => (int) env('CALLYZER_RETRY_TIMES', 2),
            'retry_sleep_ms' => (int) env('CALLYZER_RETRY_SLEEP', 2000),
        ],
        'sync' => [
            'enabled' => (bool) env('CALLYZER_SYNC_ENABLED', false),
            'page_size' => (int) env('CALLYZER_PAGE_SIZE', 100),
            'max_pages' => (int) env('CALLYZER_MAX_PAGES', 200),
            // How far back a scheduled incremental run looks. Generous on
            // purpose: a call edited in the Callyzer app days later comes back
            // with a new modified_at and must be picked up again.
            'lookback_hours' => (int) env('CALLYZER_LOOKBACK_HOURS', 48),
            'date_format' => env('CALLYZER_DATE_FORMAT', 'Y-m-d'),
        ],
        // Callyzer's own vocabulary, mapped to the unified enums. Anything not
        // listed lands on "unknown" and is still stored verbatim.
        'call_type_map' => [
            'outgoing' => 'outgoing',
            'incoming' => 'incoming',
            'missed' => 'missed',
            'rejected' => 'rejected',
            'not connected' => 'not_connected',
            'not_connected' => 'not_connected',
            'unknown' => 'unknown',
        ],
        'timezone' => env('CALLYZER_TIMEZONE', 'Asia/Kolkata'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Exotel — incoming calls
    |--------------------------------------------------------------------------
    |
    | Exotel authenticates outbound API calls with an API key/token pair but
    | signs nothing on its Passthru webhook, which is a plain GET. The endpoint
    | is therefore protected by a shared secret carried in the query string or
    | a header — configured here and included in the URL given to Exotel.
    |
    */

    'exotel' => [
        'enabled' => (bool) env('EXOTEL_ENABLED', true),
        'account_sid' => env('EXOTEL_ACCOUNT_SID'),
        'api_key' => env('EXOTEL_API_KEY'),
        'api_token' => env('EXOTEL_API_TOKEN'),
        // Exotel's subdomain differs by region; @api.exotel.com and
        // @api.in.exotel.com are the two in common use.
        'subdomain' => env('EXOTEL_SUBDOMAIN', 'api.exotel.com'),
        'webhook' => [
            // Empty disables the check. Leaving it empty in production means
            // anyone who guesses the URL can write calls into the CRM.
            'secret' => env('EXOTEL_WEBHOOK_SECRET'),
            'secret_query_key' => env('EXOTEL_WEBHOOK_SECRET_KEY', 'token'),
            'secret_header' => 'X-Exotel-Webhook-Secret',
            'require_secret' => (bool) env('EXOTEL_REQUIRE_WEBHOOK_SECRET', true),
        ],
        'http' => [
            'timeout' => (int) env('EXOTEL_TIMEOUT', 30),
            'connect_timeout' => (int) env('EXOTEL_CONNECT_TIMEOUT', 10),
            'retry_times' => (int) env('EXOTEL_RETRY_TIMES', 2),
            'retry_sleep_ms' => (int) env('EXOTEL_RETRY_SLEEP', 1000),
        ],
        // Exotel's CallStatus / DialCallStatus vocabulary, mapped to the
        // unified statuses.
        'status_map' => [
            'queued' => 'queued',
            'ringing' => 'ringing',
            'in-progress' => 'in_progress',
            'in_progress' => 'in_progress',
            'completed' => 'completed',
            'busy' => 'busy',
            'failed' => 'failed',
            'no-answer' => 'no_answer',
            'no_answer' => 'no_answer',
            'canceled' => 'cancelled',
            'cancelled' => 'cancelled',
        ],
        'timezone' => env('EXOTEL_TIMEZONE', 'Asia/Kolkata'),
    ],

];
