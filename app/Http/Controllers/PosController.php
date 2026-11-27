<?php

namespace App\Http\Controllers;

use App\Exceptions\CheckoutException;
use App\Http\Requests\PosCheckoutRequest;
use App\Models\Category;
use App\Models\Customer;
use App\Models\GiftCard;
use App\Models\Product;
use App\Services\CheckoutService;
use App\Services\Email\MarketingSubscriberService;
use App\Services\Email\OrderReceiptEmailService;
use App\Support\BusinessTime;
use App\Support\Money;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class PosController extends Controller
{
    public function index(): View
    {
        $categories = Category::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $products = Product::query()
            ->availableForPos()
            ->with(['category', 'activeVariants', 'activeAddons'])
            ->orderBy('name')
            ->get();

        $customers = Customer::query()
            ->orderBy('full_name')
            ->limit(50)
            ->get();

        $catalogPayload = $products->map(function (Product $product): array {
            return [
                'id' => $product->id,
                'name' => $product->name,
                'sku' => $product->sku,
                'category_id' => $product->category_id,
                'category_name' => $product->category?->name ?? 'Uncategorized',
                'price' => (float) $product->price,
                'price_centavos' => Money::toCentavos((string) $product->price),
                'stock_quantity' => $product->stock_quantity,
                'low_stock_threshold' => $product->low_stock_threshold,
                'image_url' => $product->imageUrl(),
                'variants' => $product->activeVariants->map(fn ($v) => [
                    'id' => $v->id,
                    'size_label' => $v->size_label,
                    'temperature' => $v->temperature,
                    'display_label' => $v->displayLabel(),
                    'price_adjustment' => (float) $v->price_adjustment,
                    'price_adjustment_centavos' => Money::toCentavos((string) $v->price_adjustment),
                ])->values()->all(),
                'addons' => $product->activeAddons->map(fn ($a) => [
                    'id' => $a->id,
                    'name' => $a->name,
                    'price' => (float) $a->price,
                    'price_centavos' => Money::toCentavos((string) $a->price),
                ])->values()->all(),
            ];
        })->values()->all();

        return view('pos.index', [
            'categories' => $categories,
            'products' => $products,
            'catalogPayload' => $catalogPayload,
            'customers' => $customers,
            'checkoutToken' => Str::uuid()->toString(),
        ]);
    }

    public function checkout(
        PosCheckoutRequest $request,
        CheckoutService $checkout,
        OrderReceiptEmailService $receiptEmails,
        MarketingSubscriberService $subscribers,
    ): JsonResponse {
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
                'source' => 'pos',
                'items' => $validated['items'],
            ]);
        } catch (CheckoutException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'field' => $e->field,
                'item_index' => $e->itemIndex,
                'product_id' => $e->productId,
            ], $e->statusCode);
        }

        $order = $result->order;

        $receiptEmailStatus = null;
        $requestedReceiptEmail = trim((string) ($validated['receipt_email'] ?? ''));
        if ($requestedReceiptEmail !== '' && ! $result->wasReplay) {
            $delivery = $receiptEmails->queueInitialReceipt($order, $requestedReceiptEmail, $request->user());
            $receiptEmailStatus = $delivery?->status;

            if ($request->boolean('marketing_opt_in')) {
                $subscribers->requestSubscription(
                    $requestedReceiptEmail,
                    $order->customer_name_snapshot ?: $order->customer?->full_name,
                    'pos_checkout_opt_in',
                    $order->customer
                );
            }
        }

        $updatedStocks = Product::query()
            ->whereIn('id', $order->sales->pluck('product_id')->unique()->all())
            ->pluck('stock_quantity', 'id');

        return response()->json([
            'message' => $result->wasReplay
                ? "Order {$order->order_number} already completed."
                : "Sale completed — Receipt {$order->order_number}.",
            'was_replay' => $result->wasReplay,
            'next_checkout_token' => Str::uuid()->toString(),
            'receipt_email_status' => $receiptEmailStatus,
            'stocks' => $updatedStocks,
            'order' => [
                'id' => $order->id,
                'order_number' => $order->order_number,
                'customer_name' => $order->customer_name_snapshot,
                'customer_email' => $order->customer?->email,
                'staff_name' => $order->staff_name_snapshot,
                'order_type' => $order->order_type,
                'payment_method' => $order->payment_method,
                'payment_reference' => $order->payment_reference,
                'cup_name' => $order->cup_name,
                'order_note' => $order->order_note,
                'discount_type' => $order->discount_type,
                'discount_label' => $order->discount_label,
                'discount_amount' => (string) $order->discount_amount,
                'discount_formatted' => Money::formatPhp((string) $order->discount_amount),
                'subtotal_formatted' => Money::formatPhp((string) $order->subtotal_amount),
                'created_at_manila' => BusinessTime::format($order->created_at),
                'total_formatted' => Money::formatPhp((string) $order->total_amount),
                'cash_formatted' => Money::formatPhp((string) $order->cash_received),
                'change_formatted' => Money::formatPhp((string) $order->change_amount),
                'receipt_url' => route('orders.receipt', $order),
                'detail_url' => route('orders.show', $order),
                'email_receipt_url' => route('orders.email-receipt', $order),
                'lines' => $order->sales->map(fn ($sale) => [
                    'product_name' => $sale->product_name_snapshot,
                    'variant_label' => $sale->variant_label_snapshot,
                    'addons' => $sale->saleAddons->pluck('addon_name_snapshot')->all(),
                    'quantity' => $sale->quantity,
                    'note' => $sale->note,
                    'unit_price_formatted' => Money::formatPhp((string) $sale->unit_price),
                    'total_price_formatted' => Money::formatPhp((string) $sale->total_price),
                ])->values()->all(),
            ],
        ]);
    }

    public function lookupGiftCard(Request $request): JsonResponse
    {
        $code = strtoupper(trim((string) $request->query('code', '')));
        $giftCard = $code !== '' ? GiftCard::query()->where('code', $code)->first() : null;
        $valid = $giftCard !== null && $giftCard->isUsable();
        $remaining = $giftCard !== null ? (string) $giftCard->remaining_balance : '0.00';

        return response()->json([
            'valid' => $valid,
            'code' => $code,
            'remaining_balance' => $remaining,
            'remaining_formatted' => Money::formatPhp($remaining),
        ]);
    }

    public function searchCustomers(Request $request): JsonResponse
    {
        $term = trim((string) $request->query('q', ''));

        $customers = Customer::query()
            ->when($term !== '', function ($query) use ($term) {
                $like = '%'.$term.'%';
                $query->where(function ($sub) use ($like) {
                    $sub->where('full_name', 'like', $like)
                        ->orWhere('email', 'like', $like)
                        ->orWhere('phone', 'like', $like);
                });
            })
            ->orderBy('full_name')
            ->limit(25)
            ->get()
            ->map(fn (Customer $c) => [
                'id' => $c->id,
                'full_name' => $c->full_name,
                'email' => $c->email,
                'phone' => $c->phone,
                'label' => "{$c->full_name} — {$c->displayIdentifier()}",
            ]);

        return response()->json(['customers' => $customers]);
    }
}
