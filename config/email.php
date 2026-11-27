<?php

return [
    'owner_alert_emails' => array_values(array_filter(array_map(
        static fn (string $email): string => mb_strtolower(trim($email)),
        explode(',', (string) env('ABC_OWNER_ALERT_EMAILS', ''))
    ))),
    'inbox_email' => env('ABC_CAFE_INBOX_EMAIL', env('BREVO_REPLY_TO_EMAIL', 'hello@artisanbrewco.local')),
    'daily_summary_time' => env('ABC_DAILY_SUMMARY_TIME', '22:00'),
    'stock_alert_mode' => env('ABC_STOCK_ALERT_MODE', 'immediate'),
    'template_version' => 'v1',
    'consent_notice_version' => '2026-11-v1',
    'invitation_expire_hours' => 48,
    'subscriber_confirm_hours' => 24,
    'receipt_link_expire_days' => 7,
    'email_verify_expire_hours' => 24,
    'payload_retention_days' => 30,
    'queues' => [
        'priority' => env('ABC_EMAIL_PRIORITY_QUEUE', 'emails-priority'),
        'default' => env('ABC_EMAIL_DEFAULT_QUEUE', 'emails'),
        'marketing' => env('ABC_EMAIL_MARKETING_QUEUE', 'emails-marketing'),
        'webhooks' => env('ABC_EMAIL_WEBHOOKS_QUEUE', 'emails-webhooks'),
    ],
];
