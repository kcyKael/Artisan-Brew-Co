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
    public function build(string $fromDate, string $toDate): array
    {
        $tz = BusinessTime::timezone();
        [$todayStartUtc, $todayEndUtc] = BusinessTime::utcBoundsForToday();
        [$rangeStartUtc, $rangeEndUtc] = BusinessTime::utcBoundsForManilaRange($fromDate, $toDate);

        $todayOrderAgg = DB::table('orders')
            ->whereBetween('created_at', [$todayStartUtc, $todayEndUtc])
            ->selectRaw('COUNT(*) as order_count, COALESCE(SUM(total_amount), 0) as revenue')
            ->first();

        $todayUnits = (int) DB::table('sales')
            ->whereBetween('created_at', [$todayStartUtc, $todayEndUtc])
            ->sum('quantity');

        $todayRevenueCentavos = Money::toCentavos((string) ($todayOrderAgg->revenue ?? '0.00'));
        $todayOrderCount = (int) ($todayOrderAgg->order_count ?? 0);
        $todayAvgCentavos = $todayOrderCount > 0 ? intdiv($todayRevenueCentavos, $todayOrderCount) : 0;

        $rangeOrderAgg = DB::table('orders')
            ->whereBetween('created_at', [$rangeStartUtc, $rangeEndUtc])
            ->selectRaw('COUNT(*) as order_count, COALESCE(SUM(total_amount), 0) as revenue')
            ->first();

        $rangeRevenueCentavos = Money::toCentavos((string) ($rangeOrderAgg->revenue ?? '0.00'));
        $rangeOrderCount = (int) ($rangeOrderAgg->order_count ?? 0);

        $rangeUnits = (int) DB::table('sales')
            ->whereBetween('created_at', [$rangeStartUtc, $rangeEndUtc])
            ->sum('quantity');

        $ordersInRange = DB::table('orders')
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

        $topProducts = DB::table('sales')
            ->whereBetween('created_at', [$rangeStartUtc, $rangeEndUtc])
            ->groupBy('product_id', 'product_name_snapshot')
            ->selectRaw('product_id, product_name_snapshot as name, SUM(quantity) as units_sold, SUM(total_price) as revenue')
            ->orderByDesc('units_sold')
            ->orderByDesc('revenue')
            ->limit(6)
            ->get()
            ->map(static fn ($row) => [
                'product_id' => (int) $row->product_id,
                'name' => (string) $row->name,
                'units_sold' => (int) $row->units_sold,
                'revenue' => round(Money::toCentavos((string) $row->revenue) / 100, 2),
                'revenue_formatted' => Money::formatPhp((string) $row->revenue),
            ])
            ->values()
            ->all();

        $recentOrders = Order::query()
            ->withCount('sales')
            ->with(['customer', 'staff'])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(8)
            ->get();

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
                'revenue_formatted' => Money::formatCentavos($rangeRevenueCentavos),
                'orders_count' => $rangeOrderCount,
                'units_sold' => $rangeUnits,
            ],
            'revenue_series' => $revenueSeries,
            'top_products' => $topProducts,
            'recent_orders' => $recentOrders,
            'low_stock_products' => $lowStockProducts,
        ];
    }
}
