<?php

namespace App\Notifications\Channels;

use App\Services\Email\TransactionalEmailService;
use Illuminate\Notifications\Notification;

class BrevoApiChannel
{
    public function __construct(private readonly TransactionalEmailService $transactional)
    {
    }

    public function send(object $notifiable, Notification $notification): void
    {
        if (! method_exists($notification, 'toBrevoApi')) {
            return;
        }

        $payload = $notification->toBrevoApi($notifiable);
        if (! is_array($payload) || empty($payload['recipient_email'])) {
            return;
        }

        $this->transactional->queue(
            messageKey: (string) $payload['message_key'],
            purpose: (string) $payload['purpose'],
            recipientEmail: (string) $payload['recipient_email'],
            recipientName: $payload['recipient_name'] ?? null,
            subject: (string) $payload['subject'],
            templateName: (string) $payload['template_name'],
            templateData: (array) ($payload['template_data'] ?? []),
            options: (array) ($payload['options'] ?? []),
        );
    }
}
