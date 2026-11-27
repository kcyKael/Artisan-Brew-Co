<?php

namespace App\Services\Email;

use App\Models\Product;
use App\Models\User;
use App\Support\BusinessTime;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class StockAlertService
{
    public function __construct(
        private readonly TransactionalEmailService $transactional,
        private readonly EmailSettingsService $settings,
    ) {
    }

    public function evaluateProducts(array $productIds, ?User $actor = null): int
    {
        $cleanIds = array_values(array_unique(array_filter(array_map('intval', $productIds), static fn (int $id) => $id > 0)));
        if ($cleanIds === []) {
            return 0;
        }

        $alertsToSend = DB::transaction(function () use ($cleanIds): array {
            sort($cleanIds);
            $products = Product::query()
                ->whereIn('id', $cleanIds)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            $triggered = [];

            foreach ($products as $product) {
                if ($product->isArchived() || ! $product->is_active) {
                    continue;
                }

                $newState = $this->computeState($product);
                $previousState = $product->stock_alert_state ?: 'normal';

                if ($newState === $previousState) {
                    continue;
                }

                $product->stock_alert_state = $newState;

                if ($newState === 'low' || $newState === 'sold_out') {
                    $product->last_stock_alert_at = now('UTC');
                    $triggered[] = [
                        'product' => $product->replicate(),
                        'id' => $product->id,
                        'state' => $newState,
                        'version' => (int) $product->stock_version,
                    ];
                }

                $product->save();
            }

            return $triggered;
        });

        if ($alertsToSend === []) {
            return 0;
        }

        if (! $this->settings->featureEnabled('feature_low_stock')) {
            return 0;
        }

        if ($this->settings->stockAlertMode() !== 'immediate') {
            return 0;
        }

        $recipients = $this->settings->ownerAlertEmails();
        if ($recipients === []) {
            return 0;
        }

        $sent = 0;
        foreach ($alertsToSend as $entry) {
            $product = $entry['product'];
            $state = $entry['state'];
            $stateLabel = $state === 'sold_out' ? 'Sold out' : 'Low stock';

            foreach ($recipients as $email) {
                $messageKey = sprintf(
                    'stock-alert:%d:%s:v%d:%s',
                    $entry['id'],
                    $state,
                    $entry['version'],
                    hash('crc32b', $email)
                );

                $this->transactional->queue(
                    messageKey: $messageKey,
                    purpose: 'low_stock_alert',
                    recipientEmail: $email,
                    recipientName: null,
                    subject: "{$stateLabel}: {$product->name} ({$product->stock_quantity} left) — Artisan Brew Co.",
                    templateName: 'low_stock_alert',
                    templateData: [
                        'mode' => 'immediate',
                        'alertTime' => BusinessTime::format(now('UTC')).' PHT',
                        'productsUrl' => route('products.index'),
                        'items' => [[
                            'name' => $product->name,
                            'sku' => $product->sku,
                            'stock' => (int) $product->stock_quantity,
                            'threshold' => (int) $product->low_stock_threshold,
                            'state' => $stateLabel,
                        ]],
                    ],
                    options: [
                        'related_type' => 'product',
                        'related_id' => $entry['id'],
                        'initiated_by' => $actor?->id,
                    ]
                );
                $sent++;
            }
        }

        return $sent;
    }

    public function sendDigest(?User $actor = null): int
    {
        if (! $this->settings->featureEnabled('feature_low_stock')) {
            return 0;
        }

        $items = Product::query()
            ->whereNull('archived_at')
            ->where('is_active', true)
            ->whereColumn('stock_quantity', '<=', 'low_stock_threshold')
            ->orderBy('stock_quantity')
            ->orderBy('name')
            ->get()
            ->map(fn (Product $product): array => [
                'name' => $product->name,
                'sku' => $product->sku,
                'stock' => (int) $product->stock_quantity,
                'threshold' => (int) $product->low_stock_threshold,
                'state' => $product->isSoldOut() ? 'Sold out' : 'Low stock',
            ])
            ->all();

        if ($items === []) {
            return 0;
        }

        $recipients = $this->settings->ownerAlertEmails();
        $dateSlug = BusinessTime::nowManila()->format('Y-m-d-H');
        $sent = 0;

        foreach ($recipients as $email) {
            $this->transactional->queue(
                messageKey: 'stock-digest:'.$dateSlug.':'.($actor ? Str::uuid() : hash('crc32b', $email)),
                purpose: 'low_stock_alert',
                recipientEmail: $email,
                recipientName: null,
                subject: 'Inventory Stock Digest ('.count($items).' items) — Artisan Brew Co.',
                templateName: 'low_stock_alert',
                templateData: [
                    'mode' => 'digest',
                    'alertTime' => BusinessTime::format(now('UTC')).' PHT',
                    'productsUrl' => route('products.index'),
                    'items' => $items,
                ],
                options: [
                    'initiated_by' => $actor?->id,
                ]
            );
            $sent++;
        }

        return $sent;
    }

    public function computeState(Product $product): string
    {
        if ($product->isSoldOut()) {
            return 'sold_out';
        }

        if ($product->isLowStock()) {
            return 'low';
        }

        return 'normal';
    }
}
