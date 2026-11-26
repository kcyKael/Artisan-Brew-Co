<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class ConcurrencyCheckoutTest extends TestCase
{
    private string $barrierFile = '';

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('migrate');
        $this->truncatePosTables();
        $this->barrierFile = sys_get_temp_dir().DIRECTORY_SEPARATOR.'abc_barrier_'.Str::random(10);
    }

    protected function tearDown(): void
    {
        @unlink($this->barrierFile);
        $this->truncatePosTables();
        parent::tearDown();
    }

    private function truncatePosTables(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        foreach (['sale_addons', 'sales', 'orders', 'addon_product', 'product_variants', 'products', 'categories', 'addons', 'customers', 'users'] as $table) {
            DB::table($table)->truncate();
        }
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }

    public function test_two_concurrent_connections_competing_for_final_units_only_allow_one_to_succeed(): void
    {
        $cashierA = new User(['username' => 'conc_a', 'full_name' => 'Cashier A', 'password' => Hash::make('Pass@1234')]);
        $cashierA->role = User::ROLE_CASHIER;
        $cashierA->is_active = true;
        $cashierA->save();

        $cashierB = new User(['username' => 'conc_b', 'full_name' => 'Cashier B', 'password' => Hash::make('Pass@1234')]);
        $cashierB->role = User::ROLE_CASHIER;
        $cashierB->is_active = true;
        $cashierB->save();

        $product = Product::create([
            'name' => 'Limited Cold Brew',
            'price' => '160.00',
            'stock_quantity' => 2,
            'low_stock_threshold' => 1,
            'is_active' => true,
        ]);

        $script = base_path('tests/bin/concurrent-checkout.php');
        $payloadA = json_encode([
            'checkout_token' => Str::uuid()->toString(),
            'cash_received' => '400.00',
            'items' => [['product_id' => $product->id, 'quantity' => 2]],
        ], JSON_THROW_ON_ERROR);
        $payloadB = json_encode([
            'checkout_token' => Str::uuid()->toString(),
            'cash_received' => '400.00',
            'items' => [['product_id' => $product->id, 'quantity' => 2]],
        ], JSON_THROW_ON_ERROR);

        $procA = new Process([PHP_BINARY, $script, '--db=artisan_brew_co_test', "--staff-id={$cashierA->id}", "--barrier={$this->barrierFile}", "--payload={$payloadA}"]);
        $procB = new Process([PHP_BINARY, $script, '--db=artisan_brew_co_test', "--staff-id={$cashierB->id}", "--barrier={$this->barrierFile}", "--payload={$payloadB}"]);

        $procA->start();
        $procB->start();
        usleep(150_000);
        file_put_contents($this->barrierFile, 'go');

        $procA->wait();
        $procB->wait();

        $resA = json_decode($procA->getOutput(), true, 512, JSON_THROW_ON_ERROR);
        $resB = json_decode($procB->getOutput(), true, 512, JSON_THROW_ON_ERROR);

        $successCount = ($resA['ok'] ? 1 : 0) + ($resB['ok'] ? 1 : 0);
        $failCount = ($resA['ok'] ? 0 : 1) + ($resB['ok'] ? 0 : 1);

        $this->assertSame(1, $successCount, 'Exactly one concurrent checkout must succeed for the final 2 units.');
        $this->assertSame(1, $failCount, 'Exactly one concurrent checkout must be rejected.');
        $this->assertSame(0, $product->fresh()->stock_quantity);
        $this->assertSame(1, Order::count());
        $this->assertSame(1, Sale::count());
    }

    public function test_simultaneous_duplicate_checkout_token_produces_single_order_and_single_stock_decrement(): void
    {
        $cashier = new User(['username' => 'conc_dup', 'full_name' => 'Cashier Dup', 'password' => Hash::make('Pass@1234')]);
        $cashier->role = User::ROLE_CASHIER;
        $cashier->is_active = true;
        $cashier->save();

        $product = Product::create([
            'name' => 'Espresso Tonic',
            'price' => '150.00',
            'stock_quantity' => 10,
            'low_stock_threshold' => 2,
            'is_active' => true,
        ]);

        $sharedToken = Str::uuid()->toString();
        $payload = json_encode([
            'checkout_token' => $sharedToken,
            'cash_received' => '500.00',
            'items' => [['product_id' => $product->id, 'quantity' => 3]],
        ], JSON_THROW_ON_ERROR);

        $script = base_path('tests/bin/concurrent-checkout.php');
        $procA = new Process([PHP_BINARY, $script, '--db=artisan_brew_co_test', "--staff-id={$cashier->id}", "--barrier={$this->barrierFile}", "--payload={$payload}"]);
        $procB = new Process([PHP_BINARY, $script, '--db=artisan_brew_co_test', "--staff-id={$cashier->id}", "--barrier={$this->barrierFile}", "--payload={$payload}"]);

        $procA->start();
        $procB->start();
        usleep(150_000);
        file_put_contents($this->barrierFile, 'go');

        $procA->wait();
        $procB->wait();

        $resA = json_decode($procA->getOutput(), true, 512, JSON_THROW_ON_ERROR);
        $resB = json_decode($procB->getOutput(), true, 512, JSON_THROW_ON_ERROR);

        $this->assertTrue($resA['ok'], $resA['message'] ?? '');
        $this->assertTrue($resB['ok'], $resB['message'] ?? '');
        $this->assertSame($resA['order_id'], $resB['order_id']);
        $this->assertSame(7, $product->fresh()->stock_quantity);
        $this->assertSame(1, Order::count());
        $this->assertSame(1, Sale::count());
    }
}
