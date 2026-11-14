<?php

return [
    // Effective only for email logins in APP_ENV=staging; disabled by default.
    'fixed_login_otp_enabled' => env('STAGING_FIXED_LOGIN_OTP_ENABLED', false),
    'otp_allowed_emails' => array_values(array_filter(explode(',', env('STAGING_OTP_ALLOWED_EMAILS', '')))),
    'signed_photo_urls' => env('STAGING_SIGNED_PHOTO_URLS', false),
];
