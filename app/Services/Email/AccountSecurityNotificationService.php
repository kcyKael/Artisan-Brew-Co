<?php

namespace App\Services\Email;

use App\Models\User;
use App\Support\BusinessTime;
use Illuminate\Support\Str;

class AccountSecurityNotificationService
{
    public function __construct(
        private readonly TransactionalEmailService $transactional,
        private readonly EmailSettingsService $settings,
    ) {
    }

    public function notifyPasswordChanged(User $user, ?User $actor = null, string $context = 'reset'): void
    {
        if (! $this->settings->featureEnabled('feature_security_alerts') || ! filled($user->email)) {
            return;
        }

        $summary = $context === 'reset'
            ? 'Your Artisan Brew Co. staff password was reset via recovery.'
            : 'Your Artisan Brew Co. staff account password was changed.';

        $this->sendSecurityNotice($user, 'Password Updated', $summary, $actor, 'password-changed');
    }

    public function notifyRoleChanged(User $user, string $oldRole, string $newRole, ?User $actor = null): void
    {
        if (! $this->settings->featureEnabled('feature_security_alerts') || ! filled($user->email)) {
            return;
        }

        $summary = sprintf(
            'Your Artisan Brew Co. staff role changed from %s to %s.',
            ucfirst($oldRole),
            ucfirst($newRole)
        );

        $this->sendSecurityNotice($user, 'Account Role Updated', $summary, $actor, 'role-changed');
    }

    public function notifyAccountDeactivated(User $user, ?User $actor = null): void
    {
        if (! $this->settings->featureEnabled('feature_security_alerts') || ! filled($user->email)) {
            return;
        }

        $this->sendSecurityNotice(
            $user,
            'Account Deactivated',
            'Your Artisan Brew Co. staff account has been deactivated and active sessions have been closed.',
            $actor,
            'account-deactivated'
        );
    }

    public function notifyEmailChangeVerification(User $user, string $pendingEmail, string $rawToken): void
    {
        if (! $this->settings->featureEnabled('feature_security_alerts')) {
            return;
        }

        $expireHours = max(1, (int) config('email.email_verify_expire_hours', 24));
        $verifyUrl = url(route('profile.verify-email', ['token' => $rawToken], false));

        $this->transactional->queue(
            messageKey: 'email-verify:'.$user->id.':'.hash('sha256', $rawToken),
            purpose: 'email_verification',
            recipientEmail: $pendingEmail,
            recipientName: $user->full_name,
            subject: 'Confirm Your New Artisan Brew Co. Staff Email',
            templateName: 'email_verification',
            templateData: [
                'fullName' => $user->full_name,
                'pendingEmail' => $pendingEmail,
                'verifyUrl' => $verifyUrl,
                'expireHours' => $expireHours,
            ],
            options: [
                'related_type' => 'user',
                'related_id' => $user->id,
                'initiated_by' => $user->id,
                'sensitive' => true,
                'expires_at' => now('UTC')->addHours($expireHours),
                'queue' => config('email.queues.priority', 'emails-priority'),
            ]
        );
    }

    public function notifyPreviousEmailOfChange(User $user, string $previousEmail, string $newEmail): void
    {
        if (! $this->settings->featureEnabled('feature_security_alerts') || ! filter_var($previousEmail, FILTER_VALIDATE_EMAIL)) {
            return;
        }

        $this->transactional->queue(
            messageKey: 'security:'.$user->id.':email-changed:'.Str::uuid(),
            purpose: 'security_alert',
            recipientEmail: $previousEmail,
            recipientName: $user->full_name,
            subject: 'Security Notice: Your Artisan Brew Co. Email Address Was Changed',
            templateName: 'security_alert',
            templateData: [
                'fullName' => $user->full_name,
                'eventTitle' => 'Email Address Changed',
                'eventSummary' => "Your Artisan Brew Co. staff recovery email was changed to {$newEmail}.",
                'occurredAt' => BusinessTime::format(now('UTC')).' PHT',
                'actionUrl' => route('login'),
                'actionLabel' => 'Open Staff Sign In',
            ],
            options: [
                'related_type' => 'user',
                'related_id' => $user->id,
                'initiated_by' => $user->id,
                'queue' => config('email.queues.priority', 'emails-priority'),
            ]
        );
    }

    private function sendSecurityNotice(User $user, string $title, string $summary, ?User $actor, string $slug): void
    {
        $this->transactional->queue(
            messageKey: 'security:'.$user->id.':'.$slug.':'.Str::uuid(),
            purpose: 'security_alert',
            recipientEmail: (string) $user->email,
            recipientName: $user->full_name,
            subject: 'Security Notice: '.$title.' — Artisan Brew Co.',
            templateName: 'security_alert',
            templateData: [
                'fullName' => $user->full_name,
                'eventTitle' => $title,
                'eventSummary' => $summary,
                'occurredAt' => BusinessTime::format(now('UTC')).' PHT',
                'actionUrl' => route('login'),
                'actionLabel' => 'Open Staff Sign In',
            ],
            options: [
                'related_type' => 'user',
                'related_id' => $user->id,
                'initiated_by' => $actor?->id ?? $user->id,
                'queue' => config('email.queues.priority', 'emails-priority'),
            ]
        );
    }
}
