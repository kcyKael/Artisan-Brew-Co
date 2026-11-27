<?php

namespace App\Jobs;

use App\Models\MarketingSubscriber;
use App\Services\Email\BrevoApiException;
use App\Services\Email\BrevoClient;
use App\Services\Email\EmailSettingsService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SyncBrevoContactJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(
        public readonly int $subscriberId,
        public readonly string $action = 'subscribe',
    ) {
    }

    public function handle(BrevoClient $brevo, EmailSettingsService $settings): void
    {
        if (! $brevo->isLiveSendEnabled()) {
            return;
        }

        $subscriber = MarketingSubscriber::query()->find($this->subscriberId);
        if (! $subscriber) {
            return;
        }

        $listId = $subscriber->provider_list_id ?: $settings->marketingListId();

        try {
            if ($this->action === 'unsubscribe' || $subscriber->status === MarketingSubscriber::STATUS_UNSUBSCRIBED) {
                $brevo->unsubscribeContact($subscriber->email, $listId);

                return;
            }

            if (! $subscriber->isEligibleForCampaigns()) {
                return;
            }

            $attributes = array_filter([
                'FIRSTNAME' => $subscriber->name,
                'CONSENT_SOURCE' => $subscriber->consent_source,
            ]);

            $result = $brevo->upsertContact($subscriber->email, $attributes, $listId);
            if (! empty($result['contact_id'])) {
                $subscriber->provider_contact_id = $result['contact_id'];
            }
            if ($listId !== null) {
                $subscriber->provider_list_id = $listId;
            }
            $subscriber->save();
        } catch (BrevoApiException $e) {
            if ($e->isRetryable) {
                $this->release($e->retryAfterSeconds ?? 30);
            }
        }
    }
}
