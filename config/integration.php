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
        // Global POS "MachNo" fallback. Per-store overrides take priority.
        // Pattern: LAUNDRYCARD_POS_MACH_NO_<STORE_ID> e.g. LAUNDRYCARD_POS_MACH_NO_0160
        // Without a machine number only the email on the Cards endpoint is available.
        'pos_mach_no' => env('LAUNDRYCARD_POS_MACH_NO'),
        // Per-store machine numbers. Set via env LAUNDRYCARD_POS_MACH_NO_<STOREID>.
        // These are resolved at runtime by LaundryCardClient::machNoForStore().
        'pos_mach_nos' => array_filter([
            '0160' => env('LAUNDRYCARD_POS_MACH_NO_0160'),
            '0679' => env('LAUNDRYCARD_POS_MACH_NO_0679'),
            '0705' => env('LAUNDRYCARD_POS_MACH_NO_0705'),
            '1153' => env('LAUNDRYCARD_POS_MACH_NO_1153'),
            '1179' => env('LAUNDRYCARD_POS_MACH_NO_1179'),
        ]),
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
        'client_id' => trim((string) env('GHL_CLIENT_ID'), "\xEF\xBB\xBF \t\n\r\0\x0B"),
        'client_secret' => trim((string) env('GHL_CLIENT_SECRET'), "\xEF\xBB\xBF \t\n\r\0\x0B"),
        'redirect_uri' => trim((string) env('GHL_REDIRECT_URI'), "\xEF\xBB\xBF \t\n\r\0\x0B"),
        // Required by the agency bulk-install discovery endpoint. If omitted,
        // the OAuth service derives it from the standard appId-clientKey ID.
        'app_id' => trim((string) env('GHL_APP_ID'), "\xEF\xBB\xBF \t\n\r\0\x0B"),
        'authorize_url' => env('GHL_AUTHORIZE_URL', 'https://marketplace.leadconnectorhq.com/v2/oauth/chooselocation'),
        'version_id' => env('GHL_VERSION_ID'),
        'token_url' => env('GHL_TOKEN_URL', 'https://services.leadconnectorhq.com/oauth/token'),
        'api_base' => env('GHL_API_BASE', 'https://services.leadconnectorhq.com'),
        'api_version' => env('GHL_API_VERSION', '2021-07-28'),
        'scopes' => preg_split('/[\\s,]+/', trim((string) env('GHL_SCOPES', 'contacts.readonly contacts.write locations.readonly locations.customfields.readonly oauth.readonly')), -1, PREG_SPLIT_NO_EMPTY),
        'timeout' => (int) env('GHL_TIMEOUT', 20),
        'state_ttl_seconds' => 600,
        // Refresh when fewer than this many seconds remain on a live API call.
        'refresh_leeway' => (int) env('GHL_REFRESH_LEEWAY', 300),
        // Proactive background refresh window (default 2 hours ahead of expiry).
        'proactive_refresh_window' => (int) env('GHL_PROACTIVE_REFRESH_WINDOW', 7200),
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
        'overlap_minutes' => (int) env('INTEGRATION_DISCOVERY_OVERLAP_MINUTES', 30),
        'max_lookback_hours' => (int) env('INTEGRATION_DISCOVERY_MAX_LOOKBACK_HOURS', 2),
        'max_baseline_batch' => (int) env('INTEGRATION_BASELINE_BATCH', 10000),
        'enrich_with_account_detail' => (bool) env('INTEGRATION_ENRICH_ACCOUNT', true),
    ],

    'cron_secret' => env('INTEGRATION_CRON_KEY', env('CRON_SECRET')),

    /*
    |--------------------------------------------------------------------------
    | Transaction Ingestion
    |--------------------------------------------------------------------------
    | Incremental polling of /api/transact per store. Disabled by default
    | until actual transaction field names and type codes are confirmed.
    */
    'transactions' => [
        'enabled'              => (bool) env('INTEGRATION_TRANSACTION_INGESTION_ENABLED', true),
        'window_hours'         => (int) env('INTEGRATION_TRANSACTION_WINDOW_HOURS', 1),
        'max_lookback_hours'   => (int) env('INTEGRATION_TRANSACTION_MAX_LOOKBACK_HOURS', 48),
        'overlap_minutes'      => (int) env('INTEGRATION_TRANSACTION_OVERLAP_MINUTES', 10),
        'schedule_every_minutes' => (int) env('INTEGRATION_TRANSACTION_EVERY_MINUTES', 30),
    ],

    /*
    |--------------------------------------------------------------------------
    | Contact Classification (Tags & Custom Fields)
    |--------------------------------------------------------------------------
    | Thresholds are configurable — do not embed business assumptions in code.
    | GHL custom field UUIDs are per-location and must be set before use.
    */
    'classification' => [
        // Balance tier thresholds (dollar amounts, inclusive lower bound)
        'balance_low_max'  => (float) env('INTEGRATION_BALANCE_LOW_MAX', 10.00),
        'balance_high_min' => (float) env('INTEGRATION_BALANCE_HIGH_MIN', 25.00),
        // GHL custom field IDs — location-keyed JSON map.
        // e.g. {"zO81pBadpY7BUJSTFCoK":{"card_number":"UUID","balance":"UUID","store_id":"UUID"}}
        'custom_fields'    => json_decode((string) env('INTEGRATION_GHL_CUSTOM_FIELDS', '{}'), true) ?: [],
    ],

    'sync' => [
        // Real contact writes enabled for live GHL location synchronization.
        'enabled' => (bool) env('INTEGRATION_CONTACT_SYNC_ENABLED', true),
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
