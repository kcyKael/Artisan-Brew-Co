<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\CheckoutService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class AuthenticationAndAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private function makeStaff(string $username, string $role = User::ROLE_CASHIER, bool $active = true): User
    {
        $user = new User([
            'username' => $username,
            'full_name' => ucfirst($username).' Staff',
            'password' => Hash::make('Secret@123'),
        ]);
        $user->role = $role;
        $user->is_active = $active;
        $user->save();

        return $user;
    }

    public function test_unauthenticated_users_are_redirected_from_all_management_and_pos_routes(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');
        $this->get('/pos')->assertRedirect('/login');
        $this->get('/sales/record')->assertRedirect('/login');
        $this->get('/sales')->assertRedirect('/login');
        $this->get('/products')->assertRedirect('/login');
        $this->get('/customers')->assertRedirect('/login');
        $this->get('/staff')->assertRedirect('/login');
        $this->get('/menu-config')->assertRedirect('/login');
        $this->get('/profile')->assertRedirect('/login');
        $this->post('/pos/checkout', [])->assertRedirect('/login');
        $this->post('/sales/record', [])->assertRedirect('/login');
    }

    public function test_admin_lands_on_dashboard_and_cashier_lands_on_pos_after_login(): void
    {
        $admin = $this->makeStaff('admintest', User::ROLE_ADMIN);
        $cashier = $this->makeStaff('cashiertest', User::ROLE_CASHIER);

        $this->post('/login', [
            'username' => $admin->username,
            'password' => 'Secret@123',
        ])->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($admin);

        $this->post('/logout')->assertRedirect('/login');
        $this->assertGuest();

        $this->post('/login', [
            'username' => $cashier->username,
            'password' => 'Secret@123',
        ])->assertRedirect('/pos');
        $this->assertAuthenticatedAs($cashier);
    }

    public function test_inactive_account_cannot_login_and_deactivated_session_is_revoked(): void
    {
        $inactive = $this->makeStaff('inactivestaff', User::ROLE_CASHIER, false);

        $this->post('/login', [
            'username' => $inactive->username,
            'password' => 'Secret@123',
        ])->assertSessionHasErrors('username');
        $this->assertGuest();

        $active = $this->makeStaff('revokedcashier', User::ROLE_CASHIER, true);
        $this->actingAs($active);

        $active->is_active = false;
        $active->save();

        $this->get('/pos')->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_login_is_rate_limited_after_repeated_failures(): void
    {
        $this->makeStaff('ratetest', User::ROLE_CASHIER, true);

        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', [
                'username' => 'ratetest',
                'password' => 'wrong-password',
            ])->assertSessionHasErrors('username');
        }

        $response = $this->post('/login', [
            'username' => 'ratetest',
            'password' => 'Secret@123',
        ]);
        $response->assertSessionHasErrors('username');
        $this->assertGuest();
    }

    public function test_cashier_cannot_access_admin_routes_or_mutate_profile_role(): void
    {
        $cashier = $this->makeStaff('cashier_perm', User::ROLE_CASHIER);
        $this->actingAs($cashier);

        $this->get('/dashboard')->assertForbidden();
        $this->get('/dashboard/metrics')->assertForbidden();
        $this->get('/products')->assertForbidden();
        $this->get('/products/create')->assertForbidden();
        $this->post('/products', ['name' => 'Hack', 'price' => 10, 'stock_quantity' => 1, 'low_stock_threshold' => 1])->assertForbidden();
        $this->get('/staff')->assertForbidden();
        $this->get('/menu-config')->assertForbidden();

        $this->put('/profile', [
            'full_name' => 'Updated Cashier Name',
            'role' => 'admin',
            'is_active' => 0,
        ])->assertRedirect('/profile');

        $cashier->refresh();
        $this->assertSame('Updated Cashier Name', $cashier->full_name);
        $this->assertSame(User::ROLE_CASHIER, $cashier->role);
        $this->assertTrue($cashier->is_active);
    }

    public function test_cashier_can_only_view_own_sales_and_receipts_while_admin_sees_all(): void
    {
        $admin = $this->makeStaff('admin_scope', User::ROLE_ADMIN);
        $cashierA = $this->makeStaff('cashier_a', User::ROLE_CASHIER);
        $cashierB = $this->makeStaff('cashier_b', User::ROLE_CASHIER);

        $product = Product::create([
            'name' => 'House Americano',
            'price' => '120.00',
            'stock_quantity' => 20,
            'low_stock_threshold' => 5,
            'is_active' => true,
        ]);

        $checkout = app(CheckoutService::class);
        $orderA = $checkout->complete($cashierA, [
            'checkout_token' => Str::uuid()->toString(),
            'cash_received' => '150.00',
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ])->order;

        $orderB = $checkout->complete($cashierB, [
            'checkout_token' => Str::uuid()->toString(),
            'cash_received' => '150.00',
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ])->order;

        $this->actingAs($cashierA);
        $this->get(route('orders.show', $orderA))->assertOk();
        $this->get(route('orders.receipt', $orderA))->assertOk();
        $this->get(route('orders.show', $orderB))->assertForbidden();
        $this->get(route('orders.receipt', $orderB))->assertForbidden();

        $history = $this->get('/sales')->assertOk();
        $history->assertSee($orderA->order_number);
        $history->assertDontSee($orderB->order_number);

        $this->actingAs($admin);
        $this->get(route('orders.show', $orderB))->assertOk();
        $adminHistory = $this->get('/sales')->assertOk();
        $adminHistory->assertSee($orderA->order_number);
        $adminHistory->assertSee($orderB->order_number);
    }
}
