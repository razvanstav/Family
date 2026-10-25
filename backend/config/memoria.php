<?php

return [
    'admin_emails' => array_values(array_filter(array_map(fn (string $email): string => strtolower(trim($email)), explode(',', (string) env('APP_ADMIN_EMAILS', ''))))),
    'max_upload_bytes' => 10 * 1024 * 1024,
    'family_quota_bytes' => 100 * 1024 * 1024,
    'upload_daily_ip_bytes' => (int) env('MEDIA_UPLOAD_DAILY_IP_BYTES', 200 * 1024 * 1024),
    'media_min_free_bytes' => (int) env('MEDIA_MIN_FREE_BYTES', 1024 * 1024 * 1024),
    'media_budget_cache_store' => env('MEDIA_BUDGET_CACHE_STORE', 'file'),
    'clamav_host' => env('CLAMAV_HOST', '127.0.0.1'),
    'clamav_port' => (int) env('CLAMAV_PORT', 3310),
    'demo_enabled' => (bool) env('MEMORIA_DEMO', false),
];
