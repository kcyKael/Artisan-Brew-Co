<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ContactEnquiry;
use App\Models\DailySalesReport;
use App\Models\EmailDelivery;
use App\Models\EmailWebhookEvent;
use App\Models\MarketingCampaign;
use App\Models\MarketingSubscriber;
use App\Services\Email\BrevoClient;
use App\Services\Email\ContactEnquiryService;
use App\Services\Email\DailySalesSummaryService;
use App\Services\Email\EmailSettingsService;
use App\Services\Email\EmailTemplateRenderer;
use App\Services\Email\MarketingCampaignService;
use App\Services\Email\MarketingSubscriberService;
use App\Services\Email\StockAlertService;
use App\Services\Email\TransactionalEmailService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class EmailCenterController extends Controller
{
    public function settings(
        EmailSettingsService $settings,
        BrevoClient $brevo,
        DailySalesSummaryService $dailySummary,
    ): View {
        return view('admin.email.settings', [
            'settings' => $settings->snapshot(),
            'liveSendEnabled' => $brevo->isLiveSendEnabled(),
            'apiKeyConfigured' => $brevo->isConfigured(),
            'webhookConfigured' => filled(config('services.brevo.webhook_token')),
            'templates' => EmailTemplateRenderer::TEMPLATES,
            'dailyPreview' => $dailySummary->computeSummary(),
            'recentReports' => DailySalesReport::query()->orderByDesc('id')->limit(7)->get(),
        ]);
    }

    public function updateSettings(Request $request, EmailSettingsService $settings): RedirectResponse
    {
        $validated = $request->validate([
            'sender_email' => ['nullable', 'string', 'email', 'max:191'],
            'sender_name' => ['nullable', 'string', 'max:120'],
            'reply_to_email' => ['nullable', 'string', 'email', 'max:191'],
            'owner_alert_emails' => ['nullable', 'string', 'max:1000'],
            'cafe_inbox_email' => ['nullable', 'string', 'email', 'max:191'],
            'daily_summary_time' => ['required', 'string', 'regex:/^(?:[01]\d|2[0-3]):[0-5]\d$/'],
            'stock_alert_mode' => ['required', Rule::in(['immediate', 'digest'])],
            'marketing_list_id' => ['nullable', 'integer', 'min:1'],
            'test_recipient_email' => ['nullable', 'string', 'email', 'max:191'],
        ]);

        $data = $validated;
        foreach (EmailSettingsService::FEATURE_KEYS as $key) {
            $data[$key] = $request->boolean($key);
        }

        $settings->update($data, $request->user());

        return back()->with('status', 'Email Center settings updated.');
    }

    public function sendTest(
        Request $request,
        TransactionalEmailService $transactional,
        EmailTemplateRenderer $renderer,
        EmailSettingsService $settings,
    ): RedirectResponse {
        $validated = $request->validate([
            'recipient_email' => ['nullable', 'string', 'email', 'max:191'],
            'template' => ['required', Rule::in(array_keys(EmailTemplateRenderer::TEMPLATES))],
        ]);

        $recipient = mb_strtolower(trim((string) ($validated['recipient_email'] ?: $settings->testRecipientEmail())));
        if ($recipient === '') {
            return back()->withErrors(['recipient_email' => 'Specify a test recipient email address.']);
        }

        $template = (string) $validated['template'];
        $delivery = $transactional->queue(
            messageKey: 'admin-test:'.$template.':'.Str::uuid(),
            purpose: $template,
            recipientEmail: $recipient,
            recipientName: $request->user()->full_name,
            subject: '[Test] '.EmailTemplateRenderer::TEMPLATES[$template].' — Artisan Brew Co.',
            templateName: $template,
            templateData: $renderer->sampleContext($template),
            options: [
                'initiated_by' => $request->user()->id,
            ]
        );

        return back()->with('status', "Test email queued for {$recipient} (status: {$delivery->status}).");
    }

    public function previewTemplate(string $template, EmailTemplateRenderer $renderer): Response
    {
        abort_unless(array_key_exists($template, EmailTemplateRenderer::TEMPLATES), 404);
        $rendered = $renderer->render($template, $renderer->sampleContext($template));

        return response($rendered['html'], 200, ['Content-Type' => 'text/html; charset=utf-8']);
    }

    public function triggerDailySummary(
        Request $request,
        DailySalesSummaryService $dailySummary,
    ): RedirectResponse {
        $validated = $request->validate([
            'business_date' => ['nullable', 'date_format:Y-m-d'],
            'test_email' => ['nullable', 'string', 'email', 'max:191'],
        ]);

        if (! empty($validated['test_email'])) {
            $dailySummary->queueTestToRecipient(
                mb_strtolower(trim((string) $validated['test_email'])),
                $validated['business_date'] ?? null,
                $request->user()
            );

            return back()->with('status', "Test daily sales summary queued for {$validated['test_email']}.");
        }

        $result = $dailySummary->generateAndQueue($validated['business_date'] ?? null, true, $request->user());

        return back()->with('status', 'Queued '.$result['queued'].' daily sales summary email(s).');
    }

    public function triggerStockDigest(Request $request, StockAlertService $stockAlerts): RedirectResponse
    {
        $count = $stockAlerts->sendDigest($request->user());

        return back()->with('status', $count > 0 ? "Queued {$count} stock digest email(s)." : 'No low-stock or sold-out items or recipients configured.');
    }

    public function deliveries(Request $request): View
    {
        $statusFilter = trim((string) $request->query('status', ''));
        $purposeFilter = trim((string) $request->query('purpose', ''));

        $deliveries = EmailDelivery::query()
            ->when($statusFilter !== '', fn ($q) => $q->where('status', $statusFilter))
            ->when($purposeFilter !== '', fn ($q) => $q->where('purpose', $purposeFilter))
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        $webhookEvents = EmailWebhookEvent::query()
            ->orderByDesc('id')
            ->limit(25)
            ->get();

        return view('admin.email.deliveries', [
            'deliveries' => $deliveries,
            'webhookEvents' => $webhookEvents,
            'statusFilter' => $statusFilter,
            'purposeFilter' => $purposeFilter,
        ]);
    }

    public function retryDelivery(EmailDelivery $delivery, TransactionalEmailService $transactional): RedirectResponse
    {
        if (! $transactional->retry($delivery)) {
            return back()->withErrors([
                'delivery' => 'This delivery cannot be retried safely (it may already be accepted/delivered, uncertain, pruned, or its link expired).',
            ]);
        }

        return back()->with('status', "Delivery #{$delivery->id} re-queued cleanly.");
    }

    public function recoverOutbox(TransactionalEmailService $transactional): RedirectResponse
    {
        $count = $transactional->recoverPending(0);

        return back()->with('status', "Recovered {$count} pending outbox delivery job(s).");
    }

    public function enquiries(Request $request): View
    {
        $statusFilter = trim((string) $request->query('status', ''));

        $enquiries = ContactEnquiry::query()
            ->with('handler')
            ->when($statusFilter !== '', fn ($q) => $q->where('status', $statusFilter))
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return view('admin.email.enquiries', [
            'enquiries' => $enquiries,
            'statusFilter' => $statusFilter,
        ]);
    }

    public function updateEnquiry(
        Request $request,
        ContactEnquiry $enquiry,
        ContactEnquiryService $enquiries,
    ): RedirectResponse {
        $validated = $request->validate([
            'status' => ['required', Rule::in([
                ContactEnquiry::STATUS_RECEIVED,
                ContactEnquiry::STATUS_IN_PROGRESS,
                ContactEnquiry::STATUS_RESOLVED,
            ])],
            'admin_notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $enquiries->updateStatus(
            $enquiry,
            (string) $validated['status'],
            $validated['admin_notes'] ?? null,
            $request->user()
        );

        return back()->with('status', "Enquiry {$enquiry->reference_code} updated.");
    }

    public function subscribers(Request $request): View
    {
        $statusFilter = trim((string) $request->query('status', ''));

        $subscribers = MarketingSubscriber::query()
            ->with('customer')
            ->when($statusFilter !== '', fn ($q) => $q->where('status', $statusFilter))
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        $counts = [
            'subscribed' => MarketingSubscriber::query()->where('status', MarketingSubscriber::STATUS_SUBSCRIBED)->count(),
            'pending' => MarketingSubscriber::query()->where('status', MarketingSubscriber::STATUS_PENDING)->count(),
            'unsubscribed' => MarketingSubscriber::query()->where('status', MarketingSubscriber::STATUS_UNSUBSCRIBED)->count(),
            'suppressed' => MarketingSubscriber::query()->where('status', MarketingSubscriber::STATUS_SUPPRESSED)->count(),
        ];

        return view('admin.email.subscribers', [
            'subscribers' => $subscribers,
            'counts' => $counts,
            'statusFilter' => $statusFilter,
        ]);
    }

    public function unsubscribeSubscriber(
        MarketingSubscriber $subscriber,
        MarketingSubscriberService $subscribers,
    ): RedirectResponse {
        $subscribers->markUnsubscribed($subscriber);

        return back()->with('status', "Subscriber {$subscriber->email} marked unsubscribed and queued for Brevo sync.");
    }

    public function campaigns(
        MarketingCampaignService $campaigns,
        BrevoClient $brevo,
        EmailSettingsService $settings,
    ): View {
        return view('admin.email.campaigns', [
            'campaigns' => MarketingCampaign::query()->orderByDesc('id')->paginate(15),
            'audienceCount' => $campaigns->eligibleAudienceCount(),
            'liveSendEnabled' => $brevo->isLiveSendEnabled(),
            'marketingListConfigured' => $settings->marketingListId() !== null,
            'marketingEnabled' => $settings->featureEnabled('feature_marketing'),
            'defaultTestEmail' => $settings->testRecipientEmail(),
        ]);
    }

    public function storeCampaign(Request $request, MarketingCampaignService $campaigns): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'min:3', 'max:160'],
            'subject' => ['required', 'string', 'min:3', 'max:200'],
            'preview_text' => ['nullable', 'string', 'max:200'],
            'body_html' => ['required', 'string', 'min:10', 'max:20000'],
        ]);

        $campaign = $campaigns->createDraft($validated, $request->user());

        return back()->with('status', "Campaign draft '{$campaign->name}' saved.");
    }

    public function previewCampaign(MarketingCampaign $campaign, MarketingCampaignService $campaigns): Response
    {
        return response($campaigns->renderPreviewHtml($campaign), 200, ['Content-Type' => 'text/html; charset=utf-8']);
    }

    public function sendCampaignTest(
        Request $request,
        MarketingCampaign $campaign,
        MarketingCampaignService $campaigns,
    ): RedirectResponse {
        $validated = $request->validate([
            'test_email' => ['required', 'string', 'email', 'max:191'],
        ]);

        try {
            $campaigns->sendTest($campaign, (string) $validated['test_email']);
        } catch (\Throwable $e) {
            return back()->withErrors(['campaign' => $e->getMessage()]);
        }

        return back()->with('status', "Test email sent to {$validated['test_email']} for campaign '{$campaign->name}'.");
    }

    public function sendCampaign(MarketingCampaign $campaign, MarketingCampaignService $campaigns, Request $request): RedirectResponse
    {
        try {
            $campaigns->sendCampaign($campaign, $request->user());
        } catch (\Throwable $e) {
            return back()->withErrors(['campaign' => $e->getMessage()]);
        }

        return back()->with('status', "Campaign '{$campaign->name}' dispatched via Brevo.");
    }

    public function syncCampaignStats(MarketingCampaign $campaign, MarketingCampaignService $campaigns): RedirectResponse
    {
        try {
            $campaigns->syncStats($campaign);
        } catch (\Throwable $e) {
            return back()->withErrors(['campaign' => $e->getMessage()]);
        }

        return back()->with('status', "Synced provider status for campaign '{$campaign->name}'.");
    }
}
