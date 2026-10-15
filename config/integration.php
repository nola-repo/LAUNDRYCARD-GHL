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
        'scopes' => preg_split('/[\\s,]+/', trim((string) env('GHL_SCOPES', 'contacts.readonly contacts.write locations.readonly')), -1, PREG_SPLIT_NO_EMPTY),
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
        'schedule_every_minutes' => (int) env('INTEGRATION_DISCOVERY_EVERY_MINUTES', 15),
        'enrich_with_account_detail' => (bool) env('INTEGRATION_ENRICH_ACCOUNT', true),
    ],

    'cron_secret' => env('CRON_SECRET'),

    'sync' => [
        // Real contact writes remain off until credentials and routing have been verified.
        'enabled' => (bool) env('INTEGRATION_CONTACT_SYNC_ENABLED', false),
        'tries' => 5,
        'backoff' => [30, 120, 300, 900],
        'dispatch_batch' => 200,
    ],

    // Explicit business-owned associations. A mapping is activated only after
    // OAuth registers the exact configured GHL location ID as an active install.
    // Additional branches can be added through INTEGRATION_BRANCH_LOCATION_MAP.
    'branch_location_map' => json_decode((string) env(
        'INTEGRATION_BRANCH_LOCATION_MAP',
        '{"0160":"zO81pBadpY7BUJSTFCoK","0679":"dzUCF0u2dwwoPOv9ETts","0705":"6l0o4cYCJ5ypj703UmfP","1153":"UuKeDdAVItve7Ly1Onj2","1179":"TFPr3itB8z2HhyEC3Kyp"}'
    ), true) ?: [],
];
