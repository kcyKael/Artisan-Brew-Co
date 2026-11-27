<?php

namespace App\Services\Email;

use App\Models\EmailSetting;
use App\Models\User;
use Illuminate\Support\Facades\Schema;

class EmailSettingsService
{
    public const FEATURE_KEYS = [
        'feature_receipts',
        'feature_password_reset',
        'feature_invitations',
        'feature_low_stock',
        'feature_daily_summary',
        'feature_security_alerts',
        'feature_enquiries',
        'feature_marketing',
    ];

    public function get(string $key, mixed $default = null): mixed
    {
        if (! Schema::hasTable('email_settings')) {
            return $default;
        }

        $record = EmailSetting::query()->whereKey($key)->first();

        return $record !== null ? $record->value : $default;
    }

    public function featureEnabled(string $featureKey): bool
    {
        $val = $this->get($featureKey, '1');

        return filter_var($val, FILTER_VALIDATE_BOOLEAN);
    }

    public function senderEmail(): string
    {
        $configured = trim((string) $this->get('sender_email', ''));

        return $configured !== ''
            ? $configured
            : (string) config('services.brevo.sender_email', 'no-reply@artisanbrewco.local');
    }

    public function senderName(): string
    {
        $configured = trim((string) $this->get('sender_name', ''));

        return $configured !== ''
            ? $configured
            : (string) config('services.brevo.sender_name', config('pos.brand_name', 'Artisan Brew Co.'));
    }

    public function replyToEmail(): ?string
    {
        $configured = trim((string) $this->get('reply_to_email', ''));
        if ($configured !== '') {
            return $configured;
        }

        $envVal = trim((string) config('services.brevo.reply_to_email', ''));

        return $envVal !== '' ? $envVal : null;
    }

    public function ownerAlertEmails(): array
    {
        $raw = (string) $this->get('owner_alert_emails', '');
        $list = $this->parseEmails($raw);
        if ($list !== []) {
            return $list;
        }

        $envList = (array) config('email.owner_alert_emails', []);
        if ($envList !== []) {
            return $envList;
        }

        return User::query()
            ->where('role', User::ROLE_ADMIN)
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->whereNotNull('email')
            ->where('email', '!=', '')
            ->pluck('email')
            ->map(fn (string $email) => mb_strtolower(trim($email)))
            ->unique()
            ->values()
            ->all();
    }

    public function cafeInboxEmail(): string
    {
        $raw = trim((string) $this->get('cafe_inbox_email', ''));
        if ($raw !== '') {
            return $raw;
        }

        $envInbox = trim((string) config('email.inbox_email', ''));
        if ($envInbox !== '' && $envInbox !== 'hello@artisanbrewco.local') {
            return $envInbox;
        }

        $owners = $this->ownerAlertEmails();

        return $owners[0] ?? $envInbox;
    }

    public function dailySummaryTime(): string
    {
        $raw = trim((string) $this->get('daily_summary_time', ''));

        if (preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $raw)) {
            return $raw;
        }

        return (string) config('email.daily_summary_time', '22:00');
    }

    public function stockAlertMode(): string
    {
        $mode = trim((string) $this->get('stock_alert_mode', ''));

        return in_array($mode, ['immediate', 'digest'], true)
            ? $mode
            : (string) config('email.stock_alert_mode', 'immediate');
    }

    public function marketingListId(): ?int
    {
        $raw = trim((string) $this->get('marketing_list_id', ''));
        if ($raw !== '' && ctype_digit($raw)) {
            return (int) $raw;
        }

        return config('services.brevo.marketing_list_id');
    }

    public function testRecipientEmail(): ?string
    {
        $raw = trim((string) $this->get('test_recipient_email', ''));

        return $raw !== '' ? mb_strtolower($raw) : null;
    }

    public function snapshot(): array
    {
        $features = [];
        foreach (self::FEATURE_KEYS as $key) {
            $features[$key] = $this->featureEnabled($key);
        }

        return array_merge($features, [
            'sender_email' => $this->senderEmail(),
            'sender_name' => $this->senderName(),
            'reply_to_email' => $this->replyToEmail(),
            'owner_alert_emails' => implode(', ', $this->ownerAlertEmails()),
            'cafe_inbox_email' => $this->cafeInboxEmail(),
            'daily_summary_time' => $this->dailySummaryTime(),
            'stock_alert_mode' => $this->stockAlertMode(),
            'marketing_list_id' => $this->marketingListId(),
            'test_recipient_email' => $this->testRecipientEmail(),
        ]);
    }

    public function update(array $data, User $actor): void
    {
        $pairs = [
            'sender_email' => trim((string) ($data['sender_email'] ?? '')),
            'sender_name' => trim((string) ($data['sender_name'] ?? '')),
            'reply_to_email' => trim((string) ($data['reply_to_email'] ?? '')),
            'owner_alert_emails' => implode(', ', $this->parseEmails((string) ($data['owner_alert_emails'] ?? ''))),
            'cafe_inbox_email' => trim((string) ($data['cafe_inbox_email'] ?? '')),
            'daily_summary_time' => trim((string) ($data['daily_summary_time'] ?? '22:00')),
            'stock_alert_mode' => in_array(($data['stock_alert_mode'] ?? 'immediate'), ['immediate', 'digest'], true)
                ? $data['stock_alert_mode']
                : 'immediate',
            'marketing_list_id' => trim((string) ($data['marketing_list_id'] ?? '')),
            'test_recipient_email' => trim((string) ($data['test_recipient_email'] ?? '')),
        ];

        foreach (self::FEATURE_KEYS as $featureKey) {
            $pairs[$featureKey] = ! empty($data[$featureKey]) ? '1' : '0';
        }

        foreach ($pairs as $key => $value) {
            EmailSetting::query()->updateOrCreate(
                ['key' => $key],
                [
                    'value' => $value,
                    'updated_by' => $actor->id,
                ]
            );
        }
    }

    private function parseEmails(string $raw): array
    {
        $parts = preg_split('/[\s,;]+/', trim($raw)) ?: [];
        $valid = [];

        foreach ($parts as $part) {
            $email = mb_strtolower(trim($part));
            if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $valid[] = $email;
            }
        }

        return array_values(array_unique($valid));
    }
}
