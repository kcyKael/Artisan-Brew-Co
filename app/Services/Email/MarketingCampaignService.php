<?php

namespace App\Services\Email;

use App\Models\MarketingCampaign;
use App\Models\MarketingSubscriber;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class MarketingCampaignService
{
    public function __construct(
        private readonly BrevoClient $brevo,
        private readonly EmailSettingsService $settings,
        private readonly EmailTemplateRenderer $renderer,
    ) {
    }

    public function eligibleAudienceCount(): int
    {
        return MarketingSubscriber::query()
            ->where('status', MarketingSubscriber::STATUS_SUBSCRIBED)
            ->where('provider_blacklisted', false)
            ->whereNull('suppressed_at')
            ->whereNull('unsubscribed_at')
            ->count();
    }

    public function createDraft(array $data, User $creator): MarketingCampaign
    {
        return MarketingCampaign::query()->create([
            'idempotency_key' => (string) Str::uuid(),
            'name' => trim((string) $data['name']),
            'subject' => trim((string) $data['subject']),
            'preview_text' => isset($data['preview_text']) ? trim((string) $data['preview_text']) : null,
            'body_html' => (string) $data['body_html'],
            'status' => MarketingCampaign::STATUS_DRAFT,
            'audience_count' => $this->eligibleAudienceCount(),
            'created_by' => $creator->id,
        ]);
    }

    public function renderPreviewHtml(MarketingCampaign $campaign): string
    {
        return $this->renderer->render('campaign', [
            'subject' => $campaign->subject,
            'previewText' => $campaign->preview_text,
            'bodyHtml' => $campaign->body_html,
            'menuUrl' => route('menu.public'),
            'unsubscribePlaceholder' => '{{ unsubscribe }}',
            'preferencesUrl' => '{{ update_profile }}',
        ])['html'];
    }

    public function ensureProviderCampaign(MarketingCampaign $campaign): MarketingCampaign
    {
        if ($campaign->provider_campaign_id !== null) {
            return $campaign;
        }

        $listId = $this->settings->marketingListId();
        if (! $listId) {
            throw new RuntimeException('BREVO_MARKETING_LIST_ID is not configured in Email Settings or environment.');
        }

        $payload = [
            'name' => $campaign->name,
            'subject' => $campaign->subject,
            'sender' => [
                'name' => $this->settings->senderName(),
                'email' => $this->settings->senderEmail(),
            ],
            'type' => 'classic',
            'htmlContent' => $this->renderPreviewHtml($campaign),
            'recipients' => [
                'listIds' => [$listId],
            ],
        ];

        $replyTo = $this->settings->replyToEmail();
        if (filled($replyTo)) {
            $payload['replyTo'] = $replyTo;
        }

        $providerId = $this->brevo->createCampaign($payload);
        $campaign->provider_campaign_id = $providerId;
        $campaign->provider_status = 'draft';
        $campaign->save();

        return $campaign;
    }

    public function sendTest(MarketingCampaign $campaign, string $testEmail): void
    {
        if (! $this->settings->featureEnabled('feature_marketing')) {
            throw new RuntimeException('Promotional campaigns are currently disabled in Email Settings.');
        }

        if (! $this->brevo->isLiveSendEnabled()) {
            throw new RuntimeException('Live Brevo sending is disabled (BREVO_LIVE_SEND_ENABLED=false or API key unset). Use Preview locally instead.');
        }

        $campaign = $this->ensureProviderCampaign($campaign);
        $this->brevo->sendCampaignTest((int) $campaign->provider_campaign_id, [mb_strtolower(trim($testEmail))]);
        $campaign->last_tested_at = now('UTC');
        $campaign->last_error = null;
        $campaign->save();
    }

    public function sendCampaign(MarketingCampaign $campaign, User $actor): MarketingCampaign
    {
        if (! $this->settings->featureEnabled('feature_marketing')) {
            throw new RuntimeException('Promotional campaigns are currently disabled in Email Settings.');
        }

        if (! $this->brevo->isLiveSendEnabled()) {
            throw new RuntimeException('Live Brevo campaign sending is disabled (BREVO_LIVE_SEND_ENABLED=false or API key unset).');
        }

        $claimed = DB::transaction(function () use ($campaign, $actor): MarketingCampaign {
            $locked = MarketingCampaign::query()
                ->whereKey($campaign->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->status === MarketingCampaign::STATUS_SENT || $locked->status === MarketingCampaign::STATUS_SENDING) {
                throw new RuntimeException('This campaign has already been sent or is currently dispatching.');
            }

            $audienceCount = $this->eligibleAudienceCount();
            if ($audienceCount === 0) {
                throw new RuntimeException('There are no confirmed eligible subscribers available to receive this campaign.');
            }

            $locked->status = MarketingCampaign::STATUS_SENDING;
            $locked->audience_count = $audienceCount;
            $locked->sent_by = $actor->id;
            $locked->last_error = null;
            $locked->save();

            return $locked;
        });

        try {
            $claimed = $this->ensureProviderCampaign($claimed);
            $this->brevo->sendCampaignNow((int) $claimed->provider_campaign_id);
            $claimed->status = MarketingCampaign::STATUS_SENT;
            $claimed->provider_status = 'queued';
            $claimed->sent_at = now('UTC');
            $claimed->save();
        } catch (\Throwable $e) {
            $claimed->status = MarketingCampaign::STATUS_FAILED;
            $claimed->last_error = mb_substr($e->getMessage(), 0, 480);
            $claimed->save();
            throw $e;
        }

        return $claimed;
    }

    public function syncStats(MarketingCampaign $campaign): MarketingCampaign
    {
        if (! $campaign->provider_campaign_id) {
            return $campaign;
        }

        $data = $this->brevo->getCampaign((int) $campaign->provider_campaign_id);
        $campaign->provider_status = is_string($data['status'] ?? null) ? $data['status'] : $campaign->provider_status;
        if (isset($data['statistics'])) {
            $campaign->stats_json = json_encode($data['statistics'], JSON_THROW_ON_ERROR);
        }
        $campaign->save();

        return $campaign;
    }
}
