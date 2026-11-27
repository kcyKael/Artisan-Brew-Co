@php
    use App\Support\Money;
@endphp

<x-layouts.app title="New Sale / POS">
    <div id="pos-app"
         data-catalog="{{ json_encode($catalogPayload) }}"
         data-checkout-url="{{ route('pos.checkout') }}"
         data-customer-search-url="{{ route('pos.customers.search') }}"
         data-checkout-token="{{ $checkoutToken }}"
         class="space-y-5 pb-20 lg:pb-0">

        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            <div>
                <h1 class="font-display text-3xl sm:text-4xl font-semibold text-[#F4EEE5]">Counter Point of Sale</h1>
                <p class="text-sm text-[#BFB4A5]">Select drinks or pastries, customize size, temperature, and add-ons, and complete cash checkout.</p>
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
                                class="px-3 py-1.5 rounded-lg text-xs font-semibold bg-[#D2B077] text-[#111110]">
                            All Items
                        </button>
                        @foreach($categories as $category)
                            <button type="button" data-pos-category="{{ $category->id }}" aria-pressed="false"
                                    class="px-3 py-1.5 rounded-lg text-xs bg-[#28241F] text-[#BFB4A5] hover:text-[#F4EEE5] border border-white/10">
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
                            <div class="aspect-[4/3] bg-[#28241F] relative overflow-hidden">
                                <x-product-image :product="$product" size="card" />
                            </div>

                            <div class="p-4 flex-1 flex flex-col justify-between gap-3">
                                <div class="space-y-1">
                                    <div class="flex items-start justify-between gap-2">
                                        <h2 class="font-display text-xl font-semibold text-[#F4EEE5] leading-tight">{{ $product->name }}</h2>
                                        <span class="text-sm font-semibold text-[#D2B077] tabular-nums shrink-0">{{ Money::formatPhp($product->price) }}</span>
                                    </div>
                                    <div class="flex items-center justify-between gap-2 text-xs">
                                        <span class="text-[#BFB4A5]">{{ $product->category?->name ?? 'Menu' }}</span>
                                        @if($product->isSoldOut())
                                            <span data-stock-badge class="text-xs font-medium px-2 py-0.5 rounded bg-[rgba(224,122,122,0.16)] text-[#E07A7A]">Sold out</span>
                                        @elseif($product->isLowStock())
                                            <span data-stock-badge class="text-xs font-medium px-2 py-0.5 rounded bg-[rgba(224,168,91,0.16)] text-[#E0A85B] tabular-nums">Low stock ({{ $product->stock_quantity }})</span>
                                        @else
                                            <span data-stock-badge class="text-xs font-medium px-2 py-0.5 rounded bg-[rgba(156,175,148,0.16)] text-[#9CAF94] tabular-nums">Available ({{ $product->stock_quantity }})</span>
                                        @endif
                                    </div>
                                </div>

                                <button type="button"
                                        data-add-product="{{ $product->id }}"
                                        @disabled($product->isSoldOut())
                                        class="abc-btn-primary w-full min-h-[44px] py-2 text-xs">
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

            <aside id="pos-order-panel" aria-label="Order Summary" class="hidden lg:block lg:col-span-5 xl:col-span-4 abc-panel p-5 lg:sticky lg:top-20 space-y-4">
                <div class="md:hidden mx-auto w-10 h-1 rounded-full bg-white/20 mb-1" aria-hidden="true"></div>

                <div class="flex items-center justify-between border-b border-white/10 pb-3">
                    <div class="flex items-center gap-2">
                        <h2 class="font-display text-2xl font-semibold text-[#F4EEE5]">Order Summary</h2>
                        <span id="pos-cart-count" class="px-2 py-0.5 rounded-full text-xs font-semibold bg-[#28241F] text-[#D2B077] tabular-nums">0</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <button type="button" id="pos-mobile-cart-close" class="lg:hidden abc-btn-secondary py-1.5 px-3 text-xs min-h-[36px]">Done</button>
                    </div>
                </div>

                <div id="pos-cart-loading" class="hidden space-y-3 py-2" aria-busy="true" aria-label="Processing checkout">
                    <div class="h-14 rounded-lg bg-[#28241F] animate-pulse"></div>
                    <div class="h-14 rounded-lg bg-[#28241F] animate-pulse"></div>
                    <div class="h-10 rounded-lg bg-[#28241F] animate-pulse"></div>
                </div>

                <div id="pos-cart-empty" class="py-8 text-center space-y-3">
                    <p class="text-sm text-[#BFB4A5]">Your order summary is empty. Select items from the cafe menu to start.</p>
                    <button type="button" id="pos-browse-menu-btn" class="abc-btn-secondary text-xs py-2 px-4">Browse Menu</button>
                </div>

                <div id="pos-cart-lines" class="space-y-2.5 max-h-[34vh] overflow-y-auto pr-1"></div>

                <div class="space-y-2 pt-3 border-t border-white/10">
                    <label for="pos-customer-search" class="block text-xs font-medium text-[#BFB4A5]">Customer (Optional — defaults to Walk-in)</label>
                    <input id="pos-customer-search" type="search" class="abc-input text-xs py-2" placeholder="Filter customers by name, email, or phone...">
                    <select id="pos-customer-id" aria-label="Select customer" class="abc-input text-xs py-2">
                        <option value="">Walk-in customer</option>
                        @foreach($customers as $customer)
                            <option value="{{ $customer->id }}">{{ $customer->full_name }} — {{ $customer->displayIdentifier() }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="space-y-2 pt-3 border-t border-white/10">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-semibold uppercase tracking-[0.14em] text-[#BFB4A5]">Payment Options</span>
                        <span class="text-[11px] text-[#BFB4A5]">Cash Counter</span>
                    </div>

                    <div id="pos-payment-radiogroup" role="radiogroup" aria-label="Cash payment options" class="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
                        <button type="button" role="radio" aria-checked="true" data-pay-option="exact" tabindex="0" class="abc-pay-card">
                            <span class="w-8 h-8 rounded-lg bg-[#1D1B18] border border-white/10 flex items-center justify-center text-[#D2B077] shrink-0" aria-hidden="true">
                                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75"><rect x="3" y="6" width="18" height="12" rx="2"/><circle cx="12" cy="12" r="2.5"/></svg>
                            </span>
                            <span class="min-w-0 pr-4">
                                <span class="block text-[15px] font-medium text-[#F4EEE5] leading-snug">Exact Cash</span>
                                <span class="block text-xs text-[#BFB4A5]">Match order total</span>
                            </span>
                            <span data-pay-check class="absolute top-2.5 right-2.5 w-4 h-4 rounded-full bg-[#D2B077] text-[#111110] flex items-center justify-center">
                                <svg class="w-3 h-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><path d="M5 13l4 4L19 7" stroke-linecap="round" stroke-linejoin="round"/></svg>
                            </span>
                        </button>

                        <button type="button" role="radio" aria-checked="false" data-pay-option="200" tabindex="-1" class="abc-pay-card">
                            <span class="w-8 h-8 rounded-lg bg-[#1D1B18] border border-white/10 flex items-center justify-center text-[#D2B077] shrink-0" aria-hidden="true">
                                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75"><path d="M4 7h16v10H4z"/><path d="M8 12h8" stroke-linecap="round"/></svg>
                            </span>
                            <span class="min-w-0 pr-4">
                                <span class="block text-[15px] font-medium text-[#F4EEE5] leading-snug tabular-nums">₱200 Bill</span>
                                <span class="block text-xs text-[#BFB4A5]">Quick cash tender</span>
                            </span>
                            <span data-pay-check class="hidden absolute top-2.5 right-2.5 w-4 h-4 rounded-full bg-[#D2B077] text-[#111110] items-center justify-center">
                                <svg class="w-3 h-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><path d="M5 13l4 4L19 7" stroke-linecap="round" stroke-linejoin="round"/></svg>
                            </span>
                        </button>

                        <button type="button" role="radio" aria-checked="false" data-pay-option="500" tabindex="-1" class="abc-pay-card">
                            <span class="w-8 h-8 rounded-lg bg-[#1D1B18] border border-white/10 flex items-center justify-center text-[#D2B077] shrink-0" aria-hidden="true">
                                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75"><path d="M4 7h16v10H4z"/><path d="M8 12h8" stroke-linecap="round"/></svg>
                            </span>
                            <span class="min-w-0 pr-4">
                                <span class="block text-[15px] font-medium text-[#F4EEE5] leading-snug tabular-nums">₱500 Bill</span>
                                <span class="block text-xs text-[#BFB4A5]">Quick cash tender</span>
                            </span>
                            <span data-pay-check class="hidden absolute top-2.5 right-2.5 w-4 h-4 rounded-full bg-[#D2B077] text-[#111110] items-center justify-center">
                                <svg class="w-3 h-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><path d="M5 13l4 4L19 7" stroke-linecap="round" stroke-linejoin="round"/></svg>
                            </span>
                        </button>

                        <button type="button" role="radio" aria-checked="false" data-pay-option="custom" tabindex="-1" class="abc-pay-card">
                            <span class="w-8 h-8 rounded-lg bg-[#1D1B18] border border-white/10 flex items-center justify-center text-[#D2B077] shrink-0" aria-hidden="true">
                                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75"><path d="M12 4v16M7 8h8.5a3 3 0 010 6H7" stroke-linecap="round" stroke-linejoin="round"/></svg>
                            </span>
                            <span class="min-w-0 pr-4">
                                <span class="block text-[15px] font-medium text-[#F4EEE5] leading-snug">Custom Cash</span>
                                <span class="block text-xs text-[#BFB4A5]">Enter amount below</span>
                            </span>
                            <span data-pay-check class="hidden absolute top-2.5 right-2.5 w-4 h-4 rounded-full bg-[#D2B077] text-[#111110] items-center justify-center">
                                <svg class="w-3 h-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><path d="M5 13l4 4L19 7" stroke-linecap="round" stroke-linejoin="round"/></svg>
                            </span>
                        </button>
                    </div>

                    <div class="pt-1 space-y-1">
                        <label for="pos-cash-received" class="block text-xs font-medium text-[#BFB4A5]">Cash Received (PHP)</label>
                        <input id="pos-cash-received" type="number" step="0.01" min="0" inputmode="decimal" class="abc-input tabular-nums" placeholder="0.00">
                    </div>
                </div>

                <div class="space-y-2 pt-3 border-t border-white/10">
                    <div class="flex justify-between text-sm text-[#BFB4A5]">
                        <span class="flex-1 text-right pr-4">Subtotal</span>
                        <span id="pos-subtotal" class="tabular-nums font-medium text-[#F4EEE5]">₱0.00</span>
                    </div>
                    <div class="flex justify-between text-sm text-[#BFB4A5]">
                        <span class="flex-1 text-right pr-4">Change</span>
                        <span id="pos-change" class="tabular-nums font-semibold text-[#BFB4A5]">₱0.00</span>
                    </div>
                    <div class="flex justify-between items-baseline pt-1 border-t border-white/10">
                        <span class="flex-1 text-right pr-4 text-sm font-semibold uppercase tracking-wider text-[#BFB4A5]">Total</span>
                        <span id="pos-total" class="text-[22px] font-semibold text-[#D2B077] tabular-nums">₱0.00</span>
                    </div>
                </div>

                <div id="pos-error-box" class="hidden rounded-lg border border-[#E07A7A]/40 bg-[#E07A7A]/12 px-3 py-2.5 text-xs text-[#F5C6C6]" role="alert">
                    <span id="pos-error-msg"></span>
                </div>

                <div class="space-y-2.5 pt-1">
                    <div class="grid grid-cols-2 gap-2">
                        <button type="button" id="pos-clear-cart-btn" class="hidden abc-btn-secondary py-2 text-xs min-h-[40px]">Clear Order</button>
                        <a href="{{ route('sales.record') }}" class="abc-btn-secondary py-2 text-xs min-h-[40px] col-span-1">Single-Item Mode</a>
                    </div>
                    <button type="button" id="pos-checkout-btn" disabled class="abc-btn-primary w-full min-h-[46px] rounded-[10px] text-sm">
                        <span id="pos-checkout-label">Complete Sale · ₱0.00</span>
                    </button>
                </div>
            </aside>
        </div>

        <div id="pos-mobile-sheet-backdrop" class="hidden fixed inset-0 bg-black/70 z-40 lg:hidden"></div>

        <div class="lg:hidden fixed bottom-4 inset-x-4 z-30">
            <button type="button" id="pos-mobile-cart-toggle" class="abc-btn-primary w-full min-h-[48px] py-3 px-4 justify-between rounded-[12px] shadow-lg">
                <span>Order Summary (<span id="pos-mobile-cart-count" class="tabular-nums">0</span>)</span>
                <span id="pos-mobile-cart-total" class="tabular-nums">₱0.00</span>
            </button>
        </div>

        <dialog id="pos-config-dialog" class="m-auto rounded-2xl bg-[#1D1B18] text-[#F4EEE5] border border-white/15 p-0 max-w-lg w-[94vw] backdrop:bg-black/75">
            <div class="p-5 space-y-4">
                <div class="flex items-start justify-between gap-3 border-b border-white/10 pb-3">
                    <div>
                        <h2 id="pos-config-title" class="font-display text-2xl font-semibold">Customize Item</h2>
                        <div class="flex items-center gap-3 text-xs text-[#BFB4A5] mt-0.5">
                            <span id="pos-config-base" class="tabular-nums"></span>
                            <span id="pos-config-stock" class="text-[#D2B077]"></span>
                        </div>
                    </div>
                    <button type="button" id="pos-config-cancel" class="abc-btn-secondary py-1 px-2.5 text-xs min-h-[36px]">Close</button>
                </div>

                <div id="pos-config-variants-wrap" class="space-y-2">
                    <div class="text-xs font-semibold uppercase tracking-wider text-[#BFB4A5]">Size &amp; Temperature</div>
                    <div id="pos-config-variants" class="grid grid-cols-1 sm:grid-cols-2 gap-2"></div>
                </div>

                <div id="pos-config-addons-wrap" class="space-y-2">
                    <div class="text-xs font-semibold uppercase tracking-wider text-[#BFB4A5]">Optional Add-ons</div>
                    <div id="pos-config-addons" class="grid grid-cols-1 sm:grid-cols-2 gap-2"></div>
                </div>

                <div class="flex items-center justify-between gap-4 pt-2 border-t border-white/10">
                    <div class="flex items-center gap-2">
                        <label for="pos-config-qty" class="text-xs font-medium text-[#BFB4A5]">Qty</label>
                        <input id="pos-config-qty" type="number" min="1" max="999" value="1" class="abc-input w-20 py-1.5 text-sm tabular-nums">
                    </div>
                    <div id="pos-config-unit-preview" class="text-sm font-semibold text-[#D2B077] tabular-nums">₱0.00</div>
                </div>

                <button type="button" id="pos-config-add-btn" class="abc-btn-primary w-full min-h-[46px] rounded-[10px]">
                    Add to Order Summary
                </button>
            </div>
        </dialog>

        <dialog id="pos-receipt-dialog" class="m-auto rounded-2xl bg-[#1D1B18] text-[#F4EEE5] border border-white/15 p-0 max-w-3xl w-[95vw] backdrop:bg-black/80">
            <div class="p-6 sm:p-8 grid grid-cols-1 md:grid-cols-12 gap-6 items-start">
                <div class="md:col-span-6 text-center space-y-4">
                    <div class="mx-auto w-14 h-14 rounded-full bg-[var(--color-success-tint-dark)] border border-[#22A85B]/40 flex items-center justify-center text-[#22A85B]">
                        <svg class="w-7 h-7" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
                            <path d="M5 13l4 4L19 7" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </div>
                    <div class="space-y-1">
                        <h2 class="font-display text-3xl font-semibold text-[#F4EEE5]">Payment Successful!</h2>
                        <p id="pos-success-order-num" class="text-xs text-[#BFB4A5] tabular-nums"></p>
                    </div>
                    <div class="abc-surface p-4 space-y-1">
                        <div class="text-[11px] font-semibold uppercase tracking-[0.16em] text-[#BFB4A5]">Total Amount Paid</div>
                        <div id="pos-success-total" class="text-3xl font-semibold text-[#22A85B] tabular-nums">₱0.00</div>
                    </div>
                    <div class="abc-surface divide-y divide-white/10 text-sm text-left">
                        <div class="px-4 py-2.5 flex justify-between">
                            <span class="text-[#BFB4A5]">Cash Received</span>
                            <span id="pos-success-cash" class="font-medium text-[#F4EEE5] tabular-nums">₱0.00</span>
                        </div>
                        <div class="px-4 py-2.5 flex justify-between">
                            <span class="text-[#BFB4A5]">Change Due</span>
                            <span id="pos-success-change" class="font-semibold text-[#22A85B] tabular-nums">₱0.00</span>
                        </div>
                    </div>
                    <a id="pos-success-detail-link" href="#" class="abc-surface px-4 py-3 flex items-center justify-between gap-2 text-sm hover:border-[#D2B077]/45 transition-colors">
                        <span class="font-medium text-[#F4EEE5]">View Full Order Details</span>
                        <svg class="w-4 h-4 text-[#BFB4A5]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 6l6 6-6 6" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    </a>
                    <div class="flex flex-col sm:flex-row gap-2.5 pt-1">
                        <button type="button" id="pos-receipt-new-order" class="abc-btn-secondary flex-1 min-h-[44px]">New Order</button>
                        <a id="pos-receipt-print-link" href="#" class="abc-btn-success flex-1 min-h-[44px]">Print Receipt</a>
                    </div>
                </div>

                <div class="md:col-span-6 flex justify-center" aria-live="polite">
                    <div id="pos-live-receipt-wrap" class="w-full flex justify-center"></div>
                </div>
            </div>
        </dialog>
    </div>

    @push('scripts')
        @vite(['resources/js/pos.js'])
    @endpush
</x-layouts.app>
