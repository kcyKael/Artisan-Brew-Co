<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Product;
use App\Support\BusinessTime;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class DashboardMetricsService
{
    public function build(
        string $fromDate,
        string $toDate,
        string $preset = '14d',
        string $label = 'Last 14 Days'
    ): array {
        $tz = BusinessTime::timezone();
        [$todayStartUtc, $todayEndUtc] = BusinessTime::utcBoundsForToday();
        [$rangeStartUtc, $rangeEndUtc] = BusinessTime::utcBoundsForManilaRange($fromDate, $toDate);

        $todayOrderAgg = DB::table('orders')
            ->where('status', 'completed')
            ->whereBetween('created_at', [$todayStartUtc, $todayEndUtc])
            ->selectRaw('COUNT(*) as order_count, COALESCE(SUM(total_amount), 0) as revenue')
            ->first();

        $todayUnits = (int) DB::table('sales')
            ->join('orders', 'sales.order_id', '=', 'orders.id')
            ->where('orders.status', 'completed')
            ->whereBetween('sales.created_at', [$todayStartUtc, $todayEndUtc])
            ->sum('sales.quantity');

        $todayRevenueCentavos = Money::toCentavos((string) ($todayOrderAgg->revenue ?? '0.00'));
        $todayOrderCount = (int) ($todayOrderAgg->order_count ?? 0);
        $todayAvgCentavos = $todayOrderCount > 0 ? intdiv($todayRevenueCentavos, $todayOrderCount) : 0;

        $rangeOrderAgg = DB::table('orders')
            ->where('status', 'completed')
            ->whereBetween('created_at', [$rangeStartUtc, $rangeEndUtc])
            ->selectRaw('COUNT(*) as order_count, COALESCE(SUM(total_amount), 0) as revenue')
            ->first();

        $rangeRevenueCentavos = Money::toCentavos((string) ($rangeOrderAgg->revenue ?? '0.00'));
        $rangeOrderCount = (int) ($rangeOrderAgg->order_count ?? 0);
        $rangeAvgCentavos = $rangeOrderCount > 0 ? intdiv($rangeRevenueCentavos, $rangeOrderCount) : 0;

        $rangeUnits = (int) DB::table('sales')
            ->join('orders', 'sales.order_id', '=', 'orders.id')
            ->where('orders.status', 'completed')
            ->whereBetween('sales.created_at', [$rangeStartUtc, $rangeEndUtc])
            ->sum('sales.quantity');

        $ordersInRange = DB::table('orders')
            ->where('status', 'completed')
            ->whereBetween('created_at', [$rangeStartUtc, $rangeEndUtc])
            ->select(['created_at', 'total_amount'])
            ->get();

        $dailyBuckets = [];
        $cursor = CarbonImmutable::createFromFormat('Y-m-d', $fromDate, $tz)->startOfDay();
        $endDay = CarbonImmutable::createFromFormat('Y-m-d', $toDate, $tz)->startOfDay();

        while ($cursor->lte($endDay)) {
            $key = $cursor->toDateString();
            $dailyBuckets[$key] = [
                'date' => $key,
                'label' => $cursor->format('M j'),
                'revenue_centavos' => 0,
                'orders' => 0,
            ];
            $cursor = $cursor->addDay();
        }

        foreach ($ordersInRange as $row) {
            $manilaKey = BusinessTime::toManila($row->created_at)?->toDateString();
            if ($manilaKey && isset($dailyBuckets[$manilaKey])) {
                $dailyBuckets[$manilaKey]['revenue_centavos'] += Money::toCentavos((string) $row->total_amount);
                $dailyBuckets[$manilaKey]['orders'] += 1;
            }
        }

        $revenueSeries = array_values(array_map(static function (array $bucket): array {
            return [
                'date' => $bucket['date'],
                'label' => $bucket['label'],
                'revenue' => round($bucket['revenue_centavos'] / 100, 2),
                'revenue_formatted' => Money::formatCentavos($bucket['revenue_centavos']),
                'orders' => $bucket['orders'],
            ];
        }, $dailyBuckets));

        $rawTop = DB::table('sales')
            ->join('orders', 'sales.order_id', '=', 'orders.id')
            ->where('orders.status', 'completed')
            ->whereBetween('sales.created_at', [$rangeStartUtc, $rangeEndUtc])
            ->groupBy('sales.product_id', 'sales.product_name_snapshot')
            ->selectRaw('sales.product_id, sales.product_name_snapshot as name, SUM(sales.quantity) as units_sold, SUM(sales.total_price) as revenue')
            ->orderByDesc('units_sold')
            ->orderByDesc('revenue')
            ->limit(6)
            ->get();

        $maxUnits = max(1, (int) ($rawTop->max('units_sold') ?? 1));

        $topProducts = $rawTop->map(static function ($row) use ($maxUnits): array {
            $units = (int) $row->units_sold;

            return [
                'product_id' => (int) $row->product_id,
                'name' => (string) $row->name,
                'units_sold' => $units,
                'share_percent' => (int) max(8, round(($units / $maxUnits) * 100)),
                'revenue' => round(Money::toCentavos((string) $row->revenue) / 100, 2),
                'revenue_formatted' => Money::formatPhp((string) $row->revenue),
            ];
        })->values()->all();

        $recentOrders = Order::query()
            ->where('status', 'completed')
            ->whereBetween('created_at', [$rangeStartUtc, $rangeEndUtc])
            ->withCount('sales')
            ->with(['sales', 'customer', 'staff'])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(8)
            ->get();

        $inventoryCounts = DB::table('products')
            ->whereNull('archived_at')
            ->where('is_active', true)
            ->selectRaw('
                COUNT(*) as active_menu_count,
                COALESCE(SUM(CASE WHEN stock_quantity <= 0 THEN 1 ELSE 0 END), 0) as sold_out_count,
                COALESCE(SUM(CASE WHEN stock_quantity > 0 AND stock_quantity <= low_stock_threshold THEN 1 ELSE 0 END), 0) as low_stock_count,
                COALESCE(SUM(CASE WHEN stock_quantity > low_stock_threshold THEN 1 ELSE 0 END), 0) as available_count
            ')
            ->first();

        $activeMenuCount = (int) ($inventoryCounts->active_menu_count ?? 0);
        $soldOutCount = (int) ($inventoryCounts->sold_out_count ?? 0);
        $lowStockCount = (int) ($inventoryCounts->low_stock_count ?? 0);
        $availableCount = (int) ($inventoryCounts->available_count ?? 0);
        $alertCount = $soldOutCount + $lowStockCount;
        $invDenom = max(1, $activeMenuCount);

        $lowStockProducts = Product::query()
            ->with('category')
            ->whereNull('archived_at')
            ->where('is_active', true)
            ->whereColumn('stock_quantity', '<=', 'low_stock_threshold')
            ->orderBy('stock_quantity')
            ->orderBy('name')
            ->limit(10)
            ->get();

        return [
            'range' => [
                'preset' => $preset,
                'label' => $label,
                'from' => $fromDate,
                'to' => $toDate,
                'timezone' => $tz,
            ],
            'today' => [
                'revenue_centavos' => $todayRevenueCentavos,
                'revenue_formatted' => Money::formatCentavos($todayRevenueCentavos),
                'orders_count' => $todayOrderCount,
                'units_sold' => $todayUnits,
                'average_order_formatted' => Money::formatCentavos($todayAvgCentavos),
            ],
            'selected_range' => [
                'revenue_centavos' => $rangeRevenueCentavos,
                'revenue_formatted' => Money::formatCentavos($rangeRevenueCentavos),
                'orders_count' => $rangeOrderCount,
                'units_sold' => $rangeUnits,
                'average_order_formatted' => Money::formatCentavos($rangeAvgCentavos),
            ],
            'inventory_status' => [
                'active_menu_count' => $activeMenuCount,
                'sold_out_count' => $soldOutCount,
                'low_stock_count' => $lowStockCount,
                'available_count' => $availableCount,
                'alert_count' => $alertCount,
                'sold_out_pct' => (int) round(($soldOutCount / $invDenom) * 100),
                'low_stock_pct' => (int) round(($lowStockCount / $invDenom) * 100),
                'available_pct' => (int) round(($availableCount / $invDenom) * 100),
            ],
            'revenue_series' => $revenueSeries,
            'top_products' => $topProducts,
            'recent_orders' => $recentOrders,
            'low_stock_products' => $lowStockProducts,
        ];
    }
}
