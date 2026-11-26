@php
use App\Support\BusinessTime;
    use App\Support\Money;
@endphp

<x-layouts.app title="Executive Dashboard">
    <div id="dashboard-app"
         data-metrics-url="{{ route('dashboard.metrics') }}"
         data-initial="{{ json_encode([
            'selected_range' => $metrics['selected_range'],
            'revenue_series' => $metrics['revenue_series'],
            'top_products' => $metrics['top_products'],
         ]) }}"
         class="space-y-6">

        <div class="flex flex-col sm:flex-row sm:items-end justify-between gap-4">
            <div>
                <h1 class="font-display text-3xl sm:text-4xl font-semibold text-[#F5F0E8]">Cafe Operations Overview</h1>
                <p class="text-sm text-[#BDB5AA] mt-1">
                    Live database revenue, completed orders, and inventory status in {{ $metrics['range']['timezone'] }} time.
                </p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <a href="{{ route('pos.index') }}" class="abc-btn-primary">Open POS Terminal</a>
                <a href="{{ route('sales.record') }}" class="abc-btn-secondary">Record Sale</a>
            </div>
        </div>

        <section aria-label="Today's key metrics" class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4">
            <div class="abc-panel p-5 space-y-1">
                <div class="text-xs font-medium uppercase tracking-wider text-[#BDB5AA]">Today's Revenue</div>
                <div class="text-2xl font-semibold text-[#C6A66B] tabular-nums">{{ $metrics['today']['revenue_formatted'] }}</div>
                <div class="text-xs text-[#BDB5AA]">Completed orders today (Manila)</div>
            </div>
            <div class="abc-panel p-5 space-y-1">
                <div class="text-xs font-medium uppercase tracking-wider text-[#BDB5AA]">Today's Orders</div>
                <div class="text-2xl font-semibold text-[#F5F0E8] tabular-nums">{{ $metrics['today']['orders_count'] }}</div>
                <div class="text-xs text-[#BDB5AA]">Distinct completed receipts</div>
            </div>
            <div class="abc-panel p-5 space-y-1">
                <div class="text-xs font-medium uppercase tracking-wider text-[#BDB5AA]">Units Sold Today</div>
                <div class="text-2xl font-semibold text-[#F5F0E8] tabular-nums">{{ $metrics['today']['units_sold'] }}</div>
                <div class="text-xs text-[#BDB5AA]">Drinks &amp; pastries served</div>
            </div>
            <div class="abc-panel p-5 space-y-1">
                <div class="text-xs font-medium uppercase tracking-wider text-[#BDB5AA]">Average Order Value</div>
                <div class="text-2xl font-semibold text-[#F5F0E8] tabular-nums">{{ $metrics['today']['average_order_formatted'] }}</div>
                <div class="text-xs text-[#BDB5AA]">Per receipt today</div>
            </div>
        </section>

        <section class="abc-panel p-5 space-y-5">
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 border-b border-white/10 pb-4">
                <div>
                    <h2 class="font-display text-2xl font-semibold text-[#F5F0E8]">Revenue &amp; Menu Performance</h2>
                    <div class="flex flex-wrap items-center gap-4 mt-1 text-sm text-[#BDB5AA]">
                        <span>Range Revenue: <strong id="dash-range-revenue" class="text-[#C6A66B] tabular-nums">{{ $metrics['selected_range']['revenue_formatted'] }}</strong></span>
                        <span>Orders: <strong id="dash-range-orders" class="text-[#F5F0E8] tabular-nums">{{ $metrics['selected_range']['orders_count'] }}</strong></span>
                        <span>Units: <strong id="dash-range-units" class="text-[#F5F0E8] tabular-nums">{{ $metrics['selected_range']['units_sold'] }}</strong></span>
                    </div>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    @foreach(['7d' => 'Last 7 Days', '14d' => 'Last 14 Days', '30d' => 'Last 30 Days'] as $key => $label)
                        <button type="button"
                                data-dash-preset="{{ $key }}"
                                aria-pressed="{{ $preset === $key ? 'true' : 'false' }}"
                                class="{{ $preset === $key ? 'abc-btn-primary' : 'abc-btn-secondary' }} py-1.5 px-3 text-xs">
                            {{ $label }}
                        </button>
                    @endforeach
                </div>
            </div>

            <div id="dash-chart-status" class="hidden text-xs text-[#BDB5AA]" role="status"></div>

            <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
                <div class="xl:col-span-2 space-y-3">
                    <h3 class="text-sm font-semibold text-[#F5F0E8]">Daily Revenue Trend</h3>
                    <div class="h-72 relative">
                        <canvas id="revenue-chart" aria-label="Daily revenue chart"></canvas>
                    </div>
                    <details class="text-xs text-[#BDB5AA]">
                        <summary class="cursor-pointer hover:text-[#C6A66B]">View accessible revenue table</summary>
                        <div class="mt-2 overflow-x-auto max-h-48">
                            <table class="abc-table">
                                <thead><tr><th>Date</th><th>Orders</th><th class="text-right">Revenue</th></tr></thead>
                                <tbody id="dash-revenue-table-body"></tbody>
                            </table>
                        </div>
                    </details>
                </div>

                <div class="space-y-3">
                    <h3 class="text-sm font-semibold text-[#F5F0E8]">Top-Selling Products</h3>
                    <div class="h-72 relative">
                        <canvas id="top-products-chart" aria-label="Top selling products chart"></canvas>
                    </div>
                    <details class="text-xs text-[#BDB5AA]">
                        <summary class="cursor-pointer hover:text-[#C6A66B]">View accessible top products table</summary>
                        <div class="mt-2 overflow-x-auto max-h-48">
                            <table class="abc-table">
                                <thead><tr><th>Product</th><th>Units</th><th class="text-right">Revenue</th></tr></thead>
                                <tbody id="dash-top-table-body"></tbody>
                            </table>
                        </div>
                    </details>
                </div>
            </div>
        </section>

        <div class="grid grid-cols-1 xl:grid-cols-2 gap-6">
            <section class="abc-panel p-5 space-y-4">
                <div class="flex items-center justify-between">
                    <h2 class="font-display text-2xl font-semibold text-[#F5F0E8]">Recent Orders</h2>
                    <a href="{{ route('sales.index') }}" class="text-xs font-medium text-[#C6A66B] hover:underline">View all sales</a>
                </div>

                @if($metrics['recent_orders']->isEmpty())
                    <x-empty-state title="No completed orders yet" description="Completed POS and Record Sale checkouts will appear here." />
                @else
                    <div class="overflow-x-auto">
                        <table class="abc-table">
                            <thead>
                                <tr>
                                    <th>Order</th>
                                    <th>Customer</th>
                                    <th>Cashier</th>
                                    <th class="text-right">Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($metrics['recent_orders'] as $order)
                                    <tr>
                                        <td>
                                            <a href="{{ route('orders.show', $order) }}" class="font-medium text-[#C6A66B] hover:underline tabular-nums">{{ $order->order_number }}</a>
                                            <div class="text-xs text-[#BDB5AA]">{{ BusinessTime::format($order->created_at) }}</div>
                                        </td>
                                        <td>{{ $order->customer_name_snapshot }}</td>
                                        <td>{{ $order->staff_name_snapshot }}</td>
                                        <td class="text-right font-semibold tabular-nums">{{ Money::formatPhp($order->total_amount) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </section>

            <section class="abc-panel p-5 space-y-4">
                <div class="flex items-center justify-between">
                    <h2 class="font-display text-2xl font-semibold text-[#F5F0E8]">Low &amp; Out-of-Stock Items</h2>
                    <a href="{{ route('products.index', ['status' => 'low_stock']) }}" class="text-xs font-medium text-[#C6A66B] hover:underline">Manage inventory</a>
                </div>

                @if($metrics['low_stock_products']->isEmpty())
                    <x-empty-state title="All active menu items stocked" description="No active products are currently at or below their low-stock threshold." />
                @else
                    <div class="overflow-x-auto">
                        <table class="abc-table">
                            <thead>
                                <tr>
                                    <th>Product</th>
                                    <th>Status</th>
                                    <th class="text-right">Remaining</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($metrics['low_stock_products'] as $product)
                                    <tr>
                                        <td>
                                            <a href="{{ route('products.edit', $product) }}" class="font-medium text-[#F5F0E8] hover:text-[#C6A66B]">{{ $product->name }}</a>
                                            <div class="text-xs text-[#BDB5AA]">{{ $product->category?->name ?? 'Uncategorized' }}</div>
                                        </td>
                                        <td>
                                            @if($product->isSoldOut())
                                                <x-status-badge tone="danger">Sold out</x-status-badge>
                                            @else
                                                <x-status-badge tone="warning">Low stock</x-status-badge>
                                            @endif
                                        </td>
                                        <td class="text-right font-semibold tabular-nums">
                                            {{ $product->stock_quantity }} / {{ $product->low_stock_threshold }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </section>
        </div>
    </div>

    @push('scripts')
        @vite(['resources/js/dashboard.js'])
    @endpush
</x-layouts.app>
