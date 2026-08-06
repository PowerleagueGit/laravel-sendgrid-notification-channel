<?php

return [
    /*
    |--------------------------------------------------------------------------
    | SendGrid API Key
    |--------------------------------------------------------------------------
    |
    | Your SendGrid API key for authentication.
    |
    */
    'api_key' => env('SENDGRID_API_KEY'),

    /*
    |--------------------------------------------------------------------------
    | Live Mode
    |--------------------------------------------------------------------------
    |
    | By default, emails are sent to the test_recipient address for safety.
    | Set SENDGRID_LIVE_MODE=true to send emails to actual recipients.
    |
    | This prevents accidental emails to real users in development/staging.
    |
    */
    'live_mode' => env('SENDGRID_LIVE_MODE', false),

    /*
    |--------------------------------------------------------------------------
    | Test Recipient
    |--------------------------------------------------------------------------
    |
    | When live_mode is false (default), ALL emails will be redirected to
    | this email address instead of the actual recipients.
    |
    | You MUST set SENDGRID_TEST_RECIPIENT when not in live mode, otherwise
    | an exception will be thrown to prevent silent failures.
    |
    */
    'test_recipient' => env('SENDGRID_TEST_RECIPIENT'),
];
