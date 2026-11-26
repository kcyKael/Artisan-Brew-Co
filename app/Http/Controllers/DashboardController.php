<?php

namespace App\Http\Controllers;

use App\Services\DashboardMetricsService;
use App\Support\BusinessTime;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(Request $request, DashboardMetricsService $service): View
    {
        [$from, $to] = $this->resolveRange($request);
        $metrics = $service->build($from, $to);

        return view('dashboard.index', [
            'metrics' => $metrics,
            'preset' => (string) $request->query('preset', '14d'),
        ]);
    }

    public function metrics(Request $request, DashboardMetricsService $service): JsonResponse
    {
        [$from, $to] = $this->resolveRange($request);
        $metrics = $service->build($from, $to);

        return response()->json([
            'range' => $metrics['range'],
            'today' => $metrics['today'],
            'selected_range' => $metrics['selected_range'],
            'revenue_series' => $metrics['revenue_series'],
            'top_products' => $metrics['top_products'],
        ]);
    }

    private function resolveRange(Request $request): array
    {
        $today = BusinessTime::nowManila();
        $preset = (string) $request->query('preset', '14d');

        $fromInput = $request->query('from');
        $toInput = $request->query('to');

        if (is_string($fromInput) && is_string($toInput)
            && preg_match('/^\d{4}-\d{2}-\d{2}$/', $fromInput)
            && preg_match('/^\d{4}-\d{2}-\d{2}$/', $toInput)
            && $fromInput <= $toInput
        ) {
            return [$fromInput, $toInput];
        }

        $days = match ($preset) {
            '7d' => 6,
            '30d' => 29,
            default => 13,
        };

        return [$today->subDays($days)->toDateString(), $today->toDateString()];
    }
}
