<?php

return [
    'max_upload_bytes' => 10 * 1024 * 1024,
    'family_quota_bytes' => 100 * 1024 * 1024,
    'clamav_host' => env('CLAMAV_HOST', '127.0.0.1'),
    'clamav_port' => (int) env('CLAMAV_PORT', 3310),
    'demo_enabled' => (bool) env('MEMORIA_DEMO', false),
];
