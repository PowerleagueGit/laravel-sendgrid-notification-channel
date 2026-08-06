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
    | Test Recipient Override
    |--------------------------------------------------------------------------
    |
    | When set, ALL emails sent through this channel will be redirected to
    | this email address instead of the actual recipients. This is useful
    | for testing environments where you want to prevent emails from being
    | sent to real users.
    |
    | Set the SENDGRID_TEST_RECIPIENT environment variable to enable this
    | feature. Leave it empty or unset to send to actual recipients.
    |
    */
    'test_recipient' => env('SENDGRID_TEST_RECIPIENT'),
];

