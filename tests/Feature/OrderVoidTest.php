<?php

namespace Tests\Feature;

use App\Models\GiftCard;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\CheckoutService;
use App\Services\DashboardMetricsService;
use App\Support\BusinessTime;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class OrderVoidTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(string $username, string $role, bool $isActive = true): User
    {
        $user = new User([
            'username' => $username,
            'full_name' => ucfirst($username).' User',
            'password' => Hash::make('Pass@1234'),
        ]);
        $user->role = $role;
        $user->is_active = $isActive;
        $user->save();

        return $user;
    }

    public function test_admin_can_void_completed_order_restoring_product_stock_and_gift_card_balance(): void
    {
        $admin = $this->makeUser('admin_void', User::ROLE_ADMIN);
        $cashier = $this->makeUser('cashier_sale', User::ROLE_CASHIER);

        $product = Product::create([
            'name' => 'Cold Brew',
            'price' => '150.00',
            'stock_quantity' => 10,
            'low_stock_threshold' => 2,
            'is_active' => true,
        ]);

        $giftCard = GiftCard::create([
            'code' => 'AB-GIFT-100',
            'initial_balance' => '200.00',
            'remaining_balance' => '200.00',
            'is_active' => true,
        ]);

        $order = app(CheckoutService::class)->complete($cashier, [
            'checkout_token' => Str::uuid()->toString(),
            'discount_type' => 'giftcard',
            'gift_card_code' => 'AB-GIFT-100',
            'cash_received' => '100.00',
            'items' => [
                ['product_id' => $product->id, 'quantity' => 2],
            ],
        ])->order;

        $this->assertSame(8, $product->fresh()->stock_quantity);
        $stockVersionAfterCheckout = $product->fresh()->stock_version;
        $this->assertSame('0.00', (string) $giftCard->fresh()->remaining_balance);

        $response = $this->actingAs($admin)->post(route('orders.void', $order), [
            'void_reason' => 'Customer cancelled order',
        ]);

        $response->assertRedirect(route('orders.show', $order));

        $order->refresh();
        $this->assertSame('voided', $order->status);
        $this->assertNotNull($order->voided_at);
        $this->assertSame($admin->id, (int) $order->voided_by);
        $this->assertSame('Customer cancelled order', $order->void_reason);

        $product->refresh();
        $this->assertSame(10, $product->stock_quantity);
        $this->assertSame($stockVersionAfterCheckout + 1, $product->stock_version);

        $this->assertSame('200.00', (string) $giftCard->fresh()->remaining_balance);
    }

    public function test_cashier_can_void_own_order_with_valid_admin_override_credentials_recording_approving_admin(): void
    {
        $admin = $this->makeUser('manager_one', User::ROLE_ADMIN);
        $cashier = $this->makeUser('cashier_own', User::ROLE_CASHIER);

        $product = Product::create([
            'name' => 'Americano',
            'price' => '120.00',
            'stock_quantity' => 15,
            'low_stock_threshold' => 3,
            'is_active' => true,
        ]);

        $order = app(CheckoutService::class)->complete($cashier, [
            'checkout_token' => Str::uuid()->toString(),
            'cash_received' => '200.00',
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1],
            ],
        ])->order;

        $response = $this->actingAs($cashier)->post(route('orders.void', $order), [
            'void_reason' => 'Wrong size entered',
            'admin_username' => 'manager_one',
            'admin_password' => 'Pass@1234',
        ]);

        $response->assertRedirect(route('orders.show', $order));
        $this->assertAuthenticatedAs($cashier);

        $order->refresh();
        $this->assertSame('voided', $order->status);
        $this->assertSame($admin->id, (int) $order->voided_by);
        $this->assertSame('Wrong size entered', $order->void_reason);
        $this->assertSame(15, $product->fresh()->stock_quantity);
    }

    public function test_cashier_void_fails_with_invalid_or_non_admin_credentials_and_already_voided_order_is_rejected(): void
    {
        $admin = $this->makeUser('manager_two', User::ROLE_ADMIN);
        $inactiveAdmin = $this->makeUser('manager_inactive', User::ROLE_ADMIN, false);
        $otherCashier = $this->makeUser('cashier_other', User::ROLE_CASHIER);
        $cashier = $this->makeUser('cashier_main', User::ROLE_CASHIER);

        $product = Product::create([
            'name' => 'Latte',
            'price' => '140.00',
            'stock_quantity' => 10,
            'low_stock_threshold' => 2,
            'is_active' => true,
        ]);

        $order = app(CheckoutService::class)->complete($cashier, [
            'checkout_token' => Str::uuid()->toString(),
            'cash_received' => '200.00',
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1],
            ],
        ])->order;

        $this->actingAs($cashier)->post(route('orders.void', $order), [
            'void_reason' => 'Mistake',
            'admin_username' => 'manager_two',
            'admin_password' => 'WrongPassword',
        ])->assertSessionHasErrors('admin_username');

        $this->actingAs($cashier)->post(route('orders.void', $order), [
            'void_reason' => 'Mistake',
            'admin_username' => $otherCashier->username,
            'admin_password' => 'Pass@1234',
        ])->assertSessionHasErrors('admin_username');

        $this->actingAs($cashier)->post(route('orders.void', $order), [
            'void_reason' => 'Mistake',
            'admin_username' => $inactiveAdmin->username,
            'admin_password' => 'Pass@1234',
        ])->assertSessionHasErrors('admin_username');

        $this->assertSame('completed', $order->fresh()->status);

        $this->actingAs($admin)->post(route('orders.void', $order), [
            'void_reason' => 'Valid admin void',
        ])->assertRedirect(route('orders.show', $order));

        $this->assertSame(10, $product->fresh()->stock_quantity);

        $this->actingAs($admin)->postJson(route('orders.void', $order), [
            'void_reason' => 'Duplicate void attempt',
        ])->assertStatus(422);

        $this->assertSame(10, $product->fresh()->stock_quantity);
    }

    public function test_cashier_cannot_void_another_cashiers_order_even_with_override(): void
    {
        $this->makeUser('manager_three', User::ROLE_ADMIN);
        $cashierA = $this->makeUser('cashier_a', User::ROLE_CASHIER);
        $cashierB = $this->makeUser('cashier_b', User::ROLE_CASHIER);

        $product = Product::create([
            'name' => 'Espresso',
            'price' => '100.00',
            'stock_quantity' => 10,
            'low_stock_threshold' => 2,
            'is_active' => true,
        ]);

        $order = app(CheckoutService::class)->complete($cashierA, [
            'checkout_token' => Str::uuid()->toString(),
            'cash_received' => '100.00',
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1],
            ],
        ])->order;

        $this->actingAs($cashierB)->post(route('orders.void', $order), [
            'void_reason' => 'Unauthorized void',
            'admin_username' => 'manager_three',
            'admin_password' => 'Pass@1234',
        ])->assertForbidden();

        $this->assertSame('completed', $order->fresh()->status);
    }

    public function test_voided_orders_are_excluded_from_dashboard_revenue_and_unit_counts(): void
    {
        $admin = $this->makeUser('admin_metrics', User::ROLE_ADMIN);

        $product = Product::create([
            'name' => 'Mocha',
            'price' => '160.00',
            'stock_quantity' => 20,
            'low_stock_threshold' => 3,
            'is_active' => true,
        ]);

        $checkout = app(CheckoutService::class);

        $keptOrder = $checkout->complete($admin, [
            'checkout_token' => Str::uuid()->toString(),
            'cash_received' => '200.00',
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1],
            ],
        ])->order;

        $voidedOrder = $checkout->complete($admin, [
            'checkout_token' => Str::uuid()->toString(),
            'cash_received' => '400.00',
            'items' => [
                ['product_id' => $product->id, 'quantity' => 2],
            ],
        ])->order;

        $this->actingAs($admin)->post(route('orders.void', $voidedOrder), [
            'void_reason' => 'Duplicate ring',
        ])->assertRedirect(route('orders.show', $voidedOrder));

        $today = BusinessTime::nowManila()->toDateString();
        $metrics = app(DashboardMetricsService::class)->build($today, $today);

        $this->assertSame(1, $metrics['today']['orders_count']);
        $this->assertSame(1, $metrics['today']['units_sold']);
        $this->assertSame('₱160.00', $metrics['today']['revenue_formatted']);
        $this->assertSame(1, $metrics['selected_range']['orders_count']);
        $this->assertSame(1, $metrics['selected_range']['units_sold']);
        $this->assertSame('₱160.00', $metrics['selected_range']['revenue_formatted']);
        $this->assertSame(1, $metrics['top_products'][0]['units_sold']);
        $this->assertCount(1, $metrics['recent_orders']);
        $this->assertSame($keptOrder->id, $metrics['recent_orders'][0]->id);
    }
}
