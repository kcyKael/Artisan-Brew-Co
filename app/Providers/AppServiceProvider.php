<?php

namespace App\Providers;

use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Policies\CustomerPolicy;
use App\Policies\OrderPolicy;
use App\Policies\ProductPolicy;
use App\Policies\UserPolicy;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
    }

    public function boot(): void
    {
        Gate::policy(Order::class, OrderPolicy::class);
        Gate::policy(Customer::class, CustomerPolicy::class);
        Gate::policy(Product::class, ProductPolicy::class);
        Gate::policy(User::class, UserPolicy::class);

        Paginator::defaultView('components.pagination');

        RateLimiter::for('login', function (Request $request) {
            $username = mb_strtolower((string) $request->input('username', ''));

            return [
                Limit::perMinute(5)->by($username.'|'.$request->ip()),
                Limit::perMinute(20)->by($request->ip()),
            ];
        });

        View::composer('components.layouts.app', function ($view): void {
            $user = auth()->user();
            $lowStockAlertCount = 0;

            if ($user && $user->isAdmin()) {
                $lowStockAlertCount = Product::query()
                    ->whereNull('archived_at')
                    ->where('is_active', true)
                    ->whereColumn('stock_quantity', '<=', 'low_stock_threshold')
                    ->count();
            }

            $view->with('lowStockAlertCount', $lowStockAlertCount);
        });
    }
}
