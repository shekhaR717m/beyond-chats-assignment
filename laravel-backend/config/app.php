<?php

return [
    'name' => env('APP_NAME', 'Beyond Chats Scraper'),
    'env' => env('APP_ENV', 'production'),
    'debug' => env('APP_DEBUG', false),
    'url' => env('APP_URL', 'http://localhost'),

    'timezone' => 'UTC',
    'locale' => 'en',
    'fallback_locale' => 'en',

    'key' => env('APP_KEY'),
    'cipher' => 'AES-256-CBC',

    'providers' => [],

    'scraper_timeout' => env('SCRAPER_TIMEOUT', 30),
    'scraper_max_retries' => env('SCRAPER_MAX_RETRIES', 3),
];
