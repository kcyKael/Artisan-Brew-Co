<?php

namespace Tests\Feature;

use App\Exceptions\CheckoutException;
use App\Models\Addon;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Sale;
use App\Models\SaleAddon;
use App\Models\User;
use App\Services\CheckoutService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class CheckoutAndPricingTest extends TestCase
{
    use RefreshDatabase;

    private function makeCashier(): User
    {
        $user = new User([
            'username' => 'pos_cashier',
            'full_name' => 'POS Cashier',
            'password' => Hash::make('Cashier@123'),
        ]);
        $user->role = User::ROLE_CASHIER;
        $user->is_active = true;
        $user->save();

        return $user;
    }

    public function test_record_sale_single_product_walk_in_customer_and_oversell_rejection(): void
    {
        $cashier = $this->makeCashier();
        $pastry = Product::create([
            'name' => 'Butter Croissant',
            'price' => '125.00',
            'stock_quantity' => 5,
            'low_stock_threshold' => 2,
            'is_active' => true,
        ]);

        $this->actingAs($cashier);

        $this->post('/sales/record', [
            'checkout_token' => Str::uuid()->toString(),
            'product_id' => $pastry->id,
            'quantity' => 6,
            'cash_received' => '1000.00',
        ])->assertSessionHasErrors('quantity');
        $this->assertSame(5, $pastry->fresh()->stock_quantity);
        $this->assertSame(0, Order::count());

        foreach (['0', '-1', '1.5'] as $badQty) {
            $this->post('/sales/record', [
                'checkout_token' => Str::uuid()->toString(),
                'product_id' => $pastry->id,
                'quantity' => $badQty,
                'cash_received' => '500.00',
            ])->assertSessionHasErrors('quantity');
        }

        $response = $this->post('/sales/record', [
            'checkout_token' => Str::uuid()->toString(),
            'product_id' => $pastry->id,
            'quantity' => 2,
            'cash_received' => '300.00',
        ]);
        $order = Order::firstOrFail();
        $response->assertRedirect(route('orders.receipt', $order));

        $this->assertSame(3, $pastry->fresh()->stock_quantity);
        $this->assertSame('Walk-in customer', $order->customer_name_snapshot);
        $this->assertSame('250.00', (string) $order->total_amount);
        $this->assertSame('50.00', (string) $order->change_amount);
        $this->assertSame('record_sale', $order->source);
    }

    public function test_pos_multi_item_variants_addons_server_recalculation_and_split_variant_stock_aggregation(): void
    {
        $cashier = $this->makeCashier();
        $customer = Customer::create([
            'full_name' => 'Maria Santos',
            'email' => 'maria@example.ph',
        ]);

        $latte = Product::create([
            'name' => 'Spanish Latte',
            'price' => '175.00',
            'stock_quantity' => 5,
            'low_stock_threshold' => 2,
            'is_active' => true,
        ]);

        $hotRegular = ProductVariant::create([
            'product_id' => $latte->id,
            'size_label' => '12 oz',
            'temperature' => 'hot',
            'price_adjustment' => '0.00',
            'is_active' => true,
        ]);
        $icedLarge = ProductVariant::create([
            'product_id' => $latte->id,
            'size_label' => '16 oz',
            'temperature' => 'iced',
            'price_adjustment' => '40.00',
            'is_active' => true,
        ]);

        $oatMilk = Addon::create(['name' => 'Oat Milk', 'price' => '40.00', 'is_active' => true]);
        $extraShot = Addon::create(['name' => 'Extra Shot', 'price' => '35.00', 'is_active' => true]);
        $ineligibleAddon = Addon::create(['name' => 'Chocolate Syrup', 'price' => '25.00', 'is_active' => true]);
        $latte->addons()->sync([$oatMilk->id, $extraShot->id]);

        $this->actingAs($cashier);

        $this->postJson('/pos/checkout', [
            'checkout_token' => Str::uuid()->toString(),
            'customer_id' => $customer->id,
            'cash_received' => '2000.00',
            'items' => [
                ['product_id' => $latte->id, 'product_variant_id' => $hotRegular->id, 'addon_ids' => [], 'quantity' => 3],
                ['product_id' => $latte->id, 'product_variant_id' => $icedLarge->id, 'addon_ids' => [$oatMilk->id], 'quantity' => 3],
            ],
        ])->assertStatus(422);
        $this->assertSame(5, $latte->fresh()->stock_quantity);
        $this->assertSame(0, Order::count());

        $this->postJson('/pos/checkout', [
            'checkout_token' => Str::uuid()->toString(),
            'cash_received' => '500.00',
            'items' => [
                ['product_id' => $latte->id, 'product_variant_id' => $hotRegular->id, 'addon_ids' => [$ineligibleAddon->id], 'quantity' => 1],
            ],
        ])->assertStatus(422);

        $this->postJson('/pos/checkout', [
            'checkout_token' => Str::uuid()->toString(),
            'cash_received' => '500.00',
            'items' => [
                ['product_id' => $latte->id, 'product_variant_id' => $hotRegular->id, 'addon_ids' => [$oatMilk->id, $oatMilk->id], 'quantity' => 1],
            ],
        ])->assertStatus(422);

        $this->postJson('/pos/checkout', [
            'checkout_token' => Str::uuid()->toString(),
            'cash_received' => '100.00',
            'items' => [
                ['product_id' => $latte->id, 'product_variant_id' => $icedLarge->id, 'addon_ids' => [$oatMilk->id], 'quantity' => 1],
            ],
        ])->assertStatus(422);

        $token = Str::uuid()->toString();
        $res = $this->postJson('/pos/checkout', [
            'checkout_token' => $token,
            'customer_id' => $customer->id,
            'cash_received' => '1500.00',
            'total_amount' => '1.00',
            'items' => [
                ['product_id' => $latte->id, 'product_variant_id' => $hotRegular->id, 'addon_ids' => [], 'quantity' => 2, 'unit_price' => 1],
                ['product_id' => $latte->id, 'product_variant_id' => $icedLarge->id, 'addon_ids' => [$oatMilk->id, $extraShot->id], 'quantity' => 3],
            ],
        ])->assertOk();

        $res->assertJsonPath('order.total_formatted', '₱1,220.00');
        $res->assertJsonPath('order.change_formatted', '₱280.00');
        $this->assertSame(0, $latte->fresh()->stock_quantity);
        $this->assertSame(1, Order::count());
        $this->assertSame(2, Sale::count());
        $this->assertSame(2, SaleAddon::count());

        $replay = $this->postJson('/pos/checkout', [
            'checkout_token' => $token,
            'customer_id' => $customer->id,
            'cash_received' => '1500.00',
            'items' => [
                ['product_id' => $latte->id, 'product_variant_id' => $hotRegular->id, 'addon_ids' => [], 'quantity' => 2],
                ['product_id' => $latte->id, 'product_variant_id' => $icedLarge->id, 'addon_ids' => [$oatMilk->id, $extraShot->id], 'quantity' => 3],
            ],
        ])->assertOk();
        $replay->assertJsonPath('was_replay', true);
        $this->assertSame(1, Order::count());

        $this->postJson('/pos/checkout', [
            'checkout_token' => $token,
            'customer_id' => null,
            'cash_received' => '500.00',
            'items' => [
                ['product_id' => $latte->id, 'product_variant_id' => $hotRegular->id, 'addon_ids' => [], 'quantity' => 1],
            ],
        ])->assertStatus(409);
    }

    public function test_failed_second_item_or_database_write_rolls_back_entire_order_cleanly(): void
    {
        $cashier = $this->makeCashier();
        $first = Product::create(['name' => 'Cookie', 'price' => '100.00', 'stock_quantity' => 10, 'low_stock_threshold' => 2, 'is_active' => true]);
        $second = Product::create(['name' => 'Brownie', 'price' => '120.00', 'stock_quantity' => 1, 'low_stock_threshold' => 2, 'is_active' => true]);

        $this->actingAs($cashier)->postJson('/pos/checkout', [
            'checkout_token' => Str::uuid()->toString(),
            'cash_received' => '500.00',
            'items' => [
                ['product_id' => $first->id, 'quantity' => 2],
                ['product_id' => $second->id, 'quantity' => 2],
            ],
        ])->assertStatus(422);

        $this->assertSame(10, $first->fresh()->stock_quantity);
        $this->assertSame(1, $second->fresh()->stock_quantity);
        $this->assertSame(0, Order::count());
        $this->assertSame(0, Sale::count());

        SaleAddon::creating(function () {
            throw new \RuntimeException('Simulated database write failure on sale_addons');
        });

        $addon = Addon::create(['name' => 'Caramel', 'price' => '25.00', 'is_active' => true]);
        $first->addons()->sync([$addon->id]);

        try {
            app(CheckoutService::class)->complete($cashier, [
                'checkout_token' => Str::uuid()->toString(),
                'cash_received' => '500.00',
                'items' => [
                    ['product_id' => $first->id, 'addon_ids' => [$addon->id], 'quantity' => 2],
                ],
            ]);
            $this->fail('Expected RuntimeException to abort checkout.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('Simulated database write failure', $e->getMessage());
        } finally {
            SaleAddon::flushEventListeners();
        }

        $this->assertSame(10, $first->fresh()->stock_quantity);
        $this->assertSame(0, Order::count());
        $this->assertSame(0, Sale::count());
        $this->assertSame(0, SaleAddon::count());
    }
}
