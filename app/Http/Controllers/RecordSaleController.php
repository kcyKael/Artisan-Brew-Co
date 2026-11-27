<?php

namespace App\Http\Controllers;

use App\Exceptions\CheckoutException;
use App\Http\Requests\RecordSaleRequest;
use App\Models\Customer;
use App\Models\Product;
use App\Services\CheckoutService;
use App\Services\Email\MarketingSubscriberService;
use App\Services\Email\OrderReceiptEmailService;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Illuminate\View\View;

class RecordSaleController extends Controller
{
    public function create(): View
    {
        $products = Product::query()
            ->availableForPos()
            ->with(['category', 'activeVariants', 'activeAddons'])
            ->orderBy('name')
            ->get();

        $customers = Customer::query()
            ->orderBy('full_name')
            ->get();

        $productsJson = $products->mapWithKeys(fn (Product $p) => [
            $p->id => [
                'id' => $p->id,
                'name' => $p->name,
                'price_centavos' => Money::toCentavos((string) $p->price),
                'stock_quantity' => $p->stock_quantity,
                'variants' => $p->activeVariants->map(fn ($v) => [
                    'id' => $v->id,
                    'label' => $v->displayLabel(),
                    'adjustment_centavos' => Money::toCentavos((string) $v->price_adjustment),
                ])->values()->all(),
                'addons' => $p->activeAddons->map(fn ($a) => [
                    'id' => $a->id,
                    'name' => $a->name,
                    'price_centavos' => Money::toCentavos((string) $a->price),
                ])->values()->all(),
            ],
        ])->all();

        return view('sales.record', [
            'products' => $products,
            'customers' => $customers,
            'productsJson' => $productsJson,
            'checkoutToken' => Str::uuid()->toString(),
        ]);
    }

    public function store(
        RecordSaleRequest $request,
        CheckoutService $checkout,
        OrderReceiptEmailService $receiptEmails,
        MarketingSubscriberService $subscribers,
    ): RedirectResponse {
        $validated = $request->validated();

        try {
            $result = $checkout->complete($request->user(), [
                'checkout_token' => $validated['checkout_token'],
                'customer_id' => $validated['customer_id'] ?? null,
                'order_type' => $validated['order_type'] ?? 'dine_in',
                'payment_method' => $validated['payment_method'] ?? 'cash',
                'payment_reference' => $validated['payment_reference'] ?? null,
                'cup_name' => $validated['cup_name'] ?? null,
                'order_note' => $validated['order_note'] ?? null,
                'discount_type' => $validated['discount_type'] ?? 'none',
                'discount_value' => $validated['discount_value'] ?? null,
                'gift_card_code' => $validated['gift_card_code'] ?? null,
                'cash_received' => $validated['cash_received'] ?? null,
                'source' => 'record_sale',
                'items' => [
                    [
                        'product_id' => $validated['product_id'],
                        'product_variant_id' => $validated['product_variant_id'] ?? null,
                        'addon_ids' => $validated['addon_ids'] ?? [],
                        'quantity' => $validated['quantity'],
                        'note' => $validated['note'] ?? null,
                    ],
                ],
            ]);
        } catch (CheckoutException $e) {
            $errorField = match ($e->field) {
                'cash_received' => 'cash_received',
                'customer_id' => 'customer_id',
                'order_type' => 'order_type',
                'payment_method' => 'payment_method',
                'discount_type' => 'discount_type',
                'discount_value' => 'discount_value',
                'gift_card_code' => 'gift_card_code',
                default => 'quantity',
            };

            return back()
                ->withInput()
                ->withErrors([$errorField => $e->getMessage()]);
        }

        $order = $result->order;

        $requestedReceiptEmail = trim((string) ($validated['receipt_email'] ?? ''));
        if ($requestedReceiptEmail !== '' && ! $result->wasReplay) {
            $receiptEmails->queueInitialReceipt($order, $requestedReceiptEmail, $request->user());
            if ($request->boolean('marketing_opt_in')) {
                $subscribers->requestSubscription(
                    $requestedReceiptEmail,
                    $order->customer_name_snapshot ?: $order->customer?->full_name,
                    'record_sale_opt_in',
                    $order->customer
                );
            }
        }

        return redirect()
            ->route('orders.receipt', $order)
            ->with('status', "Sale recorded successfully under receipt {$order->order_number}.");
    }
}
