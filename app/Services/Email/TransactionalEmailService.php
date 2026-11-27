<?php

namespace App\Services\Email;

use App\Jobs\SendTransactionalEmailJob;
use App\Models\EmailDelivery;
use Carbon\CarbonInterface;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;

class TransactionalEmailService
{
    public function __construct(
        private readonly EmailSettingsService $settings,
        private readonly EmailTemplateRenderer $renderer,
    ) {
    }

    public function queue(
        string $messageKey,
        string $purpose,
        string $recipientEmail,
        ?string $recipientName,
        string $subject,
        string $templateName,
        array $templateData,
        array $options = [],
    ): EmailDelivery {
        $normalizedRecipient = mb_strtolower(trim($recipientEmail));
        $existing = EmailDelivery::query()->where('message_key', $messageKey)->first();
        if ($existing) {
            if ($existing->status === EmailDelivery::STATUS_QUEUED && $existing->dispatched_at === null) {
                $this->dispatchJob($existing);
            }

            return $existing;
        }

        $queueName = $options['queue'] ?? $this->resolveQueue($purpose);
        $isSensitive = (bool) ($options['sensitive'] ?? false);
        $expiresAt = $options['expires_at'] ?? null;

        $rendered = $this->renderer->render($templateName, $templateData);

        $sender = [
            'name' => $this->settings->senderName(),
            'email' => $this->settings->senderEmail(),
        ];

        $to = ['email' => $normalizedRecipient];
        if (filled($recipientName)) {
            $to['name'] = mb_substr(trim($recipientName), 0, 120);
        }

        $brevoPayload = [
            'sender' => $sender,
            'to' => [$to],
            'subject' => mb_substr($subject, 0, 200),
            'htmlContent' => $rendered['html'],
            'textContent' => $rendered['text'],
            'tags' => [$purpose],
        ];

        $replyTo = $options['reply_to'] ?? $this->settings->replyToEmail();
        if (filled($replyTo) && filter_var($replyTo, FILTER_VALIDATE_EMAIL)) {
            $brevoPayload['replyTo'] = array_filter([
                'email' => mb_strtolower(trim((string) $replyTo)),
                'name' => isset($options['reply_to_name']) ? mb_substr((string) $options['reply_to_name'], 0, 120) : null,
            ]);
        }

        if (! empty($options['attachments']) && is_array($options['attachments'])) {
            $brevoPayload['attachment'] = $options['attachments'];
        }

        $encryptedPayload = Crypt::encryptString(json_encode($brevoPayload, JSON_THROW_ON_ERROR));

        try {
            $delivery = EmailDelivery::query()->create([
                'message_key' => mb_substr($messageKey, 0, 160),
                'idempotency_key' => (string) Str::uuid(),
                'purpose' => $purpose,
                'queue_name' => $queueName,
                'recipient_email' => $normalizedRecipient,
                'recipient_name' => $recipientName ? mb_substr(trim($recipientName), 0, 120) : null,
                'subject' => mb_substr($subject, 0, 200),
                'template_name' => $templateName,
                'template_version' => (string) config('email.template_version', 'v1'),
                'related_type' => $options['related_type'] ?? null,
                'related_id' => $options['related_id'] ?? null,
                'initiated_by' => $options['initiated_by'] ?? null,
                'is_sensitive' => $isSensitive,
                'payload_encrypted' => $encryptedPayload,
                'status' => EmailDelivery::STATUS_QUEUED,
                'expires_at' => $expiresAt instanceof CarbonInterface ? $expiresAt->utc() : $expiresAt,
            ]);
        } catch (QueryException $e) {
            if ((int) ($e->errorInfo[1] ?? 0) === 1062) {
                $raced = EmailDelivery::query()->where('message_key', $messageKey)->firstOrFail();

                return $raced;
            }
            throw $e;
        }

        $this->dispatchJob($delivery);

        return $delivery->fresh() ?? $delivery;
    }

    public function retry(EmailDelivery $delivery): bool
    {
        if (! $delivery->canRetry()) {
            return false;
        }

        $delivery->status = EmailDelivery::STATUS_QUEUED;
        $delivery->last_error = null;
        $delivery->last_error_code = null;
        $delivery->idempotency_key = (string) Str::uuid();
        $delivery->save();

        $this->dispatchJob($delivery);

        return true;
    }

    public function recoverPending(int $olderThanSeconds = 60): int
    {
        $cutoff = now('UTC')->subSeconds(max(0, $olderThanSeconds));

        $pending = EmailDelivery::query()
            ->where('status', EmailDelivery::STATUS_QUEUED)
            ->where('created_at', '<=', $cutoff)
            ->where(function ($query) use ($cutoff) {
                $query->whereNull('dispatched_at')
                    ->orWhere('dispatched_at', '<=', $cutoff);
            })
            ->whereNotNull('payload_encrypted')
            ->orderBy('id')
            ->limit(100)
            ->get();

        $count = 0;
        foreach ($pending as $delivery) {
            if ($delivery->expires_at !== null && $delivery->expires_at->isPast()) {
                $delivery->status = EmailDelivery::STATUS_EXPIRED;
                if ($delivery->is_sensitive) {
                    $delivery->payload_encrypted = null;
                    $delivery->payload_pruned_at = now('UTC');
                }
                $delivery->save();
                continue;
            }

            $this->dispatchJob($delivery);
            $count++;
        }

        return $count;
    }

    public function prunePayloads(): int
    {
        $retentionDays = max(1, (int) config('email.payload_retention_days', 30));
        $cutoff = now('UTC')->subDays($retentionDays);

        $sensitiveDone = EmailDelivery::query()
            ->where('is_sensitive', true)
            ->whereNull('payload_pruned_at')
            ->whereNotNull('payload_encrypted')
            ->where(function ($q) {
                $q->whereIn('status', [
                    EmailDelivery::STATUS_ACCEPTED,
                    EmailDelivery::STATUS_DELIVERED,
                    EmailDelivery::STATUS_EXPIRED,
                    EmailDelivery::STATUS_SIMULATED,
                ])->orWhere('expires_at', '<=', now('UTC'));
            })
            ->update([
                'payload_encrypted' => null,
                'payload_pruned_at' => now('UTC'),
            ]);

        $aged = EmailDelivery::query()
            ->whereNull('payload_pruned_at')
            ->whereNotNull('payload_encrypted')
            ->where('created_at', '<=', $cutoff)
            ->update([
                'payload_encrypted' => null,
                'payload_pruned_at' => now('UTC'),
            ]);

        return $sensitiveDone + $aged;
    }

    private function dispatchJob(EmailDelivery $delivery): void
    {
        $delivery->dispatched_at = now('UTC');
        $delivery->save();

        SendTransactionalEmailJob::dispatch($delivery->id)
            ->onQueue($delivery->queue_name ?: $this->resolveQueue($delivery->purpose));
    }

    private function resolveQueue(string $purpose): string
    {
        return match ($purpose) {
            'password_reset', 'staff_invitation', 'security_alert', 'email_verification' => (string) config('email.queues.priority', 'emails-priority'),
            default => (string) config('email.queues.default', 'emails'),
        };
    }
}
