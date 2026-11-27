<?php

namespace Tests\Feature;

use App\Jobs\ProcessBrevoWebhookEventJob;
use App\Jobs\SendTransactionalEmailJob;
use App\Models\EmailDelivery;
use App\Models\EmailWebhookEvent;
use App\Models\MarketingSubscriber;
use App\Models\User;
use App\Services\Email\EmailTemplateRenderer;
use App\Services\Email\TransactionalEmailService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class EmailBrevoPipelineTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
    }

    private function makeStaff(string $username, string $role = User::ROLE_ADMIN): User
    {
        $user = new User([
            'username' => $username,
            'full_name' => ucfirst($username).' User',
            'email' => $username.'@artisanbrewco.ph',
            'password' => Hash::make('Secret@123'),
        ]);
        $user->role = $role;
        $user->is_active = true;
        $user->email_verified_at = now('UTC');
        $user->save();

        return $user;
    }

    public function test_live_send_disabled_records_simulated_without_external_http_calls(): void
    {
        config([
            'services.brevo.api_key' => '',
            'services.brevo.live_send_enabled' => false,
        ]);

        Http::fake();

        $renderer = app(EmailTemplateRenderer::class);
        $service = app(TransactionalEmailService::class);
        $delivery = $service->queue(
            messageKey: 'test-sim-1',
            purpose: 'receipt',
            recipientEmail: 'guest@example.ph',
            recipientName: 'Guest',
            subject: 'Simulated Receipt',
            templateName: 'receipt',
            templateData: $renderer->sampleContext('receipt')
        );

        $delivery->refresh();
        $this->assertSame(EmailDelivery::STATUS_SIMULATED, $delivery->status);
        $this->assertSame('live_send_disabled', $delivery->last_error_code);
        Http::assertNothingSent();
    }

    public function test_brevo_client_sends_https_post_with_api_key_and_idempotency_key(): void
    {
        config([
            'services.brevo.api_key' => 'xkeysib-test-secret',
            'services.brevo.live_send_enabled' => true,
            'services.brevo.base_url' => 'https://api.brevo.com/v3',
        ]);

        Http::fake([
            'https://api.brevo.com/v3/smtp/email' => Http::response(['messageId' => '<msg-101@brevo>'], 201),
        ]);

        $renderer = app(EmailTemplateRenderer::class);
        $service = app(TransactionalEmailService::class);
        $delivery = $service->queue(
            messageKey: 'test-live-101',
            purpose: 'enquiry_ack',
            recipientEmail: 'visitor@example.ph',
            recipientName: 'Visitor',
            subject: 'We received your enquiry',
            templateName: 'enquiry_ack',
            templateData: $renderer->sampleContext('enquiry_ack')
        );

        $delivery->refresh();
        $this->assertSame(EmailDelivery::STATUS_ACCEPTED, $delivery->status);
        $this->assertSame('<msg-101@brevo>', $delivery->provider_message_id);
        $this->assertSame(1, $delivery->attempts);

        Http::assertSent(function (ClientRequest $request) use ($delivery): bool {
            return $request->url() === 'https://api.brevo.com/v3/smtp/email'
                && $request->method() === 'POST'
                && $request->hasHeader('api-key', 'xkeysib-test-secret')
                && ($request['headers']['idempotencyKey'] ?? null) === $delivery->idempotency_key
                && ($request['to'][0]['email'] ?? null) === 'visitor@example.ph';
        });
    }

    public function test_rate_limit_429_requeues_and_permanent_400_marks_failed(): void
    {
        config([
            'services.brevo.api_key' => 'xkeysib-test-secret',
            'services.brevo.live_send_enabled' => true,
            'services.brevo.base_url' => 'https://api.brevo.com/v3',
            'services.brevo.max_retries' => 3,
        ]);

        Http::fake([
            'https://api.brevo.com/v3/smtp/email' => Http::sequence()
                ->push(['message' => 'Too many requests'], 429, ['x-sib-ratelimit-reset' => '30'])
                ->push(['messageId' => '<recovered-202@brevo>'], 201)
                ->push(['code' => 'invalid_parameter', 'message' => 'Bad email'], 400),
        ]);

        $renderer = app(EmailTemplateRenderer::class);
        $service = app(TransactionalEmailService::class);

        $originalQueue = Queue::getFacadeRoot();
        Queue::fake();
        $retryable = $service->queue(
            messageKey: 'test-429-key',
            purpose: 'low_stock_alert',
            recipientEmail: 'owner@artisanbrewco.ph',
            recipientName: null,
            subject: 'Low Stock Alert',
            templateName: 'low_stock_alert',
            templateData: $renderer->sampleContext('low_stock_alert')
        );
        Queue::assertPushed(SendTransactionalEmailJob::class);

        Queue::swap($originalQueue);
        app()->instance('queue', $originalQueue);

        (new SendTransactionalEmailJob($retryable->id))->handle(app(\App\Services\Email\BrevoClient::class));
        $retryable->refresh();
        $this->assertSame(EmailDelivery::STATUS_QUEUED, $retryable->status);
        $this->assertSame(429, $retryable->last_http_status);

        (new SendTransactionalEmailJob($retryable->id))->handle(app(\App\Services\Email\BrevoClient::class));
        $retryable->refresh();
        $this->assertSame(EmailDelivery::STATUS_ACCEPTED, $retryable->status);
        $this->assertSame('<recovered-202@brevo>', $retryable->provider_message_id);

        $bad = $service->queue(
            messageKey: 'test-400-key',
            purpose: 'low_stock_alert',
            recipientEmail: 'bad@example.ph',
            recipientName: null,
            subject: 'Bad Email Alert',
            templateName: 'low_stock_alert',
            templateData: $renderer->sampleContext('low_stock_alert')
        );

        $bad->refresh();
        $this->assertSame(EmailDelivery::STATUS_FAILED, $bad->status);
        $this->assertSame(400, $bad->last_http_status);
    }

    public function test_webhooks_enforce_token_deduplicate_events_and_update_delivery_and_suppression(): void
    {
        config(['services.brevo.webhook_token' => 'super-secret-webhook-token']);

        $delivery = EmailDelivery::create([
            'message_key' => 'webhook-delivery-1',
            'idempotency_key' => '11111111-1111-4111-8111-111111111111',
            'purpose' => 'receipt',
            'queue_name' => 'emails-default',
            'recipient_email' => 'buyer@example.ph',
            'subject' => 'Receipt',
            'template_name' => 'receipt',
            'status' => EmailDelivery::STATUS_ACCEPTED,
            'provider_message_id' => '<msg-wh-99>',
            'attempts' => 1,
        ]);

        $subscriber = MarketingSubscriber::create([
            'email' => 'buyer@example.ph',
            'status' => MarketingSubscriber::STATUS_SUBSCRIBED,
            'consent_source' => 'landing_newsletter_form',
            'consent_notice_version' => '2026-11-v1',
            'unsubscribe_token_hash' => hash('sha256', 'unsub-tok'),
            'confirmed_at' => now('UTC'),
        ]);

        $this->postJson('/webhooks/brevo/transactional', [
            'event' => 'delivered',
            'message-id' => '<msg-wh-99>',
            'email' => 'buyer@example.ph',
            'ts_event' => 1764240000,
        ])->assertStatus(401);

        $payload = [
            'event' => 'delivered',
            'message-id' => '<msg-wh-99>',
            'email' => 'buyer@example.ph',
            'ts_event' => 1764240000,
        ];

        $this->withHeaders(['X-Brevo-Webhook-Token' => 'super-secret-webhook-token'])
            ->postJson('/webhooks/brevo/transactional', $payload)
            ->assertStatus(202)
            ->assertJson(['status' => 'accepted']);

        $this->withHeaders(['Authorization' => 'Bearer super-secret-webhook-token'])
            ->postJson('/webhooks/brevo/transactional', $payload)
            ->assertStatus(202)
            ->assertJson(['status' => 'duplicate_ignored']);

        $delivery->refresh();
        $this->assertSame(EmailDelivery::STATUS_DELIVERED, $delivery->status);
        $this->assertNotNull($delivery->delivered_at);
        $this->assertSame(1, EmailWebhookEvent::count());

        $this->withHeaders(['X-Brevo-Webhook-Token' => 'super-secret-webhook-token'])
            ->postJson('/webhooks/brevo/marketing', [
                'event' => 'hard_bounce',
                'email' => 'buyer@example.ph',
                'ts_event' => 1764240100,
            ])
            ->assertStatus(202);

        $subscriber->refresh();
        $this->assertSame(MarketingSubscriber::STATUS_SUPPRESSED, $subscriber->status);
        $this->assertNotNull($subscriber->suppressed_at);
    }

    public function test_email_center_routes_require_admin_and_prune_command_cleans_old_payloads(): void
    {
        $cashier = $this->makeStaff('cashier_email_ui', User::ROLE_CASHIER);
        $admin = $this->makeStaff('admin_email_ui', User::ROLE_ADMIN);

        $this->get('/admin/email')->assertRedirect('/login');
        $this->actingAs($cashier)->get('/admin/email')->assertStatus(403);

        $this->actingAs($admin)->get('/admin/email')->assertOk();
        $this->actingAs($admin)->get('/admin/email/deliveries')->assertOk();
        $this->actingAs($admin)->get('/admin/email/enquiries')->assertOk();
        $this->actingAs($admin)->get('/admin/email/subscribers')->assertOk();
        $this->actingAs($admin)->get('/admin/email/campaigns')->assertOk();
        $this->actingAs($admin)->get('/admin/email/templates/receipt/preview')->assertOk();

        $old = EmailDelivery::create([
            'message_key' => 'old-payload-1',
            'idempotency_key' => '22222222-2222-4222-8222-222222222222',
            'purpose' => 'receipt',
            'queue_name' => 'emails-default',
            'recipient_email' => 'old@example.ph',
            'subject' => 'Old Receipt',
            'template_name' => 'receipt',
            'payload_encrypted' => 'encrypted-secret',
            'status' => EmailDelivery::STATUS_DELIVERED,
            'attempts' => 1,
        ]);
        $old->forceFill(['created_at' => now('UTC')->subDays(45), 'updated_at' => now('UTC')->subDays(45)])->saveQuietly();

        $this->artisan('abc:emails-prune-payloads')->assertExitCode(0);

        $old->refresh();
        $this->assertNull($old->payload_encrypted);
        $this->assertNotNull($old->payload_pruned_at);

        $queued = EmailDelivery::create([
            'message_key' => 'recover-me-1',
            'idempotency_key' => '33333333-3333-4333-8333-333333333333',
            'purpose' => 'receipt',
            'queue_name' => 'emails-default',
            'recipient_email' => 'pending@example.ph',
            'subject' => 'Stale Pending',
            'template_name' => 'receipt',
            'payload_encrypted' => 'encrypted-secret',
            'status' => EmailDelivery::STATUS_QUEUED,
            'dispatched_at' => null,
            'attempts' => 0,
        ]);
        $queued->forceFill(['created_at' => now('UTC')->subMinutes(20)])->saveQuietly();

        Queue::fake();
        $this->actingAs($admin)->post('/admin/email/deliveries/recover')->assertRedirect();
        Queue::assertPushed(SendTransactionalEmailJob::class);
    }
}
