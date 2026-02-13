<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Application Name
    |--------------------------------------------------------------------------
    */

    'name' => env('APP_NAME', 'Party Planner'),

    /*
    |--------------------------------------------------------------------------
    | Currency Settings
    |--------------------------------------------------------------------------
    */

    'currency' => [
        'code' => env('CURRENCY_CODE', 'XAF'),
        'symbol' => env('CURRENCY_SYMBOL', 'FCFA'),
        'decimal_places' => 0,
        'thousand_separator' => ' ',
        'decimal_separator' => ',',
    ],

    /*
    |--------------------------------------------------------------------------
    | Subscription Plans
    |--------------------------------------------------------------------------
    */

    'plans' => [
        'starter' => [
            'name' => 'Starter',
            'description' => 'Idéal pour les petits événements',
            'base_price' => env('PLAN_STARTER_PRICE', 5000),
            'included_guests' => env('PLAN_STARTER_GUESTS', 50),
            'price_per_extra_guest' => env('PLAN_STARTER_EXTRA_GUEST_PRICE', 50),
            'max_collaborators' => env('PLAN_STARTER_MAX_COLLABORATORS', 2),
            'features' => [
                'guest_management' => true,
                'task_management' => true,
                'budget_tracking' => true,
                'digital_invitations' => true,
                'photo_gallery' => true,
                'pdf_export' => false,
                'priority_support' => false,
                'custom_themes' => false,
            ],
        ],
        'pro' => [
            'name' => 'Pro',
            'description' => 'Pour les événements professionnels',
            'base_price' => env('PLAN_PRO_PRICE', 15000),
            'included_guests' => env('PLAN_PRO_GUESTS', 200),
            'price_per_extra_guest' => env('PLAN_PRO_EXTRA_GUEST_PRICE', 30),
            'max_collaborators' => env('PLAN_PRO_MAX_COLLABORATORS', PHP_INT_MAX),
            'features' => [
                'guest_management' => true,
                'task_management' => true,
                'budget_tracking' => true,
                'digital_invitations' => true,
                'photo_gallery' => true,
                'pdf_export' => true,
                'priority_support' => true,
                'custom_themes' => true,
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Free Tier Limits
    |--------------------------------------------------------------------------
    */

    'free_tier' => [
        'max_guests' => env('FREE_TIER_MAX_GUESTS', 10),
        'max_collaborators' => env('FREE_TIER_MAX_COLLABORATORS', 1),
        'max_events' => env('FREE_TIER_MAX_EVENTS', 1),
        'max_photos' => env('FREE_TIER_MAX_PHOTOS', 5),
    ],

    /*
    |--------------------------------------------------------------------------
    | Payment Settings
    |--------------------------------------------------------------------------
    */

    'payments' => [
        'mtn_mobile_money' => [
            'enabled' => env('MTN_ENABLED', false),
            'simulate' => env('MTN_SIMULATE', false), // Set to true for local dev without real API
            'name' => 'MTN Mobile Money',
            'api_user' => env('MTN_API_USER'),      // UUID created in sandbox portal
            'api_key' => env('MTN_API_KEY'),        // Key generated for the API User
            'subscription_key' => env('MTN_SUBSCRIPTION_KEY'), // Ocp-Apim-Subscription-Key
            'callback_url' => env('MTN_CALLBACK_URL'),
            'environment' => env('MTN_ENVIRONMENT', 'sandbox'), // sandbox, mtncongo, mtncameroon, etc.
            'currency' => env('MTN_CURRENCY', 'XAF'), // EUR for sandbox, XAF/XOF for production
            // HTTP client configuration for momo-api (Symfony HttpClient)
            'http' => [
                'timeout' => env('MTN_HTTP_TIMEOUT', 30),
                'verify_ssl' => env('MTN_HTTP_VERIFY_SSL', true), // false only in sandbox!
            ],
        ],
        'airtel_money' => [
            'enabled' => env('AIRTEL_ENABLED', false),
            'name' => 'Airtel Money',
            'api_url' => env('AIRTEL_API_URL', 'https://openapi.airtel.africa'),
            'client_id' => env('AIRTEL_CLIENT_ID'),
            'client_secret' => env('AIRTEL_CLIENT_SECRET'),
            'callback_url' => env('AIRTEL_CALLBACK_URL'),
            'environment' => env('AIRTEL_ENVIRONMENT', 'sandbox'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | File Upload Settings
    |--------------------------------------------------------------------------
    */

    'uploads' => [
        'photos' => [
            'max_size' => env('PHOTO_MAX_SIZE', 10240), // in KB (10MB)
            'max_per_upload' => env('PHOTO_MAX_PER_UPLOAD', 10),
            'allowed_types' => ['jpeg', 'jpg', 'png', 'gif', 'webp'],
            'thumbnail_width' => 300,
            'thumbnail_height' => 200,
        ],
        'csv' => [
            'max_size' => env('CSV_MAX_SIZE', 2048), // in KB (2MB)
            'allowed_types' => ['csv', 'txt'],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Notification Settings
    |--------------------------------------------------------------------------
    */

    'notifications' => [
        'channels' => ['email', 'database'],
        'reminders' => [
            'event_days_before' => [7, 3, 1], // Send reminders X days before event
            'task_days_before' => [3, 1], // Send reminders X days before task due date
            'rsvp_days_after' => [7, 14], // Send RSVP reminders X days after invitation sent
        ],
        'budget_alert_threshold' => env('BUDGET_ALERT_THRESHOLD', 90), // Alert when X% of budget is reached
    ],

    /*
    |--------------------------------------------------------------------------
    | Invitation Settings
    |--------------------------------------------------------------------------
    */

    'invitations' => [
        'token_length' => 32,
        'default_template' => 'classic',
        'templates' => [
            'classic' => [
                'name' => 'Classique',
                'description' => 'Design élégant et intemporel',
            ],
            'modern' => [
                'name' => 'Moderne',
                'description' => 'Design minimaliste et contemporain',
            ],
            'elegant' => [
                'name' => 'Élégant',
                'description' => 'Design sophistiqué avec dorures',
            ],
            'fun' => [
                'name' => 'Fun',
                'description' => 'Design coloré et festif',
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Event Settings
    |--------------------------------------------------------------------------
    */

    'events' => [
        'max_title_length' => 255,
        'max_description_length' => 5000,
        'allow_past_dates' => false, // For editing existing events
        'default_status' => 'upcoming',
    ],

    /*
    |--------------------------------------------------------------------------
    | Authentication Settings
    |--------------------------------------------------------------------------
    |
    | Configure refresh token behaviour (lifetime & idle timeout).
    |
    */

    'auth' => [
        // Number of days a refresh token remains valid (absolute lifetime) when "remember me" is checked
        'refresh_token_ttl_days' => env('REFRESH_TOKEN_TTL_DAYS', 30),

        // Number of days a refresh token remains valid when "remember me" is NOT checked (short session)
        'refresh_token_ttl_days_short' => env('REFRESH_TOKEN_TTL_DAYS_SHORT', 1),

        // Number of minutes of inactivity after which the refresh token is considered idle-expired.
        // Set to null to disable idle timeout and rely only on absolute TTL.
        'refresh_token_idle_timeout_minutes' => env('REFRESH_TOKEN_IDLE_TIMEOUT_MINUTES', 60 * 24 * 7), // 7 days
    ],

    /*
    |--------------------------------------------------------------------------
    | Export Settings
    |--------------------------------------------------------------------------
    */

    'exports' => [
        'pdf' => [
            'paper_size' => 'a4',
            'orientation' => 'portrait',
        ],
        'guests' => [
            'formats' => ['csv', 'xlsx', 'pdf'],
        ],
        'budget' => [
            'formats' => ['pdf', 'xlsx'],
        ],
    ],

];
