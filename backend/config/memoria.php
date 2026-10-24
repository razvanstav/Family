<?php

return [
    'admin_emails' => array_values(array_filter(array_map(fn (string $email): string => strtolower(trim($email)), explode(',', (string) env('APP_ADMIN_EMAILS', ''))))),
    'max_upload_bytes' => 10 * 1024 * 1024,
    'family_quota_bytes' => 100 * 1024 * 1024,
    'clamav_host' => env('CLAMAV_HOST', '127.0.0.1'),
    'clamav_port' => (int) env('CLAMAV_PORT', 3310),
    'demo_enabled' => (bool) env('MEMORIA_DEMO', false),
];
