@php
use App\Support\Money;
@endphp

<x-layouts.app title="Record Sale">
    <div class="max-w-2xl mx-auto space-y-6">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            <div>
                <h1 class="font-display text-3xl sm:text-4xl font-semibold text-[#F5F0E8]">Record Sale</h1>
                <p class="text-sm text-[#BDB5AA] mt-1">
                    Single-product counter checkout. Decrements stock atomically and blocks overselling.
                </p>
            </div>
            <a href="{{ route('pos.index') }}" class="abc-btn-secondary self-start sm:self-auto">Multi-Item POS</a>
        </div>

        <form id="record-sale-form"
              method="POST"
              action="{{ route('sales.record.store') }}"
              data-products="{{ json_encode($productsJson) }}"
              data-old-variant="{{ old('product_variant_id') }}"
              data-old-addons="{{ json_encode(old('addon_ids', [])) }}"
              class="abc-panel p-6 space-y-5">
            @csrf
            <input type="hidden" name="checkout_token" value="{{ old('checkout_token', $checkoutToken) }}">

            <div class="space-y-1.5">
                <label for="rs-product-id" class="block text-sm font-medium text-[#F5F0E8]">Product</label>
                <select id="rs-product-id" name="product_id" required class="abc-input">
                    <option value="">— Select a menu item —</option>
                    @foreach($products as $product)
                        <option value="{{ $product->id }}" @selected((string) old('product_id') === (string) $product->id)>
                            {{ $product->name }} — {{ Money::formatPhp($product->price) }} (Stock: {{ $product->stock_quantity }})
                        </option>
                    @endforeach
                </select>
                <p id="rs-stock-hint" class="text-xs text-[#C6A66B]">Select a product to view live stock.</p>
            </div>

            <div id="rs-variant-wrap" class="hidden space-y-1.5">
                <label for="rs-variant-id" class="block text-sm font-medium text-[#F5F0E8]">Size &amp; Temperature</label>
                <select id="rs-variant-id" name="product_variant_id" class="abc-input"></select>
            </div>

            <div id="rs-addons-wrap" class="hidden space-y-2">
                <div class="text-sm font-medium text-[#F5F0E8]">Optional Add-ons</div>
                <div id="rs-addons-list" class="grid grid-cols-1 sm:grid-cols-2 gap-2"></div>
            </div>

            <div class="space-y-1.5">
                <label for="rs-customer-id" class="block text-sm font-medium text-[#F5F0E8]">Customer (Optional)</label>
                <select id="rs-customer-id" name="customer_id" class="abc-input">
                    <option value="">Walk-in customer</option>
                    @foreach($customers as $customer)
                        <option value="{{ $customer->id }}" @selected((string) old('customer_id') === (string) $customer->id)>
                            {{ $customer->full_name }} — {{ $customer->displayIdentifier() }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="space-y-1.5">
                    <label for="rs-quantity" class="block text-sm font-medium text-[#F5F0E8]">Quantity</label>
                    <input id="rs-quantity" name="quantity" type="number" min="1" max="999" step="1" required
                           value="{{ old('quantity', 1) }}" class="abc-input tabular-nums">
                </div>

                <div class="space-y-1.5">
                    <label for="rs-cash-received" class="block text-sm font-medium text-[#F5F0E8]">Cash Received (PHP)</label>
                    <input id="rs-cash-received" name="cash_received" type="number" min="0" step="0.01" required
                           value="{{ old('cash_received') }}" class="abc-input tabular-nums" placeholder="0.00">
                </div>
            </div>

            <div class="abc-surface p-4 flex flex-wrap items-center justify-between gap-4">
                <div>
                    <div class="text-xs text-[#BDB5AA]">Unit Price</div>
                    <div id="rs-unit-preview" class="text-base font-semibold text-[#F5F0E8] tabular-nums">₱0.00</div>
                </div>
                <div class="text-right">
                    <div class="text-xs text-[#BDB5AA]">Total Due</div>
                    <div id="rs-total-preview" class="text-2xl font-semibold text-[#C6A66B] tabular-nums">₱0.00</div>
                </div>
            </div>

            <div class="flex justify-end gap-3 pt-2">
                <a href="{{ route('sales.index') }}" class="abc-btn-secondary">Cancel</a>
                <button type="submit" class="abc-btn-primary">Submit Sale</button>
            </div>
        </form>
    </div>

    @push('scripts')
        @vite(['resources/js/record-sale.js'])
    @endpush
</x-layouts.app>
