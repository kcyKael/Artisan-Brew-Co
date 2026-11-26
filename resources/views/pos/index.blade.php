@php
use App\Support\Money;
@endphp

<x-layouts.app title="POS Terminal">
    <div id="pos-app"
         data-catalog="{{ json_encode($catalogPayload) }}"
         data-checkout-url="{{ route('pos.checkout') }}"
         data-customer-search-url="{{ route('pos.customers.search') }}"
         data-checkout-token="{{ $checkoutToken }}"
         class="space-y-5">

        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            <div>
                <h1 class="font-display text-3xl sm:text-4xl font-semibold text-[#F5F0E8]">Counter Point of Sale</h1>
                <p class="text-sm text-[#BDB5AA]">Select drinks or pastries, customize size, temperature, and add-ons, and complete cash checkout.</p>
            </div>
            <a href="{{ route('sales.record') }}" class="abc-btn-secondary self-start sm:self-auto">
                Simple Record Sale
            </a>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
            <section class="lg:col-span-7 xl:col-span-8 space-y-4">
                <div class="abc-panel p-4 space-y-3">
                    <div class="flex flex-col sm:flex-row gap-3">
                        <div class="flex-1">
                            <label for="pos-search" class="sr-only">Search menu items</label>
                            <input id="pos-search" type="search" class="abc-input" placeholder="Search drinks, pastries, or SKU...">
                        </div>
                    </div>

                    <div class="flex flex-wrap gap-2" role="group" aria-label="Menu categories">
                        <button type="button" data-pos-category="all" aria-pressed="true"
                                class="px-3 py-1.5 rounded-lg text-xs font-semibold bg-[#C6A66B] text-[#111110]">
                            All Items
                        </button>
                        @foreach($categories as $category)
                            <button type="button" data-pos-category="{{ $category->id }}" aria-pressed="false"
                                    class="px-3 py-1.5 rounded-lg text-xs bg-[#24211D] text-[#BDB5AA] hover:text-[#F5F0E8] border border-white/10">
                                {{ $category->name }}
                            </button>
                        @endforeach
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-4">
                    @foreach($products as $product)
                        <article data-pos-product
                                 data-product-id="{{ $product->id }}"
                                 data-category-id="{{ $product->category_id }}"
                                 data-name="{{ $product->name }}"
                                 data-sku="{{ $product->sku }}"
                                 class="abc-panel overflow-hidden flex flex-col">
                            <div class="aspect-[4/3] bg-[#24211D] relative overflow-hidden">
                                @if($product->imageUrl())
                                    <img src="{{ $product->imageUrl() }}" alt="{{ $product->name }}" class="w-full h-full object-cover">
                                @else
                                    <div class="w-full h-full flex items-center justify-center text-xs text-[#BDB5AA]">No Image</div>
                                @endif
                            </div>

                            <div class="p-4 flex-1 flex flex-col justify-between gap-3">
                                <div class="space-y-1">
                                    <div class="flex items-start justify-between gap-2">
                                        <h2 class="font-display text-xl font-semibold text-[#F5F0E8] leading-tight">{{ $product->name }}</h2>
                                        <span class="text-sm font-semibold text-[#C6A66B] tabular-nums shrink-0">{{ Money::formatPhp($product->price) }}</span>
                                    </div>
                                    <div class="flex items-center justify-between gap-2 text-xs">
                                        <span class="text-[#BDB5AA]">{{ $product->category?->name ?? 'Menu' }}</span>
                                        @if($product->isSoldOut())
                                            <span data-stock-badge class="text-xs font-medium px-2 py-0.5 rounded bg-[rgba(230,119,125,0.16)] text-[#E6777D]">Sold out</span>
                                        @elseif($product->isLowStock())
                                            <span data-stock-badge class="text-xs font-medium px-2 py-0.5 rounded bg-[rgba(224,164,88,0.16)] text-[#E0A458] tabular-nums">Low: {{ $product->stock_quantity }}</span>
                                        @else
                                            <span data-stock-badge class="text-xs font-medium px-2 py-0.5 rounded bg-[rgba(88,185,135,0.14)] text-[#58B987] tabular-nums">{{ $product->stock_quantity }} left</span>
                                        @endif
                                    </div>
                                </div>

                                <button type="button"
                                        data-add-product="{{ $product->id }}"
                                        @disabled($product->isSoldOut())
                                        class="abc-btn-primary w-full py-2 text-xs">
                                    {{ $product->activeVariants->isNotEmpty() || $product->activeAddons->isNotEmpty() ? 'Customize & Add' : 'Add to Order' }}
                                </button>
                            </div>
                        </article>
                    @endforeach
                </div>

                <div id="pos-empty-catalog" class="hidden">
                    <x-empty-state title="No matching menu items" description="Try clearing the search query or selecting a different category." />
                </div>
            </section>

            <aside id="pos-order-panel" class="hidden lg:block lg:col-span-5 xl:col-span-4 abc-panel p-5 lg:sticky lg:top-6 space-y-4">
                <div class="flex items-center justify-between border-b border-white/10 pb-3">
                    <div class="flex items-center gap-2">
                        <h2 class="font-display text-2xl font-semibold text-[#F5F0E8]">Current Order</h2>
                        <span id="pos-cart-count" class="px-2 py-0.5 rounded-full text-xs font-semibold bg-[#24211D] text-[#C6A66B] tabular-nums">0</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <button type="button" id="pos-clear-cart-btn" class="hidden text-xs text-[#BDB5AA] hover:text-[#E6777D]">Clear</button>
                        <button type="button" id="pos-mobile-cart-close" class="lg:hidden abc-btn-secondary py-1 px-2.5 text-xs">Done</button>
                    </div>
                </div>

                <div id="pos-error-box" class="hidden rounded-lg border border-[#E6777D]/40 bg-[#E6777D]/10 px-3 py-2.5 text-xs text-[#F4B4B8]" role="alert">
                    <span id="pos-error-msg"></span>
                </div>

                <div id="pos-cart-empty" class="py-8 text-center text-sm text-[#BDB5AA]">
                    Cart is empty. Tap any available item to begin an order.
                </div>

                <div id="pos-cart-lines" class="space-y-2.5 max-h-[38vh] overflow-y-auto pr-1"></div>

                <div class="space-y-2 pt-2 border-t border-white/10">
                    <label for="pos-customer-search" class="block text-xs font-medium text-[#BDB5AA]">Customer (Optional — defaults to Walk-in)</label>
                    <input id="pos-customer-search" type="search" class="abc-input text-xs py-2" placeholder="Filter customers by name, email, or phone...">
                    <select id="pos-customer-id" aria-label="Select customer" class="abc-input text-xs py-2">
                        <option value="">Walk-in customer</option>
                        @foreach($customers as $customer)
                            <option value="{{ $customer->id }}">{{ $customer->full_name }} — {{ $customer->displayIdentifier() }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="space-y-3 pt-2 border-t border-white/10">
                    <div class="flex justify-between text-sm text-[#BDB5AA]">
                        <span>Subtotal</span>
                        <span id="pos-subtotal" class="tabular-nums">₱0.00</span>
                    </div>
                    <div class="flex justify-between items-baseline">
                        <span class="text-base font-semibold text-[#F5F0E8]">Order Total</span>
                        <span id="pos-total" class="text-2xl font-semibold text-[#C6A66B] tabular-nums">₱0.00</span>
                    </div>

                    <div class="space-y-1.5">
                        <label for="pos-cash-received" class="block text-xs font-medium text-[#BDB5AA]">Cash Received (PHP)</label>
                        <input id="pos-cash-received" type="number" step="0.01" min="0" inputmode="decimal" class="abc-input tabular-nums" placeholder="0.00">
                        <div class="grid grid-cols-4 gap-1.5 pt-1">
                            <button type="button" data-cash-quick="exact" class="abc-btn-secondary py-1.5 px-2 text-xs">Exact</button>
                            <button type="button" data-cash-quick="200" class="abc-btn-secondary py-1.5 px-2 text-xs tabular-nums">₱200</button>
                            <button type="button" data-cash-quick="500" class="abc-btn-secondary py-1.5 px-2 text-xs tabular-nums">₱500</button>
                            <button type="button" data-cash-quick="1000" class="abc-btn-secondary py-1.5 px-2 text-xs tabular-nums">₱1,000</button>
                        </div>
                    </div>

                    <div class="flex justify-between items-center pt-1">
                        <span class="text-sm text-[#BDB5AA]">Change</span>
                        <span id="pos-change" class="text-base font-semibold tabular-nums text-[#BDB5AA]">₱0.00</span>
                    </div>

                    <button type="button" id="pos-checkout-btn" disabled class="abc-btn-primary w-full py-3 text-sm">
                        Complete Sale
                    </button>
                </div>
            </aside>
        </div>

        <div class="lg:hidden fixed bottom-4 inset-x-4 z-30">
            <button type="button" id="pos-mobile-cart-toggle" class="abc-btn-primary w-full py-3 px-4 justify-between shadow-lg">
                <span>View Order (<span id="pos-mobile-cart-count" class="tabular-nums">0</span>)</span>
                <span id="pos-mobile-cart-total" class="tabular-nums">₱0.00</span>
            </button>
        </div>

        <dialog id="pos-config-dialog" class="m-auto rounded-xl bg-[#1B1A18] text-[#F5F0E8] border border-white/15 p-0 max-w-lg w-[94vw] backdrop:bg-black/75">
            <div class="p-5 space-y-4">
                <div class="flex items-start justify-between gap-3 border-b border-white/10 pb-3">
                    <div>
                        <h2 id="pos-config-title" class="font-display text-2xl font-semibold">Customize Item</h2>
                        <div class="flex items-center gap-3 text-xs text-[#BDB5AA] mt-0.5">
                            <span id="pos-config-base" class="tabular-nums"></span>
                            <span id="pos-config-stock" class="text-[#C6A66B]"></span>
                        </div>
                    </div>
                    <button type="button" id="pos-config-cancel" class="abc-btn-secondary py-1 px-2.5 text-xs">Close</button>
                </div>

                <div id="pos-config-variants-wrap" class="space-y-2">
                    <div class="text-xs font-semibold uppercase tracking-wider text-[#BDB5AA]">Size &amp; Temperature</div>
                    <div id="pos-config-variants" class="grid grid-cols-1 sm:grid-cols-2 gap-2"></div>
                </div>

                <div id="pos-config-addons-wrap" class="space-y-2">
                    <div class="text-xs font-semibold uppercase tracking-wider text-[#BDB5AA]">Optional Add-ons</div>
                    <div id="pos-config-addons" class="grid grid-cols-1 sm:grid-cols-2 gap-2"></div>
                </div>

                <div class="flex items-center justify-between gap-4 pt-2 border-t border-white/10">
                    <div class="flex items-center gap-2">
                        <label for="pos-config-qty" class="text-xs font-medium text-[#BDB5AA]">Qty</label>
                        <input id="pos-config-qty" type="number" min="1" max="999" value="1" class="abc-input w-20 py-1.5 text-sm tabular-nums">
                    </div>
                    <div id="pos-config-unit-preview" class="text-sm font-semibold text-[#C6A66B] tabular-nums">₱0.00</div>
                </div>

                <button type="button" id="pos-config-add-btn" class="abc-btn-primary w-full py-2.5">
                    Add to Current Order
                </button>
            </div>
        </dialog>

        <dialog id="pos-receipt-dialog" class="m-auto rounded-xl bg-[#1B1A18] text-[#F5F0E8] border border-white/15 p-0 max-w-md w-[94vw] backdrop:bg-black/75">
            <div class="p-6 space-y-4">
                <div class="space-y-1">
                    <h2 class="font-display text-2xl font-semibold text-[#58B987]">Sale Completed</h2>
                    <p class="text-xs text-[#BDB5AA]">Inventory has been updated and the receipt is ready.</p>
                </div>
                <div id="pos-receipt-summary" class="abc-surface p-4"></div>
                <div class="flex flex-col sm:flex-row gap-2">
                    <a id="pos-receipt-print-link" href="#" class="abc-btn-primary flex-1">Open Printable Receipt</a>
                    <button type="button" id="pos-receipt-new-order" class="abc-btn-secondary flex-1">New Order</button>
                </div>
            </div>
        </dialog>
    </div>

    @push('scripts')
        @vite(['resources/js/pos.js'])
    @endpush
</x-layouts.app>
