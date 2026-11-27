<?php

namespace App\Notifications;

use App\Notifications\Channels\BrevoApiChannel;
use Illuminate\Notifications\Notification;

class StaffPasswordResetNotification extends Notification
{
    public function __construct(public readonly string $token)
    {
    }

    public function via(object $notifiable): array
    {
        return [BrevoApiChannel::class];
    }

    public function toBrevoApi(object $notifiable): array
    {
        $email = method_exists($notifiable, 'getEmailForPasswordReset')
            ? $notifiable->getEmailForPasswordReset()
            : ($notifiable->email ?? '');

        $expireMinutes = (int) config('auth.passwords.users.expire', 60);
        $resetUrl = url(route('password.reset', [
            'token' => $this->token,
            'email' => $email,
        ], false));

        $tokenHash = hash('sha256', $this->token);

        return [
            'message_key' => 'password-reset:'.$notifiable->id.':'.substr($tokenHash, 0, 24),
            'purpose' => 'password_reset',
            'recipient_email' => $email,
            'recipient_name' => $notifiable->full_name ?? null,
            'subject' => 'Reset Your Artisan Brew Co. Staff Password',
            'template_name' => 'password_reset',
            'template_data' => [
                'fullName' => $notifiable->full_name ?? 'Team Member',
                'resetUrl' => $resetUrl,
                'expireMinutes' => $expireMinutes,
            ],
            'options' => [
                'related_type' => 'user',
                'related_id' => $notifiable->id ?? null,
                'initiated_by' => $notifiable->id ?? null,
                'sensitive' => true,
                'expires_at' => now('UTC')->addMinutes($expireMinutes),
                'queue' => config('email.queues.priority', 'emails-priority'),
            ],
        ];
    }
}
