<?php

return [

    /*
    | One-time pairing codes (OTT) shown on the devices screen.
    */
    'ott_lifetime_minutes' => (int) env('DEVICE_OTT_LIFETIME_MINUTES', 10),

    // Failed /claim attempts allowed per IP before it is locked out.
    'claim_max_failures' => (int) env('DEVICE_CLAIM_MAX_FAILURES', 5),
    'claim_lockout_minutes' => (int) env('DEVICE_CLAIM_LOCKOUT_MINUTES', 15),
    'claim_per_minute' => (int) env('DEVICE_CLAIM_PER_MINUTE', 10),

    // Authenticated device API requests per minute, per device.
    'api_per_minute' => (int) env('DEVICE_API_PER_MINUTE', 120),

    // A device without any ping / request for this long is marked offline.
    'offline_after_seconds' => (int) env('DEVICE_OFFLINE_AFTER_SECONDS', 90),

    // Commands not completed within this time become "expired".
    'command_ttl_minutes' => (int) env('DEVICE_COMMAND_TTL_MINUTES', 10),

    'attendance_max_batch' => 500,
    'changes_default_limit' => 200,
    'changes_max_limit' => 500,
    'push_max_employees' => 100,

    'templates' => [
        // type => [model_version, exact byte length (null = any up to max_bytes)]
        'face_mfn_s8_v1' => ['model_version' => 'MFN_S8_V1', 'bytes' => 2048, 'max_bytes' => 2048],
        'fp_zn600' => ['model_version' => 'ZN600', 'bytes' => null, 'max_bytes' => 8192],
    ],
    'max_templates_per_type' => 3,

    /*
    | WebSocket endpoint handed to devices on claim. This is the PUBLIC address
    | of Reverb as the device sees it (may differ from REVERB_HOST, which is
    | how the app server reaches Reverb).
    */
    'ws' => [
        'host' => env('DEVICE_WS_HOST', env('REVERB_HOST', 'localhost')),
        'port' => (int) env('DEVICE_WS_PORT', env('REVERB_PORT', 8080)),
        'scheme' => env('DEVICE_WS_SCHEME', 'ws'),
        'app_key' => env('REVERB_APP_KEY'),
    ],
];
