<?php

namespace App\Services\Email;

use App\Models\EmailDelivery;
use App\Models\Order;
use App\Models\User;
use App\Support\BusinessTime;
use App\Support\Money;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

class OrderReceiptEmailService
{
    public function __construct(
        private readonly TransactionalEmailService $transactional,
        private readonly EmailSettingsService $settings,
    ) {
    }

    public function queueInitialReceipt(Order $order, string $recipientEmail, ?User $actor = null): ?EmailDelivery
    {
        return $this->queueForOrder(
            $order,
            $recipientEmail,
            $actor,
            'receipt:order:'.$order->id.':initial'
        );
    }

    public function queueResendReceipt(Order $order, string $recipientEmail, ?User $actor = null): ?EmailDelivery
    {
        return $this->queueForOrder(
            $order,
            $recipientEmail,
            $actor,
            'receipt:order:'.$order->id.':resend:'.Str::uuid()
        );
    }

    private function queueForOrder(Order $order, string $recipientEmail, ?User $actor, string $messageKey): ?EmailDelivery
    {
        if (! $this->settings->featureEnabled('feature_receipts')) {
            return null;
        }

        if ($order->status !== 'completed') {
            return null;
        }

        $cleanEmail = mb_strtolower(trim($recipientEmail));
        if (! filter_var($cleanEmail, FILTER_VALIDATE_EMAIL)) {
            return null;
        }

        $order->loadMissing(['sales.saleAddons', 'customer', 'staff']);

        $items = $order->sales->map(function ($sale): array {
            $addons = $sale->saleAddons->pluck('addon_name_snapshot')->filter()->implode(', ');

            return [
                'name' => $sale->product_name_snapshot,
                'variant' => $sale->variant_label_snapshot,
                'addons' => $addons !== '' ? $addons : null,
                'note' => $sale->note,
                'quantity' => (int) $sale->quantity,
                'unitPriceFormatted' => Money::formatPhp($sale->unit_price),
                'lineTotalFormatted' => Money::formatPhp($sale->total_price),
            ];
        })->all();

        $expireDays = max(1, (int) config('email.receipt_link_expire_days', 7));
        $signedUrl = URL::temporarySignedRoute(
            'receipts.signed',
            now('UTC')->addDays($expireDays),
            ['order' => $order->id]
        );

        $orderTypeLabel = ($order->order_type ?? 'dine_in') === 'take_out' ? 'Take-out' : 'Dine-in';
        $paymentLabel = match ($order->payment_method ?? 'cash') {
            'card' => 'Card',
            'gcash' => 'GCash',
            default => 'Cash',
        };

        $customerName = $order->customer_name_snapshot
            ?: ($order->customer?->full_name ?? 'Walk-in customer');

        return $this->transactional->queue(
            messageKey: $messageKey,
            purpose: 'receipt',
            recipientEmail: $cleanEmail,
            recipientName: $customerName,
            subject: 'Your Artisan Brew Co. Receipt · '.$order->order_number,
            templateName: 'receipt',
            templateData: [
                'orderNumber' => $order->order_number,
                'purchasedAt' => BusinessTime::format($order->created_at).' PHT',
                'orderTypeLabel' => $orderTypeLabel,
                'paymentLabel' => $paymentLabel,
                'customerName' => $customerName,
                'cupName' => $order->cup_name,
                'orderNote' => $order->order_note,
                'discountLabel' => (float) $order->discount_amount > 0 ? ($order->discount_label ?: 'Discount') : null,
                'discountFormatted' => Money::formatPhp($order->discount_amount ?? 0),
                'subtotalFormatted' => Money::formatPhp($order->subtotal_amount),
                'totalFormatted' => Money::formatPhp($order->total_amount),
                'cashFormatted' => Money::formatPhp($order->cash_received),
                'changeFormatted' => Money::formatPhp($order->change_amount),
                'signedReceiptUrl' => $signedUrl,
                'items' => $items,
            ],
            options: [
                'related_type' => 'order',
                'related_id' => $order->id,
                'initiated_by' => $actor?->id ?? $order->sold_by,
                'sensitive' => false,
                'expires_at' => now('UTC')->addDays($expireDays),
            ]
        );
    }
}
