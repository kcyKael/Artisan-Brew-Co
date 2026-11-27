<?php

namespace App\Services\Email;

use App\Models\DailySalesReport;
use App\Models\Product;
use App\Models\User;
use App\Support\BusinessTime;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DailySalesSummaryService
{
    public function __construct(
        private readonly TransactionalEmailService $transactional,
        private readonly EmailSettingsService $settings,
    ) {
    }

    public function buildPeriodBounds(?string $businessDate = null, ?string $closingTime = null): array
    {
        $tz = BusinessTime::timezone();
        $timeStr = $closingTime ?: $this->settings->dailySummaryTime();
        if (! preg_match('/^(\d{2}):(\d{2})$/', $timeStr, $matches)) {
            $matches = ['22:00', '22', '00'];
        }

        $hour = (int) $matches[1];
        $minute = (int) $matches[2];

        $manilaDate = $businessDate
            ? CarbonImmutable::createFromFormat('Y-m-d', $businessDate, $tz)
            : BusinessTime::nowManila();

        $periodEndManila = $manilaDate->setTime($hour, $minute, 0);
        $periodStartManila = $periodEndManila->subDay();

        return [
            'business_date' => $manilaDate->toDateString(),
            'closing_time' => sprintf('%02d:%02d', $hour, $minute),
            'start_manila' => $periodStartManila,
            'end_manila' => $periodEndManila,
            'start_utc' => $periodStartManila->utc(),
            'end_utc' => $periodEndManila->utc(),
        ];
    }

    public function computeSummary(?string $businessDate = null, ?string $closingTime = null): array
    {
        $bounds = $this->buildPeriodBounds($businessDate, $closingTime);
        $startUtc = $bounds['start_utc'];
        $endUtc = $bounds['end_utc'];

        $orderAgg = DB::table('orders')
            ->where('status', 'completed')
            ->where('created_at', '>=', $startUtc)
            ->where('created_at', '<', $endUtc)
            ->selectRaw('COUNT(*) as order_count, COALESCE(SUM(total_amount), 0) as revenue')
            ->first();

        $revenueCentavos = Money::toCentavos((string) ($orderAgg->revenue ?? '0.00'));
        $orderCount = (int) ($orderAgg->order_count ?? 0);
        $avgCentavos = $orderCount > 0 ? intdiv($revenueCentavos, $orderCount) : 0;

        $unitsSold = (int) DB::table('sales')
            ->join('orders', 'sales.order_id', '=', 'orders.id')
            ->where('orders.status', 'completed')
            ->where('sales.created_at', '>=', $startUtc)
            ->where('sales.created_at', '<', $endUtc)
            ->sum('sales.quantity');

        $rawTop = DB::table('sales')
            ->join('orders', 'sales.order_id', '=', 'orders.id')
            ->where('orders.status', 'completed')
            ->where('sales.created_at', '>=', $startUtc)
            ->where('sales.created_at', '<', $endUtc)
            ->groupBy('sales.product_id', 'sales.product_name_snapshot')
            ->selectRaw('sales.product_id, sales.product_name_snapshot as name, SUM(sales.quantity) as units_sold, SUM(sales.total_price) as revenue')
            ->orderByDesc('units_sold')
            ->orderByDesc('revenue')
            ->limit(6)
            ->get();

        $bestSellers = $rawTop->map(static fn ($row): array => [
            'name' => (string) $row->name,
            'units' => (int) $row->units_sold,
            'revenueFormatted' => Money::formatPhp((string) $row->revenue),
        ])->values()->all();

        $stockAlerts = Product::query()
            ->whereNull('archived_at')
            ->where('is_active', true)
            ->whereColumn('stock_quantity', '<=', 'low_stock_threshold')
            ->orderBy('stock_quantity')
            ->orderBy('name')
            ->get()
            ->map(static fn (Product $p): array => [
                'name' => $p->name,
                'stock' => (int) $p->stock_quantity,
                'threshold' => (int) $p->low_stock_threshold,
                'state' => $p->isSoldOut() ? 'Sold out' : 'Low stock',
            ])
            ->values()
            ->all();

        $periodLabel = sprintf(
            '%s – %s PHT',
            $bounds['start_manila']->format('M d, Y g:i A'),
            $bounds['end_manila']->format('M d, Y g:i A')
        );

        return [
            'reportKey' => 'daily-summary:'.$bounds['business_date'].':'.$bounds['closing_time'],
            'businessDate' => $bounds['start_manila']->format('M d, Y').' – '.$bounds['end_manila']->format('M d, Y'),
            'businessDateIso' => $bounds['business_date'],
            'periodStartUtc' => $startUtc,
            'periodEndUtc' => $endUtc,
            'periodLabel' => $periodLabel,
            'revenueCentavos' => $revenueCentavos,
            'revenueFormatted' => Money::formatCentavos($revenueCentavos),
            'orderCount' => $orderCount,
            'unitsSold' => $unitsSold,
            'averageOrderFormatted' => Money::formatCentavos($avgCentavos),
            'bestSellers' => $bestSellers,
            'stockAlerts' => $stockAlerts,
            'dashboardUrl' => route('dashboard'),
        ];
    }

    public function generateAndQueue(?string $businessDate = null, bool $force = false, ?User $actor = null): array
    {
        if (! $this->settings->featureEnabled('feature_daily_summary') && ! $force) {
            return ['report' => null, 'queued' => 0];
        }

        $recipients = $this->settings->ownerAlertEmails();
        if ($recipients === []) {
            return ['report' => null, 'queued' => 0];
        }

        $summary = $this->computeSummary($businessDate);

        $report = DailySalesReport::query()->firstOrCreate(
            ['report_key' => $summary['reportKey']],
            [
                'business_date' => $summary['businessDateIso'],
                'period_start_utc' => $summary['periodStartUtc'],
                'period_end_utc' => $summary['periodEndUtc'],
                'summary_json' => json_encode($summary, JSON_THROW_ON_ERROR),
            ]
        );

        $attachment = [[
            'name' => 'abc-daily-summary-'.$summary['businessDateIso'].'.csv',
            'content' => base64_encode($this->buildCsv($summary)),
        ]];

        $queued = 0;
        foreach ($recipients as $email) {
            $messageKey = $force
                ? $summary['reportKey'].':force:'.Str::uuid()
                : $summary['reportKey'].':recipient:'.hash('crc32b', $email);

            $this->transactional->queue(
                messageKey: $messageKey,
                purpose: 'daily_summary',
                recipientEmail: $email,
                recipientName: null,
                subject: "Daily Sales Summary · {$summary['businessDateIso']} ({$summary['revenueFormatted']}) — Artisan Brew Co.",
                templateName: 'daily_summary',
                templateData: $summary,
                options: [
                    'related_type' => 'daily_sales_report',
                    'related_id' => $report->id,
                    'initiated_by' => $actor?->id,
                    'attachments' => $attachment,
                ]
            );
            $queued++;
        }

        if ($report->sent_at === null) {
            $report->sent_at = now('UTC');
            $report->save();
        }

        return ['report' => $report, 'queued' => $queued];
    }

    public function queueTestToRecipient(string $recipientEmail, ?string $businessDate, User $actor): void
    {
        $summary = $this->computeSummary($businessDate);
        $attachment = [[
            'name' => 'abc-daily-summary-'.$summary['businessDateIso'].'.csv',
            'content' => base64_encode($this->buildCsv($summary)),
        ]];

        $this->transactional->queue(
            messageKey: 'daily-summary-test:'.Str::uuid(),
            purpose: 'daily_summary',
            recipientEmail: $recipientEmail,
            recipientName: null,
            subject: "[Test] Daily Sales Summary · {$summary['businessDateIso']} — Artisan Brew Co.",
            templateName: 'daily_summary',
            templateData: $summary,
            options: [
                'initiated_by' => $actor->id,
                'attachments' => $attachment,
            ]
        );
    }

    private function buildCsv(array $summary): string
    {
        $lines = [
            ['Metric', 'Value'],
            ['Period (Asia/Manila)', $summary['periodLabel']],
            ['Completed Revenue', $summary['revenueFormatted']],
            ['Completed Orders', (string) $summary['orderCount']],
            ['Units Sold', (string) $summary['unitsSold']],
            ['Average Order', $summary['averageOrderFormatted']],
            [],
            ['Best Seller', 'Units', 'Revenue'],
        ];

        foreach ($summary['bestSellers'] as $item) {
            $lines[] = [$item['name'], (string) $item['units'], $item['revenueFormatted']];
        }

        $stream = fopen('php://temp', 'r+');
        foreach ($lines as $row) {
            fputcsv($stream, $row);
        }
        rewind($stream);
        $csv = stream_get_contents($stream) ?: '';
        fclose($stream);

        return $csv;
    }
}
