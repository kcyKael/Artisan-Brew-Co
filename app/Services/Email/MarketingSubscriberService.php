<?php

namespace App\Services\Email;

use App\Jobs\SyncBrevoContactJob;
use App\Models\Customer;
use App\Models\MarketingSubscriber;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MarketingSubscriberService
{
    public function __construct(
        private readonly TransactionalEmailService $transactional,
        private readonly EmailSettingsService $settings,
    ) {
    }

    public function requestSubscription(
        string $email,
        ?string $name,
        string $consentSource,
        ?Customer $customer = null,
    ): MarketingSubscriber {
        $normalized = mb_strtolower(trim($email));
        $noticeVersion = (string) config('email.consent_notice_version', '2026-11-v1');
        $confirmHours = max(1, (int) config('email.subscriber_confirm_hours', 24));
        $rawConfirmToken = Str::random(64);
        $rawUnsubToken = Str::random(64);

        $subscriber = DB::transaction(function () use (
            $normalized,
            $name,
            $consentSource,
            $noticeVersion,
            $confirmHours,
            $rawConfirmToken,
            $rawUnsubToken,
            $customer
        ): MarketingSubscriber {
            $existing = MarketingSubscriber::query()
                ->where('email', $normalized)
                ->lockForUpdate()
                ->first();

            if ($existing && $existing->status === MarketingSubscriber::STATUS_SUPPRESSED) {
                return $existing;
            }

            if ($existing && $existing->isEligibleForCampaigns()) {
                return $existing;
            }

            if (! $existing) {
                $existing = new MarketingSubscriber(['email' => $normalized]);
                $existing->unsubscribe_token_hash = hash('sha256', $rawUnsubToken);
            }

            if (filled($name)) {
                $existing->name = mb_substr(trim($name), 0, 120);
            }

            if ($customer) {
                $existing->customer_id = $customer->id;
            }

            if (empty($existing->unsubscribe_token_hash)) {
                $existing->unsubscribe_token_hash = hash('sha256', $rawUnsubToken);
            }

            $existing->status = MarketingSubscriber::STATUS_PENDING;
            $existing->consent_source = mb_substr($consentSource, 0, 60);
            $existing->consent_notice_version = $noticeVersion;
            $existing->consented_at = now('UTC');
            $existing->unsubscribed_at = null;
            $existing->confirm_token_hash = hash('sha256', $rawConfirmToken);
            $existing->confirm_expires_at = now('UTC')->addHours($confirmHours);
            $existing->save();

            return $existing;
        });

        if (
            $subscriber->status === MarketingSubscriber::STATUS_PENDING
            && $this->settings->featureEnabled('feature_marketing')
        ) {
            $confirmUrl = url(route('newsletter.confirm', ['token' => $rawConfirmToken], false));

            $this->transactional->queue(
                messageKey: 'newsletter-confirm:'.$subscriber->id.':'.substr((string) $subscriber->confirm_token_hash, 0, 16),
                purpose: 'newsletter_confirm',
                recipientEmail: $subscriber->email,
                recipientName: $subscriber->name,
                subject: 'Confirm Your Artisan Brew Co. Newsletter Subscription',
                templateName: 'newsletter_confirm',
                templateData: [
                    'subscriberName' => $subscriber->name,
                    'confirmUrl' => $confirmUrl,
                    'expireHours' => $confirmHours,
                    'noticeVersion' => $noticeVersion,
                ],
                options: [
                    'related_type' => 'marketing_subscriber',
                    'related_id' => $subscriber->id,
                    'sensitive' => true,
                    'expires_at' => $subscriber->confirm_expires_at,
                ]
            );
        }

        return $subscriber;
    }

    public function confirmByToken(string $rawToken): ?MarketingSubscriber
    {
        $hash = hash('sha256', trim($rawToken));

        $subscriber = DB::transaction(function () use ($hash): ?MarketingSubscriber {
            $sub = MarketingSubscriber::query()
                ->where('confirm_token_hash', $hash)
                ->lockForUpdate()
                ->first();

            if (! $sub) {
                return null;
            }

            if ($sub->status === MarketingSubscriber::STATUS_SUPPRESSED || $sub->provider_blacklisted) {
                return $sub;
            }

            if ($sub->confirm_expires_at === null || $sub->confirm_expires_at->isPast()) {
                return null;
            }

            $sub->status = MarketingSubscriber::STATUS_SUBSCRIBED;
            $sub->confirmed_at = $sub->confirmed_at ?? now('UTC');
            $sub->unsubscribed_at = null;
            $sub->confirm_token_hash = null;
            $sub->confirm_expires_at = null;
            $sub->save();

            return $sub;
        });

        if ($subscriber && $subscriber->isEligibleForCampaigns()) {
            SyncBrevoContactJob::dispatch($subscriber->id, 'subscribe')
                ->onQueue((string) config('email.queues.marketing', 'emails-marketing'));
        }

        return $subscriber;
    }

    public function unsubscribeByToken(string $rawToken): ?MarketingSubscriber
    {
        $hash = hash('sha256', trim($rawToken));

        $subscriber = DB::transaction(function () use ($hash): ?MarketingSubscriber {
            $sub = MarketingSubscriber::query()
                ->where('unsubscribe_token_hash', $hash)
                ->lockForUpdate()
                ->first();

            if (! $sub) {
                return null;
            }

            return $this->markUnsubscribed($sub);
        });

        return $subscriber;
    }

    public function markUnsubscribed(MarketingSubscriber $subscriber): MarketingSubscriber
    {
        if ($subscriber->status !== MarketingSubscriber::STATUS_SUPPRESSED) {
            $subscriber->status = MarketingSubscriber::STATUS_UNSUBSCRIBED;
        }
        $subscriber->unsubscribed_at = $subscriber->unsubscribed_at ?? now('UTC');
        $subscriber->confirm_token_hash = null;
        $subscriber->confirm_expires_at = null;
        $subscriber->save();

        SyncBrevoContactJob::dispatch($subscriber->id, 'unsubscribe')
            ->onQueue((string) config('email.queues.marketing', 'emails-marketing'));

        return $subscriber;
    }

    public function findByUnsubscribeToken(string $rawToken): ?MarketingSubscriber
    {
        return MarketingSubscriber::query()
            ->where('unsubscribe_token_hash', hash('sha256', trim($rawToken)))
            ->first();
    }
}
