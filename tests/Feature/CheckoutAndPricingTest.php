<?php

namespace Tests\Feature;

use App\Exceptions\CheckoutException;
use App\Models\Addon;
use App\Models\Customer;
use App\Models\GiftCard;
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
use Illuminate\Support\Facades\Schema;
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

    public function test_orders_sales_and_gift_cards_schema_supports_new_pos_fields(): void
    {
        $this->assertTrue(Schema::hasTable('gift_cards'));
        $this->assertTrue(Schema::hasColumns('gift_cards', [
            'id',
            'code',
            'initial_balance',
            'remaining_balance',
            'is_active',
            'expires_at',
            'created_at',
            'updated_at',
        ]));

        $this->assertTrue(Schema::hasColumns('orders', [
            'order_type',
            'payment_method',
            'payment_reference',
            'cup_name',
            'order_note',
            'discount_type',
            'discount_label',
            'discount_amount',
            'gift_card_id',
            'status',
            'voided_at',
            'voided_by',
            'void_reason',
        ]));

        $this->assertTrue(Schema::hasColumn('sales', 'note'));

        $activeCard = GiftCard::create([
            'code' => 'ABC-TEST-500',
            'initial_balance' => '500.00',
            'remaining_balance' => '500.00',
            'is_active' => true,
            'expires_at' => null,
        ]);
        $this->assertTrue($activeCard->isUsable());

        $futureCard = GiftCard::create([
            'code' => 'ABC-TEST-FUTURE',
            'initial_balance' => '100.00',
            'remaining_balance' => '50.00',
            'is_active' => true,
            'expires_at' => now()->addDay(),
        ]);
        $this->assertTrue($futureCard->isUsable());

        $inactiveCard = GiftCard::create([
            'code' => 'ABC-TEST-INACTIVE',
            'initial_balance' => '100.00',
            'remaining_balance' => '100.00',
            'is_active' => false,
            'expires_at' => null,
        ]);
        $this->assertFalse($inactiveCard->isUsable());

        $emptyCard = GiftCard::create([
            'code' => 'ABC-TEST-EMPTY',
            'initial_balance' => '100.00',
            'remaining_balance' => '0.00',
            'is_active' => true,
            'expires_at' => null,
        ]);
        $this->assertFalse($emptyCard->isUsable());

        $expiredCard = GiftCard::create([
            'code' => 'ABC-TEST-EXPIRED',
            'initial_balance' => '100.00',
            'remaining_balance' => '100.00',
            'is_active' => true,
            'expires_at' => now()->subMinute(),
        ]);
        $this->assertFalse($expiredCard->isUsable());

        $cashier = $this->makeCashier();
        $voidAdmin = new User([
            'username' => 'void_admin',
            'full_name' => 'Void Admin',
            'password' => Hash::make('Admin@123'),
        ]);
        $voidAdmin->role = User::ROLE_ADMIN;
        $voidAdmin->is_active = true;
        $voidAdmin->save();
        $voidAdmin->delete();

        $order = Order::create([
            'order_number' => 'ABC-TEST-000001',
            'sold_by' => $cashier->id,
            'customer_name_snapshot' => 'Walk-in customer',
            'staff_name_snapshot' => $cashier->full_name,
            'source' => 'pos',
            'order_type' => 'take_out',
            'payment_method' => 'gcash',
            'payment_reference' => 'GC-123456',
            'cup_name' => 'Alex',
            'order_note' => 'Pack separately',
            'subtotal_amount' => '200.00',
            'discount_type' => 'giftcard',
            'discount_label' => 'Gift Card ABC-TEST-500',
            'discount_amount' => '100.00',
            'gift_card_id' => $activeCard->id,
            'total_amount' => '100.00',
            'cash_received' => '100.00',
            'change_amount' => '0.00',
            'status' => 'voided',
            'voided_at' => now(),
            'voided_by' => $voidAdmin->id,
            'void_reason' => 'Customer changed order',
            'checkout_token' => Str::uuid()->toString(),
            'request_hash' => hash('sha256', 'schema-test-order'),
        ]);

        $this->assertSame($activeCard->id, $order->giftCard?->id);
        $this->assertSame($voidAdmin->id, $order->voidedBy?->id);
        $this->assertSame(1, $activeCard->orders()->count());

        $product = Product::create([
            'name' => 'Americano',
            'price' => '130.00',
            'stock_quantity' => 10,
            'low_stock_threshold' => 2,
            'is_active' => true,
        ]);

        $sale = Sale::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'sold_by' => $cashier->id,
            'product_name_snapshot' => 'Americano',
            'base_price_snapshot' => '130.00',
            'variant_adjustment_snapshot' => '0.00',
            'addons_total_snapshot' => '0.00',
            'unit_price' => '130.00',
            'quantity' => 1,
            'total_price' => '130.00',
            'note' => 'Less ice',
        ]);
        $this->assertSame('Less ice', $sale->fresh()->note);
    }

    public function test_card_and_gcash_payments_require_no_cash_tender_and_record_reference(): void
    {
        $cashier = $this->makeCashier();
        $latte = Product::create([
            'name' => 'Latte',
            'price' => '150.00',
            'stock_quantity' => 10,
            'low_stock_threshold' => 2,
            'is_active' => true,
        ]);

        $this->actingAs($cashier);

        $cardRes = $this->postJson('/pos/checkout', [
            'checkout_token' => Str::uuid()->toString(),
            'payment_method' => 'card',
            'payment_reference' => 'VISA-4242',
            'items' => [
                ['product_id' => $latte->id, 'quantity' => 2],
            ],
        ])->assertOk();

        $cardRes->assertJsonPath('order.payment_method', 'card');
        $cardRes->assertJsonPath('order.total_formatted', '₱300.00');
        $cardOrder = Order::latest('id')->firstOrFail();
        $this->assertSame('card', $cardOrder->payment_method);
        $this->assertSame('VISA-4242', $cardOrder->payment_reference);
        $this->assertSame('300.00', (string) $cardOrder->cash_received);
        $this->assertSame('0.00', (string) $cardOrder->change_amount);

        $this->postJson('/pos/checkout', [
            'checkout_token' => Str::uuid()->toString(),
            'payment_method' => 'gcash',
            'payment_reference' => 'GC-998877',
            'items' => [
                ['product_id' => $latte->id, 'quantity' => 1],
            ],
        ])->assertOk();

        $gcashOrder = Order::latest('id')->firstOrFail();
        $this->assertSame('gcash', $gcashOrder->payment_method);
        $this->assertSame('GC-998877', $gcashOrder->payment_reference);
        $this->assertSame('150.00', (string) $gcashOrder->cash_received);
        $this->assertSame('0.00', (string) $gcashOrder->change_amount);

        $this->postJson('/pos/checkout', [
            'checkout_token' => Str::uuid()->toString(),
            'payment_method' => 'cash',
            'payment_reference' => 'SHOULD-BE-NULL',
            'cash_received' => '200.00',
            'items' => [
                ['product_id' => $latte->id, 'quantity' => 1],
            ],
        ])->assertOk();

        $cashOrder = Order::latest('id')->firstOrFail();
        $this->assertSame('cash', $cashOrder->payment_method);
        $this->assertNull($cashOrder->payment_reference);
        $this->assertSame('200.00', (string) $cashOrder->cash_received);
        $this->assertSame('50.00', (string) $cashOrder->change_amount);

        $this->postJson('/pos/checkout', [
            'checkout_token' => Str::uuid()->toString(),
            'payment_method' => 'cash',
            'items' => [
                ['product_id' => $latte->id, 'quantity' => 1],
            ],
        ])->assertStatus(422);
    }

    public function test_percent_fixed_and_gift_card_discounts_cap_at_subtotal_and_deduct_gift_card_balance(): void
    {
        $cashier = $this->makeCashier();
        $espresso = Product::create([
            'name' => 'Espresso',
            'price' => '100.00',
            'stock_quantity' => 20,
            'low_stock_threshold' => 2,
            'is_active' => true,
        ]);

        $this->actingAs($cashier);

        $pctRes = $this->postJson('/pos/checkout', [
            'checkout_token' => Str::uuid()->toString(),
            'payment_method' => 'card',
            'discount_type' => 'percent',
            'discount_value' => '25',
            'items' => [
                ['product_id' => $espresso->id, 'quantity' => 2],
            ],
        ])->assertOk();

        $pctRes->assertJsonPath('order.discount_label', '25% Off');
        $pctOrder = Order::latest('id')->firstOrFail();
        $this->assertSame('percent', $pctOrder->discount_type);
        $this->assertSame('25% Off', $pctOrder->discount_label);
        $this->assertSame('200.00', (string) $pctOrder->subtotal_amount);
        $this->assertSame('50.00', (string) $pctOrder->discount_amount);
        $this->assertSame('150.00', (string) $pctOrder->total_amount);
        $this->assertSame('150.00', (string) $pctOrder->cash_received);

        $this->postJson('/pos/checkout', [
            'checkout_token' => Str::uuid()->toString(),
            'payment_method' => 'cash',
            'cash_received' => '0.00',
            'discount_type' => 'fixed',
            'discount_value' => '500.00',
            'items' => [
                ['product_id' => $espresso->id, 'quantity' => 1],
            ],
        ])->assertOk();

        $fixedOrder = Order::latest('id')->firstOrFail();
        $this->assertSame('fixed', $fixedOrder->discount_type);
        $this->assertSame('Fixed Discount', $fixedOrder->discount_label);
        $this->assertSame('100.00', (string) $fixedOrder->subtotal_amount);
        $this->assertSame('100.00', (string) $fixedOrder->discount_amount);
        $this->assertSame('0.00', (string) $fixedOrder->total_amount);
        $this->assertSame('0.00', (string) $fixedOrder->change_amount);

        $card = GiftCard::create([
            'code' => 'ABC-GC-300',
            'initial_balance' => '300.00',
            'remaining_balance' => '300.00',
            'is_active' => true,
            'expires_at' => null,
        ]);

        $this->postJson('/pos/checkout', [
            'checkout_token' => Str::uuid()->toString(),
            'payment_method' => 'cash',
            'cash_received' => '0.00',
            'discount_type' => 'giftcard',
            'gift_card_code' => 'abc-gc-300',
            'items' => [
                ['product_id' => $espresso->id, 'quantity' => 2],
            ],
        ])->assertOk();

        $gcOrder1 = Order::latest('id')->firstOrFail();
        $this->assertSame('giftcard', $gcOrder1->discount_type);
        $this->assertSame('Gift Card ABC-GC-300', $gcOrder1->discount_label);
        $this->assertSame('200.00', (string) $gcOrder1->discount_amount);
        $this->assertSame('0.00', (string) $gcOrder1->total_amount);
        $this->assertSame($card->id, $gcOrder1->gift_card_id);
        $this->assertSame('100.00', (string) $card->fresh()->remaining_balance);

        $this->postJson('/pos/checkout', [
            'checkout_token' => Str::uuid()->toString(),
            'payment_method' => 'cash',
            'cash_received' => '150.00',
            'discount_type' => 'giftcard',
            'gift_card_code' => 'ABC-GC-300',
            'items' => [
                ['product_id' => $espresso->id, 'quantity' => 2],
            ],
        ])->assertOk();

        $gcOrder2 = Order::latest('id')->firstOrFail();
        $this->assertSame('100.00', (string) $gcOrder2->discount_amount);
        $this->assertSame('100.00', (string) $gcOrder2->total_amount);
        $this->assertSame('50.00', (string) $gcOrder2->change_amount);
        $this->assertSame('0.00', (string) $card->fresh()->remaining_balance);

        $this->postJson('/pos/checkout', [
            'checkout_token' => Str::uuid()->toString(),
            'payment_method' => 'card',
            'discount_type' => 'giftcard',
            'gift_card_code' => 'ABC-GC-300',
            'items' => [
                ['product_id' => $espresso->id, 'quantity' => 1],
            ],
        ])->assertStatus(422);

        $this->postJson('/pos/checkout', [
            'checkout_token' => Str::uuid()->toString(),
            'payment_method' => 'card',
            'discount_type' => 'giftcard',
            'gift_card_code' => 'NON-EXISTENT',
            'items' => [
                ['product_id' => $espresso->id, 'quantity' => 1],
            ],
        ])->assertStatus(422);
    }

    public function test_walk_in_cup_name_order_type_and_line_notes_persist_on_order_and_sales(): void
    {
        $cashier = $this->makeCashier();
        $customer = Customer::create([
            'full_name' => 'Elena Cruz',
            'email' => 'elena@example.ph',
        ]);
        $matcha = Product::create([
            'name' => 'Matcha',
            'price' => '160.00',
            'stock_quantity' => 10,
            'low_stock_threshold' => 2,
            'is_active' => true,
        ]);

        $this->actingAs($cashier);

        $this->postJson('/pos/checkout', [
            'checkout_token' => Str::uuid()->toString(),
            'order_type' => 'take_out',
            'cup_name' => 'Sam',
            'order_note' => 'Extra napkins',
            'cash_received' => '200.00',
            'items' => [
                ['product_id' => $matcha->id, 'quantity' => 1, 'note' => 'Oat milk, less sweet'],
            ],
        ])->assertOk();

        $walkInOrder = Order::latest('id')->firstOrFail();
        $this->assertSame('take_out', $walkInOrder->order_type);
        $this->assertSame('Sam', $walkInOrder->cup_name);
        $this->assertSame('Extra napkins', $walkInOrder->order_note);
        $this->assertSame('Walk-in · Sam', $walkInOrder->customer_name_snapshot);
        $this->assertSame('completed', $walkInOrder->status);
        $this->assertSame('Oat milk, less sweet', $walkInOrder->sales()->firstOrFail()->note);

        $this->postJson('/pos/checkout', [
            'checkout_token' => Str::uuid()->toString(),
            'customer_id' => $customer->id,
            'cup_name' => 'IgnoredOnSnapshot',
            'cash_received' => '200.00',
            'items' => [
                ['product_id' => $matcha->id, 'quantity' => 1],
            ],
        ])->assertOk();

        $custOrder = Order::latest('id')->firstOrFail();
        $this->assertSame('dine_in', $custOrder->order_type);
        $this->assertSame('Elena Cruz', $custOrder->customer_name_snapshot);
        $this->assertSame('IgnoredOnSnapshot', $custOrder->cup_name);

        $recordRes = $this->post('/sales/record', [
            'checkout_token' => Str::uuid()->toString(),
            'product_id' => $matcha->id,
            'quantity' => 1,
            'order_type' => 'take_out',
            'payment_method' => 'gcash',
            'payment_reference' => 'GC-555',
            'cup_name' => 'Mia',
            'order_note' => 'No straw',
            'discount_type' => 'percent',
            'discount_value' => '10',
            'note' => 'Half sweet',
        ]);
        $recordOrder = Order::latest('id')->firstOrFail();
        $recordRes->assertRedirect(route('orders.receipt', $recordOrder));
        $this->assertSame('take_out', $recordOrder->order_type);
        $this->assertSame('gcash', $recordOrder->payment_method);
        $this->assertSame('GC-555', $recordOrder->payment_reference);
        $this->assertSame('Walk-in · Mia', $recordOrder->customer_name_snapshot);
        $this->assertSame('No straw', $recordOrder->order_note);
        $this->assertSame('16.00', (string) $recordOrder->discount_amount);
        $this->assertSame('144.00', (string) $recordOrder->total_amount);
        $this->assertSame('Half sweet', $recordOrder->sales()->firstOrFail()->note);
    }

    public function test_gift_card_lookup_endpoint_returns_validity_and_balance(): void
    {
        $cashier = $this->makeCashier();

        GiftCard::create([
            'code' => 'ABC-LOOK-250',
            'initial_balance' => '250.00',
            'remaining_balance' => '250.00',
            'is_active' => true,
            'expires_at' => null,
        ]);
        GiftCard::create([
            'code' => 'ABC-LOOK-OFF',
            'initial_balance' => '100.00',
            'remaining_balance' => '100.00',
            'is_active' => false,
            'expires_at' => null,
        ]);
        GiftCard::create([
            'code' => 'ABC-LOOK-EXP',
            'initial_balance' => '100.00',
            'remaining_balance' => '100.00',
            'is_active' => true,
            'expires_at' => now()->subDay(),
        ]);
        GiftCard::create([
            'code' => 'ABC-LOOK-ZERO',
            'initial_balance' => '100.00',
            'remaining_balance' => '0.00',
            'is_active' => true,
            'expires_at' => null,
        ]);

        $this->actingAs($cashier);

        $this->getJson(route('pos.gift-cards.lookup', ['code' => 'abc-look-250']))
            ->assertOk()
            ->assertJson([
                'valid' => true,
                'code' => 'ABC-LOOK-250',
                'remaining_balance' => '250.00',
                'remaining_formatted' => '₱250.00',
            ]);

        $this->getJson(route('pos.gift-cards.lookup', ['code' => 'ABC-LOOK-OFF']))
            ->assertOk()
            ->assertJson(['valid' => false]);

        $this->getJson(route('pos.gift-cards.lookup', ['code' => 'ABC-LOOK-EXP']))
            ->assertOk()
            ->assertJson(['valid' => false]);

        $this->getJson(route('pos.gift-cards.lookup', ['code' => 'ABC-LOOK-ZERO']))
            ->assertOk()
            ->assertJson(['valid' => false]);

        $this->getJson(route('pos.gift-cards.lookup', ['code' => 'NO-SUCH-CARD']))
            ->assertOk()
            ->assertJson([
                'valid' => false,
                'code' => 'NO-SUCH-CARD',
                'remaining_balance' => '0.00',
                'remaining_formatted' => '₱0.00',
            ]);
    }
}
