<?php

namespace Tests\Feature;

use App\Models\Addon;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\CheckoutService;
use App\Services\DashboardMetricsService;
use App\Support\BusinessTime;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class HistoryReceiptAndDashboardTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(string $username, string $role): User
    {
        $user = new User([
            'username' => $username,
            'full_name' => ucfirst($username).' Name',
            'password' => Hash::make('Pass@1234'),
        ]);
        $user->role = $role;
        $user->is_active = true;
        $user->save();

        return $user;
    }

    public function test_receipt_and_history_preserve_snapshots_after_menu_edits_and_soft_deletes_and_reprint_is_idempotent(): void
    {
        $admin = $this->makeUser('admin_hist', User::ROLE_ADMIN);
        $cashier = $this->makeUser('cashier_hist', User::ROLE_CASHIER);
        $customer = Customer::create(['full_name' => 'Gabriel Lim', 'email' => 'gabriel@example.ph']);

        $product = Product::create(['name' => 'Matcha Latte', 'price' => '185.00', 'stock_quantity' => 10, 'low_stock_threshold' => 2, 'is_active' => true]);
        $variant = ProductVariant::create(['product_id' => $product->id, 'size_label' => '16 oz', 'temperature' => 'iced', 'price_adjustment' => '40.00', 'is_active' => true]);
        $addon = Addon::create(['name' => 'Oat Milk', 'price' => '40.00', 'is_active' => true]);
        $product->addons()->sync([$addon->id]);

        $order = app(CheckoutService::class)->complete($cashier, [
            'checkout_token' => Str::uuid()->toString(),
            'customer_id' => $customer->id,
            'cash_received' => '300.00',
            'items' => [[
                'product_id' => $product->id,
                'product_variant_id' => $variant->id,
                'addon_ids' => [$addon->id],
                'quantity' => 1,
            ]],
        ])->order;

        $product->update(['name' => 'Renamed Uji Matcha', 'price' => '250.00']);
        $variant->update(['size_label' => '20 oz', 'price_adjustment' => '90.00']);
        $addon->update(['name' => 'Organic Oat', 'price' => '60.00']);
        $customer->delete();
        $cashier->delete();

        $this->actingAs($admin);

        $receipt = $this->get(route('orders.receipt', $order))->assertOk();
        $receipt->assertSee('Matcha Latte');
        $receipt->assertSee('16 oz · Iced');
        $receipt->assertSee('Oat Milk');
        $receipt->assertSee('₱265.00');
        $receipt->assertSee('Gabriel Lim');
        $receipt->assertSee('Cashier_hist Name');

        $this->get(route('orders.receipt', $order))->assertOk();
        $this->assertSame(1, Order::count());
        $this->assertSame(9, $product->fresh()->stock_quantity);
    }

    public function test_dashboard_metrics_do_not_inflate_order_counts_or_revenue_when_joining_lines_and_addons(): void
    {
        $admin = $this->makeUser('admin_dash', User::ROLE_ADMIN);
        $drink = Product::create(['name' => 'Spanish Latte', 'price' => '175.00', 'stock_quantity' => 20, 'low_stock_threshold' => 5, 'is_active' => true]);
        $pastry = Product::create(['name' => 'Croissant', 'price' => '125.00', 'stock_quantity' => 20, 'low_stock_threshold' => 5, 'is_active' => true]);
        $addonA = Addon::create(['name' => 'Oat Milk', 'price' => '40.00', 'is_active' => true]);
        $addonB = Addon::create(['name' => 'Extra Shot', 'price' => '35.00', 'is_active' => true]);
        $drink->addons()->sync([$addonA->id, $addonB->id]);

        app(CheckoutService::class)->complete($admin, [
            'checkout_token' => Str::uuid()->toString(),
            'cash_received' => '1000.00',
            'items' => [
                ['product_id' => $drink->id, 'addon_ids' => [$addonA->id, $addonB->id], 'quantity' => 2],
                ['product_id' => $pastry->id, 'quantity' => 3],
            ],
        ]);

        $today = BusinessTime::nowManila()->toDateString();
        $metrics = app(DashboardMetricsService::class)->build($today, $today);

        $this->assertSame(1, $metrics['today']['orders_count']);
        $this->assertSame(5, $metrics['today']['units_sold']);
        $this->assertSame('₱875.00', $metrics['today']['revenue_formatted']);
        $this->assertSame('₱875.00', $metrics['today']['average_order_formatted']);

        $this->actingAs($admin);
        $this->get('/dashboard')->assertOk()->assertSee('₱875.00')->assertSee('Inventory Status');
        $this->get('/dashboard/metrics?preset=7d')
            ->assertOk()
            ->assertJsonPath('today.orders_count', 1)
            ->assertJsonPath('today.units_sold', 5)
            ->assertJsonPath('today.revenue_formatted', '₱875.00')
            ->assertJsonPath('inventory_status.active_menu_count', 2)
            ->assertJsonPath('inventory_status.available_count', 2);
        $this->get('/dashboard/metrics?preset=all')
            ->assertOk()
            ->assertJsonPath('selected_range.orders_count', 1);
    }
}
