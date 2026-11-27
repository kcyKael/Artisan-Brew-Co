<?php

namespace Tests\Feature;

use App\Models\ContactEnquiry;
use App\Models\EmailDelivery;
use App\Models\MarketingCampaign;
use App\Models\MarketingSubscriber;
use App\Models\User;
use App\Services\Email\EmailSettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class EmailMarketingAndEnquiriesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        config([
            'services.brevo.api_key' => 'xkeysib-test-secret',
            'services.brevo.live_send_enabled' => true,
            'services.brevo.base_url' => 'https://api.brevo.com/v3',
            'services.brevo.marketing_list_id' => 9,
        ]);
    }

    private function makeAdmin(): User
    {
        $admin = new User([
            'username' => 'admin_mkt',
            'full_name' => 'Marketing Admin',
            'email' => 'admin@artisanbrewco.ph',
            'password' => Hash::make('Secret@123'),
        ]);
        $admin->role = User::ROLE_ADMIN;
        $admin->is_active = true;
        $admin->email_verified_at = now('UTC');
        $admin->save();

        return $admin;
    }

    public function test_contact_enquiry_persists_before_email_sets_reply_to_escapes_html_and_honors_honeypot(): void
    {
        Http::fake([
            'https://api.brevo.com/v3/smtp/email' => Http::response(['messageId' => '<enq-1@brevo>'], 201),
        ]);

        $this->post('/contact', [
            'website' => 'https://spam.example',
            'name' => 'Bot',
            'email' => 'bot@example.com',
            'subject' => 'Spam Offer',
            'message' => 'This is spam content over ten chars.',
        ])->assertRedirect(route('home').'#contact');

        $this->assertSame(0, ContactEnquiry::count());
        Http::assertNothingSent();

        $this->post('/contact', [
            'name' => 'Camille Reyes',
            'email' => 'camille@example.ph',
            'subject' => 'Coffee Tasting <script>alert(1)</script>',
            'message' => 'Can we book a <script>evil()</script> Friday tasting session?',
        ])->assertRedirect(route('home').'#contact');

        $this->assertSame(1, ContactEnquiry::count());
        $this->assertSame(0, MarketingSubscriber::count());

        $inbox = EmailDelivery::where('purpose', 'enquiry_inbox')->firstOrFail();
        $payload = json_decode(Crypt::decryptString($inbox->payload_encrypted), true);
        $this->assertSame('camille@example.ph', $payload['replyTo']['email'] ?? null);
        $this->assertStringNotContainsString('<script>evil()</script>', $payload['htmlContent']);
        $this->assertStringContainsString('&lt;script&gt;evil()&lt;/script&gt;', $payload['htmlContent']);

        $this->assertDatabaseHas('email_deliveries', [
            'purpose' => 'enquiry_ack',
            'recipient_email' => 'camille@example.ph',
        ]);

        $admin = $this->makeAdmin();
        $enquiry = ContactEnquiry::firstOrFail();

        $this->actingAs($admin)->patch('/admin/email/enquiries/'.$enquiry->id, [
            'status' => ContactEnquiry::STATUS_RESOLVED,
            'admin_notes' => 'Confirmed tasting slot for Friday 4 PM.',
        ])->assertRedirect();

        $enquiry->refresh();
        $this->assertSame(ContactEnquiry::STATUS_RESOLVED, $enquiry->status);
        $this->assertSame('Confirmed tasting slot for Friday 4 PM.', $enquiry->admin_notes);
    }

    public function test_creating_customer_never_silently_subscribes_and_double_opt_in_syncs_with_brevo(): void
    {
        Http::fake([
            'https://api.brevo.com/v3/smtp/email' => Http::response(['messageId' => '<nl-1@brevo>'], 201),
            'https://api.brevo.com/v3/contacts' => Http::response(['id' => 42], 201),
            'https://api.brevo.com/v3/contacts/*' => Http::response([], 204),
        ]);

        $admin = $this->makeAdmin();
        $this->actingAs($admin)->post('/customers', [
            'full_name' => 'Walk In Regular',
            'email' => 'regular@example.ph',
            'phone' => '+63 917 000 1111',
        ])->assertRedirect();

        $this->assertSame(0, MarketingSubscriber::count());

        $this->post('/newsletter/subscribe', [
            'company' => 'Bot LLC',
            'newsletter_email' => 'bot@example.ph',
            'newsletter_consent' => '1',
        ])->assertRedirect(route('home').'#newsletter');
        $this->assertSame(0, MarketingSubscriber::count());

        $this->post('/newsletter/subscribe', [
            'newsletter_name' => 'Elena Cruz',
            'newsletter_email' => 'elena@example.ph',
            'newsletter_consent' => '1',
        ])->assertRedirect(route('home').'#newsletter');

        $subscriber = MarketingSubscriber::where('email', 'elena@example.ph')->firstOrFail();
        $this->assertSame(MarketingSubscriber::STATUS_PENDING, $subscriber->status);

        $rawConfirm = Str::random(64);
        $rawUnsub = Str::random(64);
        $subscriber->update([
            'confirm_token_hash' => hash('sha256', $rawConfirm),
            'confirm_expires_at' => now('UTC')->addHours(48),
            'unsubscribe_token_hash' => hash('sha256', $rawUnsub),
        ]);

        $this->get('/newsletter/confirm/'.$rawConfirm)->assertOk()->assertSee('Subscription Confirmed');

        $subscriber->refresh();
        $this->assertSame(MarketingSubscriber::STATUS_SUBSCRIBED, $subscriber->status);
        $this->assertNotNull($subscriber->confirmed_at);

        Http::assertSent(function (ClientRequest $request): bool {
            if ($request->url() !== 'https://api.brevo.com/v3/contacts' || $request->method() !== 'POST') {
                return false;
            }
            $data = $request->data();

            return ($data['email'] ?? null) === 'elena@example.ph'
                && ($data['updateEnabled'] ?? false) === true
                && ($data['listIds'] ?? []) === [9]
                && ! array_key_exists('emailBlacklisted', $data);
        });

        $this->get('/newsletter/unsubscribe/'.$rawUnsub)->assertOk()->assertSee('Unsubscribed Successfully');

        $subscriber->refresh();
        $this->assertSame(MarketingSubscriber::STATUS_UNSUBSCRIBED, $subscriber->status);

        Http::assertSent(function (ClientRequest $request): bool {
            return str_contains($request->url(), '/v3/contacts/elena%40example.ph')
                && $request->method() === 'PUT'
                && ($request['emailBlacklisted'] ?? false) === true;
        });

        $subscriber->update(['status' => MarketingSubscriber::STATUS_SUPPRESSED, 'suppressed_at' => now('UTC')]);
        $this->post('/newsletter/subscribe', [
            'newsletter_email' => 'elena@example.ph',
            'newsletter_consent' => '1',
        ])->assertRedirect(route('home').'#newsletter');
        $subscriber->refresh();
        $this->assertSame(MarketingSubscriber::STATUS_SUPPRESSED, $subscriber->status);
    }

    public function test_promotional_campaign_draft_test_send_live_guard_and_dispatch(): void
    {
        $admin = $this->makeAdmin();

        MarketingSubscriber::create([
            'email' => 'fan@example.ph',
            'name' => 'Coffee Fan',
            'status' => MarketingSubscriber::STATUS_SUBSCRIBED,
            'consent_source' => 'landing_newsletter_form',
            'consent_notice_version' => '2026-11-v1',
            'unsubscribe_token_hash' => hash('sha256', 'fan-unsub'),
            'confirmed_at' => now('UTC'),
        ]);

        $this->actingAs($admin)->post('/admin/email/campaigns', [
            'name' => 'Holiday Espresso Release',
            'subject' => 'Reserve our new Holiday Single-Origin',
            'preview_text' => 'Limited small-batch roast now pouring',
            'body_html' => '<p>Join us this weekend for our new single-origin flight.</p>',
        ])->assertRedirect();

        $campaign = MarketingCampaign::firstOrFail();
        $this->actingAs($admin)->get('/admin/email/campaigns/'.$campaign->id.'/preview')
            ->assertOk()
            ->assertSee('Reserve our new Holiday Single-Origin');

        Http::fake([
            'https://api.brevo.com/v3/emailCampaigns' => Http::response(['id' => 777], 201),
            'https://api.brevo.com/v3/emailCampaigns/777/sendTest' => Http::response([], 204),
            'https://api.brevo.com/v3/emailCampaigns/777/sendNow' => Http::response([], 204),
        ]);

        config(['services.brevo.live_send_enabled' => false]);
        $this->actingAs($admin)->post('/admin/email/campaigns/'.$campaign->id.'/send')
            ->assertSessionHasErrors('campaign');

        config(['services.brevo.live_send_enabled' => true]);

        $this->actingAs($admin)->post('/admin/email/campaigns/'.$campaign->id.'/send-test', [
            'test_email' => 'qa@artisanbrewco.ph',
        ])->assertRedirect();

        $this->actingAs($admin)->post('/admin/email/campaigns/'.$campaign->id.'/send')
            ->assertRedirect();

        $campaign->refresh();
        $this->assertSame(777, (int) $campaign->provider_campaign_id);
        $this->assertSame('sent', $campaign->status);
        $this->assertNotNull($campaign->sent_at);

        Http::assertSent(fn (ClientRequest $req) => $req->url() === 'https://api.brevo.com/v3/emailCampaigns/777/sendTest');
        Http::assertSent(fn (ClientRequest $req) => $req->url() === 'https://api.brevo.com/v3/emailCampaigns/777/sendNow');
    }
}
