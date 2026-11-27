<?php

namespace App\Jobs;

use App\Models\EmailDelivery;
use App\Models\EmailWebhookEvent;
use App\Models\MarketingSubscriber;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class ProcessBrevoWebhookEventJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(public readonly int $webhookEventId)
    {
    }

    public function handle(): void
    {
        DB::transaction(function (): void {
            $event = EmailWebhookEvent::query()
                ->whereKey($this->webhookEventId)
                ->lockForUpdate()
                ->first();

            if (! $event || $event->status === EmailWebhookEvent::STATUS_PROCESSED) {
                return;
            }

            $normalizedType = mb_strtolower((string) $event->event_type);
            $matched = false;

            if ($event->scope === 'transactional') {
                $delivery = $this->findMatchingDelivery($event);
                if ($delivery) {
                    $matched = true;
                    $event->email_delivery_id = $delivery->id;
                    $this->applyDeliveryEvent($delivery, $normalizedType, $event->occurred_at);
                }
            }

            if (in_array($normalizedType, [
                'unsubscribed',
                'unsubscribe',
                'hard_bounce',
                'hardbounce',
                'hard_bounced',
                'hardbounced',
                'blocked',
                'spam',
                'complaint',
                'invalid_email',
                'error',
            ], true) && filled($event->email)) {
                $subscriber = MarketingSubscriber::query()
                    ->where('email', mb_strtolower(trim((string) $event->email)))
                    ->lockForUpdate()
                    ->first();

                if ($subscriber) {
                    $matched = true;
                    $this->applySubscriberEvent($subscriber, $normalizedType, $event->occurred_at);
                }
            }

            $event->status = $matched
                ? EmailWebhookEvent::STATUS_PROCESSED
                : EmailWebhookEvent::STATUS_QUARANTINED;
            $event->processed_at = now('UTC');
            $event->save();
        });
    }

    private function findMatchingDelivery(EmailWebhookEvent $event): ?EmailDelivery
    {
        if (filled($event->provider_message_id)) {
            $byMsgId = EmailDelivery::query()
                ->where('provider_message_id', $event->provider_message_id)
                ->lockForUpdate()
                ->first();

            if ($byMsgId) {
                return $byMsgId;
            }
        }

        if (filled($event->email)) {
            return EmailDelivery::query()
                ->where('recipient_email', mb_strtolower(trim((string) $event->email)))
                ->whereIn('status', [
                    EmailDelivery::STATUS_ACCEPTED,
                    EmailDelivery::STATUS_UNCERTAIN,
                    EmailDelivery::STATUS_DELIVERED,
                ])
                ->orderByDesc('id')
                ->lockForUpdate()
                ->first();
        }

        return null;
    }

    private function applyDeliveryEvent(EmailDelivery $delivery, string $eventType, mixed $occurredAt): void
    {
        $eventTime = $occurredAt ?? now('UTC');
        $isNewer = $delivery->last_event_at === null || $eventTime->greaterThanOrEqualTo($delivery->last_event_at);

        $mappedStatus = match ($eventType) {
            'delivered' => EmailDelivery::STATUS_DELIVERED,
            'hard_bounce', 'hardbounce', 'soft_bounce', 'softbounce', 'hard_bounced', 'hardbounced' => EmailDelivery::STATUS_BOUNCED,
            'blocked', 'spam', 'complaint', 'invalid_email', 'error', 'deferred' => EmailDelivery::STATUS_BLOCKED,
            'request', 'sent' => EmailDelivery::STATUS_ACCEPTED,
            default => null,
        };

        if ($mappedStatus === null) {
            return;
        }

        if ($mappedStatus === EmailDelivery::STATUS_DELIVERED && $delivery->delivered_at === null) {
            $delivery->delivered_at = $eventTime;
        }

        if (! $isNewer) {
            return;
        }

        $delivery->last_event_at = $eventTime;

        if ($delivery->status === EmailDelivery::STATUS_DELIVERED && $mappedStatus === EmailDelivery::STATUS_ACCEPTED) {
            $delivery->save();

            return;
        }

        $delivery->status = $mappedStatus;
        if (in_array($mappedStatus, [EmailDelivery::STATUS_BOUNCED, EmailDelivery::STATUS_BLOCKED], true)) {
            $delivery->failed_at = $eventTime;
            $delivery->last_error_code = $eventType;
        }
        $delivery->save();
    }

    private function applySubscriberEvent(MarketingSubscriber $subscriber, string $eventType, mixed $occurredAt): void
    {
        $eventTime = $occurredAt ?? now('UTC');

        if (in_array($eventType, ['unsubscribed', 'unsubscribe'], true)) {
            $subscriber->status = MarketingSubscriber::STATUS_UNSUBSCRIBED;
            $subscriber->unsubscribed_at = $subscriber->unsubscribed_at ?? $eventTime;
            $subscriber->provider_blacklisted = true;
            $subscriber->save();

            return;
        }

        $subscriber->status = MarketingSubscriber::STATUS_SUPPRESSED;
        $subscriber->suppressed_at = $subscriber->suppressed_at ?? $eventTime;
        $subscriber->suppression_reason = $eventType;
        $subscriber->provider_blacklisted = true;
        $subscriber->save();
    }
}
