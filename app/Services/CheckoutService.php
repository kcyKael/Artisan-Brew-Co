<?php

namespace App\Services;

use App\Exceptions\CheckoutException;
use App\Models\Addon;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Sale;
use App\Models\SaleAddon;
use App\Models\User;
use App\Support\BusinessTime;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CheckoutService
{
    public function priceLine(
        Product $product,
        ?ProductVariant $variant,
        Collection $addons,
        int $quantity
    ): array {
        $baseCentavos = Money::toCentavos((string) $product->price);
        $variantAdjCentavos = $variant ? Money::toCentavos((string) $variant->price_adjustment) : 0;
        $addonsTotalCentavos = $addons->sum(fn (Addon $addon) => Money::toCentavos((string) $addon->price));

        $unitPriceCentavos = $baseCentavos + $variantAdjCentavos + $addonsTotalCentavos;
        $lineTotalCentavos = $unitPriceCentavos * $quantity;

        if ($unitPriceCentavos > Money::MAX_CENTAVOS || $lineTotalCentavos > Money::MAX_CENTAVOS) {
            throw new CheckoutException('Calculated line amount exceeds the supported maximum.', 422, 'items');
        }

        return [
            'base_centavos' => $baseCentavos,
            'variant_adjustment_centavos' => $variantAdjCentavos,
            'addons_total_centavos' => $addonsTotalCentavos,
            'unit_price_centavos' => $unitPriceCentavos,
            'line_total_centavos' => $lineTotalCentavos,
        ];
    }

    public function complete(User $staff, array $payload): CheckoutResult
    {
        if (! $staff->isActiveAccount()) {
            throw new CheckoutException('Your staff account is inactive.', 403);
        }

        $checkoutToken = trim((string) ($payload['checkout_token'] ?? ''));
        if ($checkoutToken === '' || strlen($checkoutToken) < 16 || strlen($checkoutToken) > 64) {
            throw new CheckoutException('Invalid checkout session token. Please refresh and try again.', 422, 'checkout_token');
        }

        $rawItems = $payload['items'] ?? [];
        if (! is_array($rawItems) || count($rawItems) === 0) {
            throw new CheckoutException('Your cart is empty. Add at least one item before checking out.', 422, 'items');
        }

        $source = ($payload['source'] ?? 'pos') === 'record_sale' ? 'record_sale' : 'pos';
        $customerId = isset($payload['customer_id']) && $payload['customer_id'] !== '' ? (int) $payload['customer_id'] : null;

        try {
            $cashReceivedCentavos = Money::toCentavos((string) ($payload['cash_received'] ?? ''));
        } catch (\InvalidArgumentException) {
            throw new CheckoutException('Please enter a valid cash received amount.', 422, 'cash_received');
        }

        if ($cashReceivedCentavos < 0 || $cashReceivedCentavos > Money::MAX_CENTAVOS) {
            throw new CheckoutException('Cash received amount is out of range.', 422, 'cash_received');
        }

        $normalizedItems = $this->normalizeItems($rawItems);
        $requestHash = $this->computeRequestHash($customerId, $normalizedItems, $cashReceivedCentavos, $source);

        try {
            return DB::transaction(function () use (
                $staff,
                $checkoutToken,
                $requestHash,
                $customerId,
                $normalizedItems,
                $cashReceivedCentavos,
                $source
            ): CheckoutResult {
                $freshStaff = User::query()->whereKey($staff->id)->first();
                if (! $freshStaff || ! $freshStaff->isActiveAccount()) {
                    throw new CheckoutException('Your staff account is no longer active.', 403);
                }

                $existingOrder = Order::query()
                    ->where('sold_by', $freshStaff->id)
                    ->where('checkout_token', $checkoutToken)
                    ->lockForUpdate()
                    ->first();

                if ($existingOrder) {
                    return $this->resolveExistingTokenOrder($existingOrder, $requestHash);
                }

                $customerNameSnapshot = 'Walk-in customer';
                if ($customerId !== null) {
                    $customer = Customer::query()->whereKey($customerId)->first();
                    if (! $customer) {
                        throw new CheckoutException('The selected customer was not found or has been removed.', 422, 'customer_id');
                    }
                    $customerNameSnapshot = $customer->full_name;
                }

                $productIds = collect($normalizedItems)
                    ->pluck('product_id')
                    ->unique()
                    ->sort()
                    ->values()
                    ->all();

                $products = Product::query()
                    ->with(['variants', 'addons'])
                    ->whereIn('id', $productIds)
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get()
                    ->keyBy('id');

                $requestedByProduct = [];
                $firstLineByProduct = [];
                $preparedLines = [];
                $orderTotalCentavos = 0;

                foreach ($normalizedItems as $line) {
                    $lineIndex = $line['original_index'];
                    $productId = $line['product_id'];
                    $quantity = $line['quantity'];

                    $product = $products->get($productId);

                    if (! $product || $product->isArchived() || ! $product->is_active) {
                        throw new CheckoutException(
                            'One of the selected menu items is unavailable or archived.',
                            422,
                            'items',
                            $lineIndex,
                            $productId
                        );
                    }

                    $activeVariants = $product->variants->where('is_active', true);
                    $selectedVariant = null;

                    if ($activeVariants->isNotEmpty()) {
                        if ($line['product_variant_id'] === null) {
                            throw new CheckoutException(
                                "Please choose a size and temperature option for {$product->name}.",
                                422,
                                'items',
                                $lineIndex,
                                $productId
                            );
                        }

                        $selectedVariant = $activeVariants->firstWhere('id', $line['product_variant_id']);
                        if (! $selectedVariant) {
                            throw new CheckoutException(
                                "The selected option for {$product->name} is no longer available.",
                                422,
                                'items',
                                $lineIndex,
                                $productId
                            );
                        }
                    } elseif ($line['product_variant_id'] !== null) {
                        throw new CheckoutException(
                            "{$product->name} does not support drink size/temperature variants.",
                            422,
                            'items',
                            $lineIndex,
                            $productId
                        );
                    }

                    $eligibleAddons = $product->addons->where('is_active', true)->keyBy('id');
                    $selectedAddons = collect();

                    foreach ($line['addon_ids'] as $addonId) {
                        $addon = $eligibleAddons->get($addonId);
                        if (! $addon) {
                            throw new CheckoutException(
                                "An add-on selected for {$product->name} is ineligible or inactive.",
                                422,
                                'items',
                                $lineIndex,
                                $productId
                            );
                        }
                        $selectedAddons->push($addon);
                    }

                    $requestedByProduct[$productId] = ($requestedByProduct[$productId] ?? 0) + $quantity;
                    if (! isset($firstLineByProduct[$productId])) {
                        $firstLineByProduct[$productId] = $lineIndex;
                    }

                    $pricing = $this->priceLine($product, $selectedVariant, $selectedAddons, $quantity);
                    $orderTotalCentavos += $pricing['line_total_centavos'];

                    if ($orderTotalCentavos > Money::MAX_CENTAVOS) {
                        throw new CheckoutException('Order total exceeds the supported transaction limit.', 422, 'items', $lineIndex, $productId);
                    }

                    $preparedLines[] = [
                        'product' => $product,
                        'variant' => $selectedVariant,
                        'addons' => $selectedAddons,
                        'quantity' => $quantity,
                        'pricing' => $pricing,
                    ];
                }

                foreach ($requestedByProduct as $productId => $totalNeeded) {
                    $product = $products->get($productId);
                    if ($totalNeeded > $product->stock_quantity) {
                        throw new CheckoutException(
                            "Insufficient stock for {$product->name}: requested {$totalNeeded}, available {$product->stock_quantity}.",
                            422,
                            'items',
                            $firstLineByProduct[$productId],
                            $productId
                        );
                    }
                }

                if ($cashReceivedCentavos < $orderTotalCentavos) {
                    $shortCentavos = $orderTotalCentavos - $cashReceivedCentavos;
                    throw new CheckoutException(
                        'Insufficient cash received. Short by '.Money::formatCentavos($shortCentavos).'.',
                        422,
                        'cash_received'
                    );
                }

                $changeCentavos = $cashReceivedCentavos - $orderTotalCentavos;
                $now = CarbonImmutable::now('UTC');

                $order = new Order();
                $order->order_number = 'TMP-'.Str::upper(Str::random(18));
                $order->customer_id = $customerId;
                $order->sold_by = $freshStaff->id;
                $order->customer_name_snapshot = $customerNameSnapshot;
                $order->staff_name_snapshot = $freshStaff->full_name;
                $order->source = $source;
                $order->subtotal_amount = Money::fromCentavos($orderTotalCentavos);
                $order->total_amount = Money::fromCentavos($orderTotalCentavos);
                $order->cash_received = Money::fromCentavos($cashReceivedCentavos);
                $order->change_amount = Money::fromCentavos($changeCentavos);
                $order->checkout_token = $checkoutToken;
                $order->request_hash = $requestHash;
                $order->created_at = $now;
                $order->updated_at = $now;
                $order->save();

                $manilaDate = BusinessTime::toManila($now)?->format('Ymd') ?? $now->format('Ymd');
                $order->order_number = sprintf('ABC-%s-%06d', $manilaDate, $order->id);
                $order->save();

                foreach ($preparedLines as $prepared) {
                    $product = $prepared['product'];
                    $variant = $prepared['variant'];
                    $addons = $prepared['addons'];
                    $pricing = $prepared['pricing'];

                    $sale = new Sale();
                    $sale->order_id = $order->id;
                    $sale->product_id = $product->id;
                    $sale->product_variant_id = $variant?->id;
                    $sale->customer_id = $customerId;
                    $sale->sold_by = $freshStaff->id;
                    $sale->product_name_snapshot = $product->name;
                    $sale->variant_label_snapshot = $variant?->displayLabel();
                    $sale->base_price_snapshot = Money::fromCentavos($pricing['base_centavos']);
                    $sale->variant_adjustment_snapshot = Money::fromCentavos($pricing['variant_adjustment_centavos']);
                    $sale->addons_total_snapshot = Money::fromCentavos($pricing['addons_total_centavos']);
                    $sale->unit_price = Money::fromCentavos($pricing['unit_price_centavos']);
                    $sale->quantity = $prepared['quantity'];
                    $sale->total_price = Money::fromCentavos($pricing['line_total_centavos']);
                    $sale->created_at = $now;
                    $sale->updated_at = $now;
                    $sale->save();

                    foreach ($addons as $addon) {
                        $saleAddon = new SaleAddon();
                        $saleAddon->sale_id = $sale->id;
                        $saleAddon->addon_id = $addon->id;
                        $saleAddon->addon_name_snapshot = $addon->name;
                        $saleAddon->addon_price_snapshot = Money::fromCentavos(Money::toCentavos((string) $addon->price));
                        $saleAddon->created_at = $now;
                        $saleAddon->save();
                    }
                }

                ksort($requestedByProduct);
                foreach ($requestedByProduct as $productId => $qtyToDeduct) {
                    $affected = DB::table('products')
                        ->where('id', $productId)
                        ->whereNull('archived_at')
                        ->where('is_active', true)
                        ->where('stock_quantity', '>=', $qtyToDeduct)
                        ->update([
                            'stock_quantity' => DB::raw('stock_quantity - '.(int) $qtyToDeduct),
                            'stock_version' => DB::raw('stock_version + 1'),
                            'updated_at' => $now,
                        ]);

                    if ($affected !== 1) {
                        $product = $products->get($productId);
                        throw new CheckoutException(
                            "Stock changed for {$product->name} while finalizing your order. Please try again.",
                            409,
                            'items',
                            $firstLineByProduct[$productId],
                            $productId
                        );
                    }
                }

                return new CheckoutResult(
                    $order->load(['sales.saleAddons', 'customer', 'staff']),
                    false
                );
            }, 3);
        } catch (QueryException $e) {
            if ($this->isDuplicateCheckoutTokenException($e)) {
                $existingOrder = Order::query()
                    ->where('sold_by', $staff->id)
                    ->where('checkout_token', $checkoutToken)
                    ->first();

                if ($existingOrder) {
                    return $this->resolveExistingTokenOrder($existingOrder, $requestHash);
                }
            }

            throw $e;
        }
    }

    private function normalizeItems(array $rawItems): array
    {
        $normalized = [];

        foreach (array_values($rawItems) as $index => $item) {
            if (! is_array($item)) {
                throw new CheckoutException('Invalid cart item entry.', 422, 'items', $index);
            }

            $productId = filter_var($item['product_id'] ?? null, FILTER_VALIDATE_INT);
            if ($productId === false || $productId <= 0) {
                throw new CheckoutException('Invalid product selection in cart.', 422, 'items', $index);
            }

            $rawQty = $item['quantity'] ?? null;
            if (is_string($rawQty) && ! preg_match('/^\d+$/', trim($rawQty))) {
                throw new CheckoutException('Quantity must be a positive whole number.', 422, 'items', $index, $productId);
            }
            $quantity = filter_var($rawQty, FILTER_VALIDATE_INT);
            if ($quantity === false || $quantity <= 0 || $quantity > 999) {
                throw new CheckoutException('Quantity must be a whole number between 1 and 999.', 422, 'items', $index, $productId);
            }

            $variantId = null;
            if (isset($item['product_variant_id']) && $item['product_variant_id'] !== '' && $item['product_variant_id'] !== null) {
                $parsedVariant = filter_var($item['product_variant_id'], FILTER_VALIDATE_INT);
                if ($parsedVariant === false || $parsedVariant <= 0) {
                    throw new CheckoutException('Invalid drink variant selection.', 422, 'items', $index, $productId);
                }
                $variantId = $parsedVariant;
            }

            $addonIds = [];
            $rawAddons = $item['addon_ids'] ?? [];
            if (! is_array($rawAddons)) {
                throw new CheckoutException('Invalid add-ons format.', 422, 'items', $index, $productId);
            }

            foreach ($rawAddons as $rawAddonId) {
                $parsedAddon = filter_var($rawAddonId, FILTER_VALIDATE_INT);
                if ($parsedAddon === false || $parsedAddon <= 0) {
                    throw new CheckoutException('Invalid add-on selection.', 422, 'items', $index, $productId);
                }
                if (in_array($parsedAddon, $addonIds, true)) {
                    throw new CheckoutException('Duplicate add-ons are not allowed on a single item.', 422, 'items', $index, $productId);
                }
                $addonIds[] = $parsedAddon;
            }

            sort($addonIds);

            $normalized[] = [
                'original_index' => $index,
                'product_id' => $productId,
                'product_variant_id' => $variantId,
                'addon_ids' => $addonIds,
                'quantity' => $quantity,
            ];
        }

        return $normalized;
    }

    private function computeRequestHash(?int $customerId, array $normalizedItems, int $cashReceivedCentavos, string $source): string
    {
        $canonicalItems = array_map(fn (array $item) => [
            'product_id' => $item['product_id'],
            'product_variant_id' => $item['product_variant_id'],
            'addon_ids' => $item['addon_ids'],
            'quantity' => $item['quantity'],
        ], $normalizedItems);

        usort($canonicalItems, static function (array $a, array $b): int {
            return [$a['product_id'], $a['product_variant_id'] ?? 0, implode(',', $a['addon_ids']), $a['quantity']]
                <=> [$b['product_id'], $b['product_variant_id'] ?? 0, implode(',', $b['addon_ids']), $b['quantity']];
        });

        $payload = json_encode([
            'customer_id' => $customerId,
            'cash_received_centavos' => $cashReceivedCentavos,
            'source' => $source,
            'items' => $canonicalItems,
        ], JSON_THROW_ON_ERROR);

        return hash('sha256', $payload);
    }

    private function resolveExistingTokenOrder(Order $existingOrder, string $requestHash): CheckoutResult
    {
        if (! hash_equals($existingOrder->request_hash, $requestHash)) {
            throw new CheckoutException(
                'This checkout token was already used for a different cart. Please start a fresh order.',
                409,
                'checkout_token'
            );
        }

        return new CheckoutResult(
            $existingOrder->load(['sales.saleAddons', 'customer', 'staff']),
            true
        );
    }

    private function isDuplicateCheckoutTokenException(QueryException $e): bool
    {
        $sqlState = (string) ($e->errorInfo[0] ?? '');
        $driverCode = (int) ($e->errorInfo[1] ?? 0);

        return $sqlState === '23000'
            && $driverCode === 1062
            && str_contains($e->getMessage(), 'orders_staff_checkout_token_unique');
    }
}
