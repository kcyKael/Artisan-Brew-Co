<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Services\DashboardMetricsService;
use App\Support\BusinessTime;
use App\Support\Money;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(Request $request, DashboardMetricsService $service): View
    {
        [$from, $to, $preset, $label] = $this->resolveRange($request);
        $metrics = $service->build($from, $to, $preset, $label);

        return view('dashboard.index', [
            'metrics' => $metrics,
            'preset' => $preset,
        ]);
    }

    public function metrics(Request $request, DashboardMetricsService $service): JsonResponse
    {
        [$from, $to, $preset, $label] = $this->resolveRange($request);
        $metrics = $service->build($from, $to, $preset, $label);

        $recentOrders = $metrics['recent_orders']->map(static function (Order $order): array {
            $firstItem = $order->sales->first()?->product_name_snapshot ?? 'Order items';
            $extraCount = max(0, $order->sales->count() - 1);
            $itemsSummary = $extraCount > 0 ? "{$firstItem} +{$extraCount} more" : $firstItem;

            return [
                'order_number' => $order->order_number,
                'items_summary' => $itemsSummary,
                'customer_name' => $order->customer_name_snapshot,
                'staff_name' => $order->staff_name_snapshot,
                'total_formatted' => Money::formatPhp($order->total_amount),
                'created_at_manila' => BusinessTime::format($order->created_at),
                'show_url' => route('orders.show', $order),
                'receipt_url' => route('orders.receipt', $order),
            ];
        })->values()->all();

        return response()->json([
            'range' => $metrics['range'],
            'today' => $metrics['today'],
            'selected_range' => $metrics['selected_range'],
            'inventory_status' => $metrics['inventory_status'],
            'revenue_series' => $metrics['revenue_series'],
            'top_products' => $metrics['top_products'],
            'recent_orders' => $recentOrders,
        ]);
    }

    private function resolveRange(Request $request): array
    {
        $today = BusinessTime::nowManila();
        $preset = (string) $request->query('preset', 'all');

        $fromInput = $request->query('from');
        $toInput = $request->query('to');

        if (is_string($fromInput) && is_string($toInput)
            && preg_match('/^\d{4}-\d{2}-\d{2}$/', $fromInput)
            && preg_match('/^\d{4}-\d{2}-\d{2}$/', $toInput)
            && $fromInput <= $toInput
        ) {
            return [$fromInput, $toInput, 'custom', "{$fromInput} to {$toInput}"];
        }

        if ($preset === 'all') {
            $earliest = DB::table('orders')->min('created_at');
            $earliestManila = $earliest
                ? BusinessTime::toManila($earliest)?->toDateString()
                : $today->subDays(13)->toDateString();

            $fromDate = ($earliestManila && $earliestManila <= $today->toDateString())
                ? $earliestManila
                : $today->toDateString();

            return [$fromDate, $today->toDateString(), 'all', 'All Time'];
        }

        return match ($preset) {
            'today' => [$today->toDateString(), $today->toDateString(), 'today', 'Today'],
            '7d' => [$today->subDays(6)->toDateString(), $today->toDateString(), '7d', 'Last 7 Days'],
            '30d' => [$today->subDays(29)->toDateString(), $today->toDateString(), '30d', 'Last 30 Days'],
            default => [$today->subDays(13)->toDateString(), $today->toDateString(), '14d', 'Last 14 Days'],
        };
    }
}
