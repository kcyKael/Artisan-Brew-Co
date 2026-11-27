<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'brevo' => [
        'api_key' => env('BREVO_API_KEY'),
        'base_url' => rtrim((string) env('BREVO_API_BASE_URL', 'https://api.brevo.com/v3'), '/'),
        'sender_email' => env('BREVO_SENDER_EMAIL', 'no-reply@artisanbrewco.local'),
        'sender_name' => env('BREVO_SENDER_NAME', env('APP_NAME', 'Artisan Brew Co.')),
        'reply_to_email' => env('BREVO_REPLY_TO_EMAIL'),
        'webhook_token' => env('BREVO_WEBHOOK_TOKEN'),
        'marketing_list_id' => env('BREVO_MARKETING_LIST_ID') !== null && env('BREVO_MARKETING_LIST_ID') !== ''
            ? (int) env('BREVO_MARKETING_LIST_ID')
            : null,
        'live_send_enabled' => filter_var(env('BREVO_LIVE_SEND_ENABLED', false), FILTER_VALIDATE_BOOLEAN),
        'timeout' => (int) env('BREVO_HTTP_TIMEOUT', 15),
        'connect_timeout' => (int) env('BREVO_HTTP_CONNECT_TIMEOUT', 5),
        'max_retries' => (int) env('BREVO_MAX_RETRIES', 4),
    ],

];
