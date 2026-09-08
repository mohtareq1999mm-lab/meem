<?php

return [
    'anchor' => env('CURRENCY_RATE_ANCHOR', 'USD'),
    'enabled' => filter_var(env('CURRENCY_RATE_SYNC_ENABLED', false), FILTER_VALIDATE_BOOLEAN),

    'provider' => [
        'name' => env('CURRENCY_RATE_PROVIDER', 'exchange_rate_api'),
        'base_url' => env('EXCHANGE_RATE_API_BASE_URL', 'https://v6.exchangerate-api.com/v6'),
        'api_key' => env('EXCHANGE_RATE_API_KEY'),
        'timeout' => (int) env('CURRENCY_RATE_TIMEOUT', 10),
        'connect_timeout' => (int) env('CURRENCY_RATE_CONNECT_TIMEOUT', 3),
        'retries' => (int) env('CURRENCY_RATE_RETRIES', 2),
        'retry_sleep_ms' => (int) env('CURRENCY_RATE_RETRY_SLEEP_MS', 500),
    ],

    'sync' => [
        'interval_hours' => 6,
        'lock_ttl' => (int) env('CURRENCY_RATE_LOCK_TTL', 3600),
        'stale_after_hours' => (int) env('CURRENCY_RATE_STALE_AFTER_HOURS', 12),
        'provider_data_max_age_hours' => (int) env('CURRENCY_RATE_PROVIDER_MAX_AGE_HOURS', 72),
    ],

    'validation' => [
        'precision' => 10,
        'max_rate_change_percent' => (string) env('CURRENCY_RATE_MAX_CHANGE_PERCENT', '30'),
        'hard_max_rate' => (string) env('CURRENCY_RATE_HARD_MAX', '1000000'),
    ],
];
