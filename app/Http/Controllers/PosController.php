<?php

namespace App\Http\Controllers;

use App\Exceptions\CheckoutException;
use App\Http\Requests\PosCheckoutRequest;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Product;
use App\Services\CheckoutService;
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

    public function checkout(PosCheckoutRequest $request, CheckoutService $checkout): JsonResponse
    {
        try {
            $result = $checkout->complete($request->user(), [
                'checkout_token' => $request->validated('checkout_token'),
                'customer_id' => $request->validated('customer_id'),
                'cash_received' => $request->validated('cash_received'),
                'source' => 'pos',
                'items' => $request->validated('items'),
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
        $updatedStocks = Product::query()
            ->whereIn('id', $order->sales->pluck('product_id')->unique()->all())
            ->pluck('stock_quantity', 'id');

        return response()->json([
            'message' => $result->wasReplay
                ? "Order {$order->order_number} already completed."
                : "Sale completed — Receipt {$order->order_number}.",
            'was_replay' => $result->wasReplay,
            'next_checkout_token' => Str::uuid()->toString(),
            'stocks' => $updatedStocks,
            'order' => [
                'id' => $order->id,
                'order_number' => $order->order_number,
                'customer_name' => $order->customer_name_snapshot,
                'staff_name' => $order->staff_name_snapshot,
                'created_at_manila' => BusinessTime::format($order->created_at),
                'total_formatted' => Money::formatPhp((string) $order->total_amount),
                'cash_formatted' => Money::formatPhp((string) $order->cash_received),
                'change_formatted' => Money::formatPhp((string) $order->change_amount),
                'receipt_url' => route('orders.receipt', $order),
                'detail_url' => route('orders.show', $order),
                'lines' => $order->sales->map(fn ($sale) => [
                    'product_name' => $sale->product_name_snapshot,
                    'variant_label' => $sale->variant_label_snapshot,
                    'addons' => $sale->saleAddons->pluck('addon_name_snapshot')->all(),
                    'quantity' => $sale->quantity,
                    'unit_price_formatted' => Money::formatPhp((string) $sale->unit_price),
                    'total_price_formatted' => Money::formatPhp((string) $sale->total_price),
                ])->values()->all(),
            ],
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
