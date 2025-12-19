<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Default Firebase project
    |--------------------------------------------------------------------------
    */
    'default' => env('FIREBASE_PROJECT', 'app'),

    /*
    |--------------------------------------------------------------------------
    | Firebase project configurations
    |--------------------------------------------------------------------------
    */
    'projects' => [
        'app' => [
            /*
            |--------------------------------------------------------------------------
            | Credentials / Service Account
            |--------------------------------------------------------------------------
            |
            | You can use a JSON file or JSON string containing the service account
            | credentials. If you use a file, the path should be absolute or relative
            | to the project root.
            |
            */
            'credentials' => [
                'file' => env('FIREBASE_CREDENTIALS', storage_path('app/firebase-credentials.json')),
                // OR use auto-discovery from Google Cloud environment:
                // 'auto_discovery' => true,
            ],

            /*
            |--------------------------------------------------------------------------
            | Firebase Auth Component
            |--------------------------------------------------------------------------
            */
            'auth' => [
                'tenant_id' => env('FIREBASE_AUTH_TENANT_ID'),
            ],

            /*
            |--------------------------------------------------------------------------
            | Firebase Realtime Database
            |--------------------------------------------------------------------------
            */
            'database' => [
                'url' => env('FIREBASE_DATABASE_URL'),
            ],

            /*
            |--------------------------------------------------------------------------
            | Firebase Dynamic Links
            |--------------------------------------------------------------------------
            */
            'dynamic_links' => [
                'default_domain' => env('FIREBASE_DYNAMIC_LINKS_DEFAULT_DOMAIN'),
            ],

            /*
            |--------------------------------------------------------------------------
            | Firebase Cloud Storage
            |--------------------------------------------------------------------------
            */
            'storage' => [
                'default_bucket' => env('FIREBASE_STORAGE_DEFAULT_BUCKET'),
            ],

            /*
            |--------------------------------------------------------------------------
            | Caching
            |--------------------------------------------------------------------------
            |
            | The Firebase SDK caches values by default. You can configure the cache
            | here. Set 'enable' to false to disable caching.
            |
            */
            'cache' => [
                'enable' => true,
                'store' => env('FIREBASE_CACHE_STORE', 'file'),
            ],

            /*
            |--------------------------------------------------------------------------
            | Logging
            |--------------------------------------------------------------------------
            */
            'logging' => [
                'http_log_channel' => env('FIREBASE_HTTP_LOG_CHANNEL'),
                'http_debug_log_channel' => env('FIREBASE_HTTP_DEBUG_LOG_CHANNEL'),
            ],

            /*
            |--------------------------------------------------------------------------
            | HTTP Client Options
            |--------------------------------------------------------------------------
            */
            'http_client_options' => [
                'timeout' => 30,
                'proxy' => env('FIREBASE_HTTP_PROXY'),
            ],
        ],
    ],
];
