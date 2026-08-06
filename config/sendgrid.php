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
    | When live_mode is false (default), emails will be redirected to this
    | email address instead of the actual recipients.
    |
    | If no test recipient is configured and live_mode is false, emails will
    | be silently dropped (not sent) and a debug log entry will be created.
    |
    */
    'test_recipient' => env('SENDGRID_TEST_RECIPIENT'),
];
