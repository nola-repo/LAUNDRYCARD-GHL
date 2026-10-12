<?php

return [

    /*
    |--------------------------------------------------------------------------
    | LaundryCard Live
    |--------------------------------------------------------------------------
    | Documented at CCI LaundryCard API Overview / CCI Point-Of-Sale API Overview.
    | Credentials come from the environment only and are never rendered in the UI.
    */
    'laundrycard' => [
        'base_url' => env('LAUNDRYCARD_BASE_URL', 'https://live.laundrycard.com'),
        'username' => env('LAUNDRYCARD_USERNAME'),
        'password' => env('LAUNDRYCARD_PASSWORD'),
        // POS "MachNo" is required by the documented Account endpoint and must
        // match a defined POS equipment at the store. Without it only the
        // email on the Cards endpoint is available.
        'pos_mach_no' => env('LAUNDRYCARD_POS_MACH_NO'),
        // The docs do not state token lifetime; we re-authenticate on 401 and
        // proactively after this many seconds.
        'token_ttl' => (int) env('LAUNDRYCARD_TOKEN_TTL', 3000),
        'timeout' => (int) env('LAUNDRYCARD_TIMEOUT', 20),
        'store_id_pad' => 4,
    ],

    /*
    |--------------------------------------------------------------------------
    | GoHighLevel (Marketplace OAuth 2.0, Authorization Code Grant)
    |--------------------------------------------------------------------------
    */
    'ghl' => [
        'client_id' => env('GHL_CLIENT_ID'),
        'client_secret' => env('GHL_CLIENT_SECRET'),
        'redirect_uri' => env('GHL_REDIRECT_URI'),
        'authorize_url' => env('GHL_AUTHORIZE_URL', 'https://marketplace.gohighlevel.com/oauth/chooselocation'),
        'token_url' => env('GHL_TOKEN_URL', 'https://services.leadconnectorhq.com/oauth/token'),
        'api_base' => env('GHL_API_BASE', 'https://services.leadconnectorhq.com'),
        'api_version' => env('GHL_API_VERSION', '2021-07-28'),
        'scopes' => array_filter(explode(' ', env('GHL_SCOPES', 'contacts.readonly contacts.write locations.readonly'))),
        'timeout' => (int) env('GHL_TIMEOUT', 20),
        'state_ttl_seconds' => 600,
        // Refresh when fewer than this many seconds remain.
        'refresh_leeway' => 300,
        'default_country_code' => env('GHL_DEFAULT_COUNTRY_CODE', '1'),
        'contact_source' => 'LaundryCard',
        'contact_tags' => ['laundrycard'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Discovery / sync behaviour
    |--------------------------------------------------------------------------
    */
    'discovery' => [
        // skip   = first scan of a store only records a baseline; nothing is imported.
        // import = first scan marks records pending (still needs a verified mapping).
        'initial_import' => env('INTEGRATION_INITIAL_IMPORT', 'skip'),
        // Records first seen after the baseline are only auto-synced when the source
        // timestamp proves they were created after the baseline; otherwise held.
        'require_timestamp_confirmation' => (bool) env('INTEGRATION_REQUIRE_TIMESTAMP_CONFIRMATION', true),
        // Tolerance when comparing source timestamp with baseline start.
        'timestamp_grace_minutes' => 10,
        'schedule_every_minutes' => (int) env('INTEGRATION_DISCOVERY_EVERY_MINUTES', 15),
        'enrich_with_account_detail' => (bool) env('INTEGRATION_ENRICH_ACCOUNT', true),
    ],

    'sync' => [
        'tries' => 5,
        'backoff' => [30, 120, 300, 900],
        'dispatch_batch' => 200,
    ],
];
