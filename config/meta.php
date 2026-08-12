<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Graph API
    |--------------------------------------------------------------------------
    |
    | The version is pinned rather than floating, because Meta ships breaking
    | changes between versions and an unpinned call would start failing on
    | their release schedule rather than ours. v26.0 is current as of
    | 2026-07-29 and is supported until at least mid-2028.
    |
    | Note that WhatsAppService pins its own version separately; the two
    | integrations are deliberately free to move independently.
    |
    */

    'api' => [
        'version' => env('META_API_VERSION', 'v26.0'),
        'base_url' => env('META_GRAPH_URL', 'https://graph.facebook.com'),
        // Meta expects a webhook response within 20 seconds, so every Graph
        // call happens on the queue and can afford a generous timeout.
        'timeout' => (int) env('META_API_TIMEOUT', 20),
        'connect_timeout' => (int) env('META_API_CONNECT_TIMEOUT', 10),
        // Transport-level retries inside a single job attempt. Job-level
        // retries below handle everything that survives these.
        'retry_times' => (int) env('META_API_RETRY_TIMES', 3),
        'retry_sleep_ms' => (int) env('META_API_RETRY_SLEEP', 500),
    ],

    /*
    |--------------------------------------------------------------------------
    | Credentials
    |--------------------------------------------------------------------------
    |
    | These are fallbacks only. The running values live in the settings table
    | and are edited from the Meta Lead Settings page, matching how the
    | WhatsApp integration stores its credentials. Per-Page access tokens are
    | held encrypted on meta_pages, never here.
    |
    | Setting keys: meta_app_id, meta_app_secret, meta_verify_token.
    |
    */

    'credentials' => [
        'app_id' => env('META_APP_ID'),
        'app_secret' => env('META_APP_SECRET'),
        'verify_token' => env('META_VERIFY_TOKEN'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Webhook
    |--------------------------------------------------------------------------
    |
    | Meta signs every POST with an HMAC of the raw request body. Verification
    | is mandatory in production — without it the endpoint would accept a lead
    | from anyone who guessed the URL. It can be disabled locally, where the
    | Lead Ads Testing Tool is often replayed by hand without a signature.
    |
    */

    'webhook' => [
        'verify_signature' => (bool) env('META_VERIFY_SIGNATURE', true),
        'signature_header' => 'X-Hub-Signature-256',
        // Meta's leadgen notifications arrive under the "page" object.
        'object' => 'page',
        'field' => 'leadgen',
    ],

    /*
    |--------------------------------------------------------------------------
    | Queue
    |--------------------------------------------------------------------------
    |
    | Lead processing is queued so the webhook can answer in milliseconds. The
    | backoff is long-tailed because the usual cause of failure is an expired
    | Page token or a Meta outage, neither of which resolves in seconds.
    |
    */

    'queue' => [
        'name' => env('META_LEAD_QUEUE', 'meta-leads'),
        'connection' => env('META_LEAD_QUEUE_CONNECTION', null),
        'tries' => (int) env('META_LEAD_JOB_TRIES', 5),
        'backoff' => [30, 120, 300, 900],
        'timeout' => (int) env('META_LEAD_JOB_TIMEOUT', 120),
    ],

    /*
    |--------------------------------------------------------------------------
    | Lead node fields
    |--------------------------------------------------------------------------
    |
    | Requested explicitly because the Graph API returns only id and
    | created_time by default. Attribution beyond ad_id/form_id depends on the
    | token's ads permissions, so anything missing from the response is simply
    | absent rather than an error.
    |
    */

    'lead_fields' => [
        'id',
        'created_time',
        'field_data',
        'ad_id',
        'ad_name',
        'adset_id',
        'adset_name',
        'campaign_id',
        'campaign_name',
        'form_id',
        'is_organic',
        'platform',
        'partner_name',
    ],

    /*
    |--------------------------------------------------------------------------
    | Form and page name caching
    |--------------------------------------------------------------------------
    |
    | The lead node carries form_id but not the form's name, so it needs a
    | second call. Names change rarely and a busy campaign can deliver
    | hundreds of leads from one form, so the lookup is cached.
    |
    */

    'cache' => [
        'form_ttl' => (int) env('META_FORM_CACHE_TTL', 86400),
        'prefix' => 'meta_lead',
    ],

];
