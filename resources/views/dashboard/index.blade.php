@php
use App\Support\BusinessTime;
    use App\Support\Money;

    $inv = $metrics['inventory_status'];
    $recentSerialized = $metrics['recent_orders']->map(function ($order) {
        $firstItem = $order->sales->first()?->product_name_snapshot ?? 'Order items';
        $extra = max(0, $order->sales->count() - 1);
        return [
            'order_number' => $order->order_number,
            'items_summary' => $extra > 0 ? "{$firstItem} +{$extra} more" : $firstItem,
            'customer_name' => $order->customer_name_snapshot,
            'staff_name' => $order->staff_name_snapshot,
            'total_formatted' => Money::formatPhp($order->total_amount),
            'created_at_manila' => BusinessTime::format($order->created_at),
            'show_url' => route('orders.show', $order),
            'receipt_url' => route('orders.receipt', $order),
        ];
    })->values()->all();
@endphp

<x-layouts.app title="Sales Overview">
    <div id="dashboard-app"
         data-metrics-url="{{ route('dashboard.metrics') }}"
         data-initial="{{ json_encode([
            'range' => $metrics['range'],
            'selected_range' => $metrics['selected_range'],
            'inventory_status' => $inv,
            'revenue_series' => $metrics['revenue_series'],
            'top_products' => $metrics['top_products'],
            'recent_orders' => $recentSerialized,
         ]) }}"
         class="space-y-6">

        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <h1 class="font-display text-3xl sm:text-4xl font-semibold text-[#F4EEE5]">Sales Overview</h1>
                <p class="text-sm text-[#BFB4A5] mt-0.5">
                    Today ({{ $metrics['range']['timezone'] }}):
                    <span class="text-[#F4EEE5] font-medium tabular-nums">{{ $metrics['today']['revenue_formatted'] }}</span>
                    across <span class="text-[#F4EEE5] font-medium tabular-nums">{{ $metrics['today']['orders_count'] }}</span> order(s)
                </p>
            </div>

            <div class="flex items-center gap-2 self-start sm:self-auto">
                <label for="dash-preset-select" class="sr-only">Filter date range</label>
                <select id="dash-preset-select" class="abc-input w-44 py-2 text-sm">
                    <option value="all" @selected($preset === 'all')>All Time</option>
                    <option value="30d" @selected($preset === '30d')>Last 30 Days</option>
                    <option value="14d" @selected($preset === '14d')>Last 14 Days</option>
                    <option value="7d" @selected($preset === '7d')>Last 7 Days</option>
                    <option value="today" @selected($preset === 'today')>Today</option>
                </select>
            </div>
        </div>

        <section aria-label="Primary operational metrics" class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div class="abc-panel p-5 flex flex-col justify-between gap-4">
                <div class="flex items-start justify-between gap-3">
                    <div class="space-y-1">
                        <div class="text-xs font-medium text-[#BFB4A5]">Revenue (<span id="dash-period-label">{{ $metrics['range']['label'] }}</span>)</div>
                        <div id="dash-range-revenue" class="text-3xl font-semibold text-[#F4EEE5] tabular-nums">
                            {{ $metrics['selected_range']['revenue_formatted'] }}
                        </div>
                    </div>
                    <span class="w-9 h-9 rounded-lg bg-[#28241F] border border-[#D2B077]/30 flex items-center justify-center text-[#D2B077]" aria-hidden="true">
                        <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75"><path d="M4 17l6-6 4 4 6-8" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    </span>
                </div>
                <div class="flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-[#BFB4A5] pt-2 border-t border-white/10">
                    <span>Orders: <strong id="dash-range-orders" class="text-[#F4EEE5] tabular-nums">{{ $metrics['selected_range']['orders_count'] }}</strong></span>
                    <span>Units sold: <strong id="dash-range-units" class="text-[#F4EEE5] tabular-nums">{{ $metrics['selected_range']['units_sold'] }}</strong></span>
                    <span>Avg order: <strong id="dash-range-avg" class="text-[#D2B077] tabular-nums">{{ $metrics['selected_range']['average_order_formatted'] }}</strong></span>
                </div>
            </div>

            <div class="abc-panel p-5 flex flex-col justify-between gap-4">
                <div class="flex items-start justify-between gap-3">
                    <div class="space-y-1">
                        <div class="text-xs font-medium text-[#BFB4A5]">Low Stock Items</div>
                        <div class="text-3xl font-semibold text-[#F4EEE5] tabular-nums">
                            {{ $inv['alert_count'] }} {{ Str::plural('product', $inv['alert_count']) }}
                        </div>
                    </div>
                    <x-status-badge :tone="$inv['alert_count'] > 0 ? 'warning' : 'success'">
                        Alerts: {{ $inv['alert_count'] }}
                    </x-status-badge>
                </div>
                <div class="space-y-2 pt-2 border-t border-white/10">
                    <div class="flex justify-between text-xs text-[#BFB4A5] tabular-nums">
                        <span>Sold out: <strong class="text-[#E07A7A]">{{ $inv['sold_out_count'] }}</strong></span>
                        <span>Low stock: <strong class="text-[#E0A85B]">{{ $inv['low_stock_count'] }}</strong></span>
                    </div>
                    <div class="h-1.5 rounded-full bg-[#28241F] overflow-hidden flex">
                        <div class="bg-[#E07A7A]" style="width: {{ $inv['sold_out_pct'] }}%"></div>
                        <div class="bg-[#E0A85B]" style="width: {{ $inv['low_stock_pct'] }}%"></div>
                    </div>
                </div>
            </div>

            <div class="abc-panel p-5 flex flex-col justify-between gap-4">
                <div class="flex items-start justify-between gap-3">
                    <div class="space-y-1">
                        <div class="text-xs font-medium text-[#BFB4A5]">Active Menu Items</div>
                        <div class="text-3xl font-semibold text-[#F4EEE5] tabular-nums">
                            {{ $inv['active_menu_count'] }} {{ Str::plural('item', $inv['active_menu_count']) }}
                        </div>
                    </div>
                    <span class="w-9 h-9 rounded-lg bg-[#9CAF94]/18 border border-[#9CAF94]/35 flex items-center justify-center text-[#9CAF94]" aria-hidden="true">
                        <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75"><path d="M17 8h1a3 3 0 010 6h-1M4 8h13v6a5 5 0 01-5 5H9a5 5 0 01-5-5V8z"/><path d="M8 3v2M12 3v2" stroke-linecap="round"/></svg>
                    </span>
                </div>
                <div class="flex items-center justify-between text-xs text-[#BFB4A5] pt-2 border-t border-white/10">
                    <span>Available: <strong class="text-[#9CAF94] tabular-nums">{{ $inv['available_count'] }}</strong></span>
                    <a href="{{ route('products.index') }}" class="text-[#D2B077] hover:underline font-medium">Manage menu</a>
                </div>
            </div>
        </section>

        <div id="dash-chart-status" class="hidden text-xs text-[#BFB4A5]" role="status"></div>

        <section class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-stretch">
            <div class="lg:col-span-8 abc-panel p-5 flex flex-col justify-between gap-4">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <div class="text-xs font-semibold uppercase tracking-[0.14em] text-[#BFB4A5]">Daily Revenue</div>
                        <h2 id="dash-chart-heading" class="font-display text-2xl font-semibold text-[#F4EEE5] mt-0.5">{{ $metrics['range']['label'] }}</h2>
                        <div class="text-xs text-[#BFB4A5] mt-0.5">
                            Total Revenue: <strong id="dash-chart-total" class="text-[#D2B077] tabular-nums">{{ $metrics['selected_range']['revenue_formatted'] }}</strong>
                        </div>
                    </div>
                    <a href="{{ route('sales.index') }}" class="abc-btn-secondary py-1.5 px-3 text-xs">Full Ledger</a>
                </div>

                <div class="h-64 relative rounded-lg bg-[#28241F]/55 border border-white/5 p-3">
                    <canvas id="revenue-chart" aria-label="Revenue chart"></canvas>
                </div>

                <details class="text-xs text-[#BFB4A5]">
                    <summary class="cursor-pointer hover:text-[#D2B077]">View accessible revenue table</summary>
                    <div class="mt-2 overflow-x-auto max-h-44">
                        <table class="abc-table">
                            <thead><tr><th>Date</th><th>Orders</th><th class="text-right">Revenue</th></tr></thead>
                            <tbody id="dash-revenue-table-body"></tbody>
                        </table>
                    </div>
                </details>
            </div>

            <div class="lg:col-span-4 abc-panel p-5 flex flex-col justify-between gap-4">
                <div class="space-y-2">
                    <div class="flex items-center justify-between gap-2">
                        <h2 class="font-display text-2xl font-semibold text-[#F4EEE5]">Inventory Status</h2>
                        <a href="{{ route('products.index') }}" class="text-xs text-[#D2B077] hover:underline">Stock list</a>
                    </div>
                    <div class="flex flex-wrap items-center gap-3 text-xs text-[#BFB4A5]">
                        <span class="inline-flex items-center gap-1.5"><span class="w-2 h-2 rounded-full bg-[#E07A7A]"></span>Sold out</span>
                        <span class="inline-flex items-center gap-1.5"><span class="w-2 h-2 rounded-full bg-[#E0A85B]"></span>Low stock</span>
                        <span class="inline-flex items-center gap-1.5"><span class="w-2 h-2 rounded-full bg-[#9CAF94]"></span>Available</span>
                    </div>
                </div>

                <div class="grid grid-cols-3 gap-3">
                    @foreach([
                        ['label' => 'Sold out', 'count' => $inv['sold_out_count'], 'pct' => $inv['sold_out_pct'], 'bar' => 'bg-[#E07A7A]', 'text' => 'text-[#E07A7A]', 'filter' => 'sold_out'],
                        ['label' => 'Low stock', 'count' => $inv['low_stock_count'], 'pct' => $inv['low_stock_pct'], 'bar' => 'bg-[#E0A85B]', 'text' => 'text-[#E0A85B]', 'filter' => 'low_stock'],
                        ['label' => 'Available', 'count' => $inv['available_count'], 'pct' => $inv['available_pct'], 'bar' => 'bg-[#9CAF94]', 'text' => 'text-[#9CAF94]', 'filter' => 'active'],
                    ] as $pillar)
                        <a href="{{ route('products.index', ['status' => $pillar['filter']]) }}" class="abc-surface p-3 flex flex-col items-center gap-2 hover:border-[#D2B077]/40 transition-colors">
                            <div class="text-xs font-semibold tabular-nums {{ $pillar['text'] }}">{{ $pillar['count'] }}</div>
                            <div class="w-6 h-32 rounded-md bg-[#161412] flex items-end overflow-hidden p-0.5">
                                <div class="w-full rounded {{ $pillar['bar'] }}" style="height: {{ max(8, $pillar['pct']) }}%"></div>
                            </div>
                            <div class="text-[11px] text-[#BFB4A5] text-center">{{ $pillar['label'] }}</div>
                        </a>
                    @endforeach
                </div>

                @if($metrics['low_stock_products']->isNotEmpty())
                    <div class="pt-3 border-t border-white/10 space-y-1.5">
                        <div class="text-xs font-medium text-[#BFB4A5]">Needs Attention</div>
                        @foreach($metrics['low_stock_products']->take(3) as $item)
                            <div class="flex items-center justify-between text-xs">
                                <a href="{{ route('products.edit', $item) }}" class="text-[#F4EEE5] hover:text-[#D2B077] truncate pr-2">{{ $item->name }}</a>
                                <span class="tabular-nums font-medium {{ $item->isSoldOut() ? 'text-[#E07A7A]' : 'text-[#E0A85B]' }}">
                                    {{ $item->isSoldOut() ? 'Sold out' : $item->stock_quantity . ' left' }}
                                </span>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </section>

        <section class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
            <div class="lg:col-span-8 abc-panel overflow-hidden">
                <div class="px-5 py-4 border-b border-white/10 flex items-center justify-between gap-3">
                    <div>
                        <div class="text-xs font-semibold uppercase tracking-[0.14em] text-[#BFB4A5]">Recent Orders</div>
                        <h2 id="dash-recent-heading" class="font-display text-2xl font-semibold text-[#F4EEE5]">{{ $metrics['range']['label'] }}</h2>
                    </div>
                    <a href="{{ route('sales.index') }}" class="text-xs font-medium text-[#D2B077] hover:underline">View all</a>
                </div>

                <div class="overflow-x-auto">
                    <table class="abc-table">
                        <thead>
                            <tr>
                                <th>Order ID</th>
                                <th>Order Items</th>
                                <th>Customer</th>
                                <th class="text-right">Amount</th>
                                <th class="text-right">Receipt</th>
                            </tr>
                        </thead>
                        <tbody id="dash-recent-orders-body">
                            @forelse($recentSerialized as $ord)
                                <tr>
                                    <td class="tabular-nums whitespace-nowrap">
                                        <a href="{{ $ord['show_url'] }}" class="font-medium text-[#D2B077] hover:underline">{{ $ord['order_number'] }}</a>
                                        <div class="text-xs text-[#BFB4A5]">{{ $ord['created_at_manila'] }}</div>
                                    </td>
                                    <td class="text-sm text-[#F4EEE5]">{{ $ord['items_summary'] }}</td>
                                    <td class="text-sm text-[#BFB4A5]">{{ $ord['customer_name'] }}</td>
                                    <td class="text-right font-semibold tabular-nums text-[#F4EEE5]">{{ $ord['total_formatted'] }}</td>
                                    <td class="text-right whitespace-nowrap">
                                        <a href="{{ $ord['receipt_url'] }}" class="abc-btn-secondary py-1 px-2.5 text-xs">Receipt</a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="text-center text-sm text-[#BFB4A5] py-8">No completed orders in this period.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="lg:col-span-4 abc-panel p-5 space-y-4">
                <div>
                    <div class="text-xs font-semibold uppercase tracking-[0.14em] text-[#BFB4A5]">Best Sellers</div>
                    <h2 id="dash-top-heading" class="font-display text-2xl font-semibold text-[#F4EEE5]">{{ $metrics['range']['label'] }}</h2>
                </div>

                <div id="dash-best-sellers-list" class="space-y-3">
                    @forelse($metrics['top_products'] as $item)
                        <div class="abc-surface p-3 space-y-1.5">
                            <div class="flex items-center justify-between gap-2 text-sm">
                                <span class="font-medium text-[#F4EEE5] truncate">{{ $item['name'] }}</span>
                                <span class="text-xs font-semibold text-[#D2B077] tabular-nums shrink-0">{{ $item['revenue_formatted'] }}</span>
                            </div>
                            <div class="flex items-center justify-between text-xs text-[#BFB4A5] tabular-nums">
                                <span>{{ $item['units_sold'] }} units sold</span>
                                <span>{{ $item['share_percent'] }}%</span>
                            </div>
                            <div class="h-1.5 rounded-full bg-[#161412] overflow-hidden">
                                <div class="h-full rounded-full bg-[#D2B077]" style="width: {{ $item['share_percent'] }}%"></div>
                            </div>
                        </div>
                    @empty
                        <div class="text-sm text-[#BFB4A5] py-6 text-center">No product sales recorded in this period.</div>
                    @endforelse
                </div>
            </div>
        </section>
    </div>

    @push('scripts')
        @vite(['resources/js/dashboard.js'])
    @endpush
</x-layouts.app>
