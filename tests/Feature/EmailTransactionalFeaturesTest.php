<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Customer;
use App\Models\DailySalesReport;
use App\Models\EmailDelivery;
use App\Models\MarketingSubscriber;
use App\Models\Order;
use App\Models\Product;
use App\Models\StaffInvitation;
use App\Models\User;
use App\Services\CheckoutService;
use App\Services\DashboardMetricsService;
use App\Services\Email\DailySalesSummaryService;
use App\Support\BusinessTime;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Tests\TestCase;

class EmailTransactionalFeaturesTest extends TestCase
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
        ]);
    }

    private function makeStaff(string $username, string $role = User::ROLE_ADMIN, bool $active = true): User
    {
        $user = new User([
            'username' => $username,
            'full_name' => ucfirst($username).' Staff',
            'email' => $username.'@artisanbrewco.ph',
            'password' => Hash::make('Secret@123'),
        ]);
        $user->role = $role;
        $user->is_active = $active;
        $user->email_verified_at = now('UTC');
        $user->save();

        return $user;
    }

    private function makeProduct(string $name, string $sku, int $stock = 20, int $threshold = 5, bool $active = true): Product
    {
        $category = Category::firstOrCreate(
            ['slug' => 'espresso'],
            ['name' => 'Espresso Bar', 'sort_order' => 1, 'is_active' => true]
        );

        return Product::create([
            'category_id' => $category->id,
            'name' => $name,
            'sku' => $sku,
            'price' => '180.00',
            'stock_quantity' => $stock,
            'low_stock_threshold' => $threshold,
            'is_active' => $active,
        ]);
    }

    public function test_customer_receipt_at_checkout_resend_signed_link_and_explicit_opt_in_only(): void
    {
        Http::fake([
            'https://api.brevo.com/v3/smtp/email' => Http::response(['messageId' => '<rcpt-1@brevo>'], 201),
        ]);

        $cashier = $this->makeStaff('barista_rcpt', User::ROLE_CASHIER);
        $product = $this->makeProduct('Spanish Latte', 'ABC-LAT-01');

        $response = $this->actingAs($cashier)->postJson('/pos/checkout', [
            'checkout_token' => (string) Str::uuid(),
            'order_type' => 'take_out',
            'payment_method' => 'cash',
            'cash_received' => '200.00',
            'receipt_email' => 'guest@example.ph',
            'marketing_opt_in' => false,
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1],
            ],
        ]);

        $response->assertOk()->assertJsonPath('receipt_email_status', EmailDelivery::STATUS_ACCEPTED);
        $this->assertSame(0, MarketingSubscriber::count());

        $order = Order::firstOrFail();
        $delivery = EmailDelivery::where('purpose', 'receipt')->firstOrFail();
        $this->assertSame('guest@example.ph', $delivery->recipient_email);
        $payload = json_decode(Crypt::decryptString($delivery->payload_encrypted), true);
        $this->assertStringContainsString($order->order_number, $payload['htmlContent']);
        $this->assertStringContainsString('₱180.00', $payload['htmlContent']);

        $signedUrl = URL::temporarySignedRoute('receipts.signed', now()->addDays(7), ['order' => $order->id]);
        $this->get($signedUrl)->assertOk()->assertSee($order->order_number);
        $this->get($signedUrl.'tamper')->assertStatus(403);

        $this->actingAs($cashier)->post('/orders/'.$order->id.'/email-receipt', [
            'receipt_email' => 'guest2@example.ph',
        ])->assertSessionHasErrors('confirm_address');

        $this->actingAs($cashier)->post('/orders/'.$order->id.'/email-receipt', [
            'receipt_email' => 'guest2@example.ph',
            'confirm_address' => '1',
            'marketing_opt_in' => '1',
        ])->assertRedirect();

        $this->assertDatabaseHas('marketing_subscribers', [
            'email' => 'guest2@example.ph',
            'status' => MarketingSubscriber::STATUS_PENDING,
            'consent_source' => 'order_receipt_opt_in',
        ]);
    }

    public function test_staff_password_recovery_and_security_alert(): void
    {
        Http::fake([
            'https://api.brevo.com/v3/smtp/email' => Http::response(['messageId' => '<pw-1@brevo>'], 201),
        ]);

        $staff = $this->makeStaff('barista_pw', User::ROLE_CASHIER);
        DB::table('sessions')->insert([
            'id' => 'old-session-id',
            'user_id' => $staff->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Test',
            'payload' => base64_encode('test'),
            'last_activity' => time(),
        ]);

        $this->post('/forgot-password', ['email' => $staff->email])->assertSessionHas('status');

        $resetDelivery = EmailDelivery::where('purpose', 'password_reset')->firstOrFail();
        $this->assertSame('emails-priority', $resetDelivery->queue_name);
        $this->assertNull($resetDelivery->payload_encrypted);

        $rawToken = app('auth.password.broker')->createToken($staff);

        $this->post('/reset-password', [
            'token' => $rawToken,
            'email' => $staff->email,
            'password' => 'NewSecret@123',
            'password_confirmation' => 'NewSecret@123',
        ])->assertRedirect('/login');

        $staff->refresh();
        $this->assertTrue(Hash::check('NewSecret@123', $staff->password));
        $this->assertSame(0, DB::table('sessions')->where('user_id', $staff->id)->count());
        $this->assertDatabaseHas('email_deliveries', [
            'purpose' => 'security_alert',
            'recipient_email' => $staff->email,
        ]);
    }

    public function test_staff_invitation_lifecycle_blocks_reset_until_accepted_and_activates_account(): void
    {
        Http::fake([
            'https://api.brevo.com/v3/smtp/email' => Http::response(['messageId' => '<inv-1@brevo>'], 201),
        ]);

        $admin = $this->makeStaff('admin_inv', User::ROLE_ADMIN);

        $this->actingAs($admin)->post('/staff', [
            'username' => 'new_barista',
            'full_name' => 'New Barista',
            'email' => 'newbarista@artisanbrewco.ph',
            'role' => User::ROLE_CASHIER,
            'invite_mode' => '1',
        ])->assertRedirect('/staff');

        $invited = User::where('username', 'new_barista')->firstOrFail();
        $this->assertFalse($invited->is_active);
        $this->assertFalse($invited->canReceivePasswordReset());

        $this->post('/logout')->assertRedirect('/login');
        $this->post('/forgot-password', ['email' => $invited->email])->assertSessionHas('status');
        $this->assertSame(0, EmailDelivery::where('purpose', 'password_reset')->where('recipient_email', $invited->email)->count());

        $invitation = StaffInvitation::where('user_id', $invited->id)->firstOrFail();
        $rawToken = Str::random(64);
        $invitation->update(['token_hash' => hash('sha256', $rawToken)]);

        $this->get('/invitations/'.$rawToken)->assertOk()->assertSee('New Barista');

        $this->post('/invitations/'.$rawToken, [
            'password' => 'BaristaPass@123',
            'password_confirmation' => 'BaristaPass@123',
        ])->assertRedirect('/login');

        $invited->refresh();
        $invitation->refresh();
        $this->assertTrue($invited->is_active);
        $this->assertSame('accepted', $invitation->status);
        $this->assertTrue(Hash::check('BaristaPass@123', $invited->password));

        $this->get('/invitations/'.$rawToken)->assertOk()->assertSee('accepted');
    }

    public function test_low_stock_and_sold_out_alerts_trigger_only_on_transitions_and_rearm_on_restock(): void
    {
        Http::fake([
            'https://api.brevo.com/v3/smtp/email' => Http::response(['messageId' => '<stk-1@brevo>'], 201),
        ]);

        $admin = $this->makeStaff('owner_stk', User::ROLE_ADMIN);
        $beans = $this->makeProduct('Single Origin Pour', 'ABC-POUR-1', 6, 5, true);
        $archived = $this->makeProduct('Archived Brew', 'ABC-ARC-1', 6, 5, true);
        $archived->delete();

        $checkout = app(CheckoutService::class);
        $checkout->complete($admin, [
            'checkout_token' => (string) Str::uuid(),
            'payment_method' => 'cash',
            'cash_received' => '360.00',
            'items' => [['product_id' => $beans->id, 'quantity' => 2]],
        ]);

        $this->assertSame(1, EmailDelivery::where('purpose', 'low_stock_alert')->count());

        $checkout->complete($admin, [
            'checkout_token' => (string) Str::uuid(),
            'payment_method' => 'cash',
            'cash_received' => '180.00',
            'items' => [['product_id' => $beans->id, 'quantity' => 1]],
        ]);

        $this->assertSame(1, EmailDelivery::where('purpose', 'low_stock_alert')->count());

        $checkout->complete($admin, [
            'checkout_token' => (string) Str::uuid(),
            'payment_method' => 'cash',
            'cash_received' => '540.00',
            'items' => [['product_id' => $beans->id, 'quantity' => 3]],
        ]);

        $this->assertSame(2, EmailDelivery::where('purpose', 'low_stock_alert')->count());

        $beans->refresh();
        $this->actingAs($admin)->post('/products/'.$beans->id.'/adjust-stock', [
            'delta' => 10,
            'stock_version' => $beans->stock_version,
        ])->assertRedirect();

        $beans->refresh();
        $this->assertSame('normal', $beans->stock_alert_state);

        $checkout->complete($admin, [
            'checkout_token' => (string) Str::uuid(),
            'payment_method' => 'cash',
            'cash_received' => '1440.00',
            'items' => [['product_id' => $beans->id, 'quantity' => 8]],
        ]);

        $this->assertSame(3, EmailDelivery::where('purpose', 'low_stock_alert')->count());
    }

    public function test_daily_sales_summary_matches_manila_metrics_excludes_voided_attaches_csv_and_deduplicates(): void
    {
        Http::fake([
            'https://api.brevo.com/v3/smtp/email' => Http::response(['messageId' => '<sum-1@brevo>'], 201),
        ]);

        $admin = $this->makeStaff('owner_sum', User::ROLE_ADMIN);
        $espresso = $this->makeProduct('Flat White', 'ABC-FW-1', 20, 3, true);

        $checkout = app(CheckoutService::class);
        $checkout->complete($admin, [
            'checkout_token' => (string) Str::uuid(),
            'payment_method' => 'cash',
            'cash_received' => '400.00',
            'items' => [['product_id' => $espresso->id, 'quantity' => 2]],
        ]);

        $voided = $checkout->complete($admin, [
            'checkout_token' => (string) Str::uuid(),
            'payment_method' => 'cash',
            'cash_received' => '200.00',
            'items' => [['product_id' => $espresso->id, 'quantity' => 1]],
        ])->order;
        $voided->forceFill(['status' => 'voided', 'voided_at' => now('UTC')])->save();

        $summary = app(DailySalesSummaryService::class)->computeSummary();
        $this->assertSame(1, $summary['orderCount']);
        $this->assertSame(2, $summary['unitsSold']);
        $this->assertSame(36000, $summary['revenueCentavos']);

        $todayManila = BusinessTime::nowManila()->toDateString();
        $metrics = app(DashboardMetricsService::class)->build($todayManila, $todayManila);
        $this->assertSame($summary['revenueCentavos'], $metrics['today']['revenue_centavos']);

        $this->artisan('abc:emails-daily-summary', ['--date' => $summary['businessDateIso']])
            ->assertExitCode(0);
        $this->artisan('abc:emails-daily-summary', ['--date' => $summary['businessDateIso']])
            ->assertExitCode(0);

        $this->assertSame(1, DailySalesReport::count());
        $this->assertSame(1, EmailDelivery::where('purpose', 'daily_summary')->count());

        Http::assertSent(function ($request): bool {
            $attachment = $request['attachment'][0] ?? null;
            return $attachment !== null && str_ends_with($attachment['name'], '.csv') && base64_decode($attachment['content'], true) !== false;
        });
    }

    public function test_account_security_alerts_and_email_verification_flow(): void
    {
        Http::fake([
            'https://api.brevo.com/v3/smtp/email' => Http::response(['messageId' => '<sec-1@brevo>'], 201),
        ]);

        $admin = $this->makeStaff('admin_sec', User::ROLE_ADMIN);
        $cashier = $this->makeStaff('cashier_sec', User::ROLE_CASHIER);

        $this->actingAs($admin)->put('/staff/'.$cashier->id, [
            'username' => $cashier->username,
            'full_name' => $cashier->full_name,
            'email' => $cashier->email,
            'role' => User::ROLE_ADMIN,
            'is_active' => '1',
        ])->assertRedirect('/staff');

        $this->assertDatabaseHas('email_deliveries', [
            'purpose' => 'security_alert',
            'recipient_email' => $cashier->email,
        ]);

        $this->actingAs($cashier)->put('/profile', [
            'full_name' => $cashier->full_name,
            'email' => 'updated_cashier@artisanbrewco.ph',
        ])->assertRedirect('/profile');

        $cashier->refresh();
        $this->assertSame('cashier_sec@artisanbrewco.ph', $cashier->email);
        $this->assertSame('updated_cashier@artisanbrewco.ph', $cashier->pending_email);

        $rawVerify = Str::random(64);
        $cashier->forceFill([
            'pending_email_token_hash' => hash('sha256', $rawVerify),
            'pending_email_expires_at' => now('UTC')->addHours(2),
        ])->save();

        $this->actingAs($cashier)->get('/profile/verify-email/'.$rawVerify)->assertRedirect('/profile');

        $cashier->refresh();
        $this->assertSame('updated_cashier@artisanbrewco.ph', $cashier->email);
        $this->assertNull($cashier->pending_email);
    }
}
