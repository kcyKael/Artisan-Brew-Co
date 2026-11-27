@php
    use App\Support\Money;
@endphp

<x-layouts.app title="New Sale / POS">
    <div id="pos-app"
         data-catalog="{{ json_encode($catalogPayload) }}"
         data-checkout-url="{{ route('pos.checkout') }}"
         data-customer-search-url="{{ route('pos.customers.search') }}"
         data-gift-card-lookup-url="{{ route('pos.gift-cards.lookup') }}"
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

            <aside id="pos-order-panel" aria-label="Order Summary" class="hidden lg:flex lg:flex-col lg:col-span-5 xl:col-span-4 abc-panel p-5 lg:sticky lg:top-20 lg:max-h-[calc(100vh-6rem)]">
                <div class="shrink-0 space-y-3 pb-3 border-b border-white/10">
                    <div class="md:hidden mx-auto w-10 h-1 rounded-full bg-white/20 mb-1" aria-hidden="true"></div>

                    <div class="flex items-center justify-between gap-2">
                        <div class="flex items-center gap-2">
                            <h2 class="font-display text-2xl font-semibold text-[#F4EEE5]">Order Summary</h2>
                            <span id="pos-cart-count" class="px-2 py-0.5 rounded-full text-xs font-semibold bg-[#28241F] text-[#D2B077] tabular-nums">0</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <button type="button" id="pos-clear-cart-btn" class="hidden text-xs font-medium text-[#BFB4A5] hover:text-[#E07A7A] px-2 py-1 rounded-md border border-white/10 bg-[#28241F]">Clear</button>
                            <button type="button" id="pos-mobile-cart-close" class="lg:hidden abc-btn-secondary py-1.5 px-3 text-xs min-h-[36px]">Done</button>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-1.5 p-1 rounded-lg bg-[#161412] border border-white/10" role="group" aria-label="Order type">
                        <button type="button" data-order-type="dine_in" aria-pressed="true"
                                class="py-1.5 px-3 rounded-md text-xs font-semibold bg-[#D2B077] text-[#111110] transition-colors">
                            Dine-in
                        </button>
                        <button type="button" data-order-type="take_out" aria-pressed="false"
                                class="py-1.5 px-3 rounded-md text-xs font-medium text-[#BFB4A5] hover:text-[#F4EEE5] transition-colors">
                            Take-out
                        </button>
                    </div>
                </div>

                <div class="lg:flex-1 lg:min-h-0 lg:overflow-y-auto space-y-3 py-3 pr-1">
                    <div id="pos-cart-loading" class="hidden space-y-3 py-2" aria-busy="true" aria-label="Processing checkout">
                        <div class="h-14 rounded-lg bg-[#28241F] animate-pulse"></div>
                        <div class="h-14 rounded-lg bg-[#28241F] animate-pulse"></div>
                        <div class="h-10 rounded-lg bg-[#28241F] animate-pulse"></div>
                    </div>

                    <div id="pos-cart-empty" class="py-6 text-center space-y-3">
                        <p class="text-sm text-[#BFB4A5]">Your order summary is empty. Select items from the cafe menu to start.</p>
                        <button type="button" id="pos-browse-menu-btn" class="abc-btn-secondary text-xs py-2 px-4">Browse Menu</button>
                    </div>

                    <div id="pos-cart-lines" class="space-y-2.5"></div>

                    <div class="space-y-2 pt-3 border-t border-white/10">
                        <label for="pos-customer-search" class="block text-xs font-medium text-[#BFB4A5]">Customer &amp; Order Details</label>
                        <input id="pos-customer-search" type="search" class="abc-input text-xs py-1.5" placeholder="Filter customers by name, email, or phone...">
                        <select id="pos-customer-id" aria-label="Select customer" class="abc-input text-xs py-1.5">
                            <option value="">Walk-in customer</option>
                            @foreach($customers as $customer)
                                <option value="{{ $customer->id }}">{{ $customer->full_name }} — {{ $customer->displayIdentifier() }}</option>
                            @endforeach
                        </select>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                            <input id="pos-cup-name" type="text" maxlength="60" aria-label="Cup name" class="abc-input text-xs py-1.5" placeholder="Name on cup (walk-in)">
                            <input id="pos-order-note" type="text" maxlength="200" aria-label="Order note" class="abc-input text-xs py-1.5" placeholder="Order note (optional)">
                        </div>
                        <div class="space-y-1.5 pt-1">
                            <input id="pos-receipt-email" type="email" maxlength="191" aria-label="Digital receipt email" class="abc-input text-xs py-1.5" placeholder="Email receipt to customer (optional)">
                            <label class="inline-flex items-center gap-2 text-[11px] text-[#BFB4A5] cursor-pointer">
                                <input id="pos-marketing-opt-in" type="checkbox" class="accent-[#D2B077]">
                                <span>Customer wants seasonal promos (double opt-in)</span>
                            </label>
                        </div>
                    </div>

                    <div class="space-y-2 pt-3 border-t border-white/10">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-medium text-[#BFB4A5]">Discount / Gift Card</span>
                        </div>
                        <div class="grid grid-cols-4 gap-1 p-1 rounded-lg bg-[#161412] border border-white/10" role="group" aria-label="Discount type">
                            <button type="button" data-discount-type="none" aria-pressed="true"
                                    class="py-1 px-2 rounded-md text-xs font-semibold bg-[#D2B077] text-[#111110] transition-colors">
                                None
                            </button>
                            <button type="button" data-discount-type="percent" aria-pressed="false"
                                    class="py-1 px-2 rounded-md text-xs font-medium text-[#BFB4A5] hover:text-[#F4EEE5] transition-colors">
                                %
                            </button>
                            <button type="button" data-discount-type="fixed" aria-pressed="false"
                                    class="py-1 px-2 rounded-md text-xs font-medium text-[#BFB4A5] hover:text-[#F4EEE5] transition-colors">
                                ₱
                            </button>
                            <button type="button" data-discount-type="giftcard" aria-pressed="false"
                                    class="py-1 px-2 rounded-md text-xs font-medium text-[#BFB4A5] hover:text-[#F4EEE5] transition-colors">
                                Gift Card
                            </button>
                        </div>

                        <div id="pos-discount-value-wrap" class="hidden">
                            <input id="pos-discount-value" type="number" step="0.01" min="0" inputmode="decimal" aria-label="Discount value" class="abc-input text-xs py-1.5 tabular-nums" placeholder="Discount value">
                        </div>

                        <div id="pos-gift-card-wrap" class="hidden space-y-1.5">
                            <div class="flex gap-2">
                                <input id="pos-gift-card-code" type="text" maxlength="60" aria-label="Gift card code" class="abc-input text-xs py-1.5 uppercase" placeholder="Gift card code">
                                <button type="button" id="pos-gift-card-lookup-btn" class="abc-btn-secondary py-1.5 px-3 text-xs shrink-0">Lookup</button>
                            </div>
                            <p id="pos-gift-card-feedback" class="hidden text-xs"></p>
                        </div>
                    </div>
                </div>

                <div class="shrink-0 pt-3 border-t border-white/10 space-y-2.5 bg-[#1D1B18]">
                    <div class="grid grid-cols-3 gap-1 p-1 rounded-lg bg-[#161412] border border-white/10" role="group" aria-label="Payment method">
                        <button type="button" data-payment-method="cash" aria-pressed="true"
                                class="py-1.5 px-2 rounded-md text-xs font-semibold bg-[#D2B077] text-[#111110] transition-colors">
                            Cash
                        </button>
                        <button type="button" data-payment-method="card" aria-pressed="false"
                                class="py-1.5 px-2 rounded-md text-xs font-medium text-[#BFB4A5] hover:text-[#F4EEE5] transition-colors">
                            Card
                        </button>
                        <button type="button" data-payment-method="gcash" aria-pressed="false"
                                class="py-1.5 px-2 rounded-md text-xs font-medium text-[#BFB4A5] hover:text-[#F4EEE5] transition-colors">
                            GCash
                        </button>
                    </div>

                    <div id="pos-cash-section" class="space-y-2">
                        <div id="pos-payment-radiogroup" role="radiogroup" aria-label="Cash payment options" class="grid grid-cols-4 gap-1.5">
                            <button type="button" role="radio" aria-checked="true" data-pay-option="exact" tabindex="0"
                                    class="py-1.5 px-2 rounded-lg text-xs font-semibold border border-[#D2B077] bg-[#D2B077]/15 text-[#F4EEE5] transition-colors">
                                Exact
                            </button>
                            <button type="button" role="radio" aria-checked="false" data-pay-option="200" tabindex="-1"
                                    class="py-1.5 px-2 rounded-lg text-xs font-medium border border-white/10 bg-[#28241F] text-[#BFB4A5] hover:text-[#F4EEE5] tabular-nums transition-colors">
                                ₱200
                            </button>
                            <button type="button" role="radio" aria-checked="false" data-pay-option="500" tabindex="-1"
                                    class="py-1.5 px-2 rounded-lg text-xs font-medium border border-white/10 bg-[#28241F] text-[#BFB4A5] hover:text-[#F4EEE5] tabular-nums transition-colors">
                                ₱500
                            </button>
                            <button type="button" role="radio" aria-checked="false" data-pay-option="custom" tabindex="-1"
                                    class="py-1.5 px-2 rounded-lg text-xs font-medium border border-white/10 bg-[#28241F] text-[#BFB4A5] hover:text-[#F4EEE5] transition-colors">
                                Custom
                            </button>
                        </div>

                        <div class="flex items-center gap-2">
                            <label for="pos-cash-received" class="text-xs font-medium text-[#BFB4A5] shrink-0">Cash (₱)</label>
                            <input id="pos-cash-received" type="number" step="0.01" min="0" inputmode="decimal" class="abc-input text-xs py-1.5 tabular-nums" placeholder="0.00">
                            <div class="text-right shrink-0 min-w-[78px]">
                                <div class="text-[10px] uppercase tracking-wider text-[#BFB4A5]">Change</div>
                                <div id="pos-change" class="text-xs tabular-nums font-semibold text-[#BFB4A5]">₱0.00</div>
                            </div>
                        </div>
                    </div>

                    <div id="pos-reference-section" class="hidden">
                        <input id="pos-payment-reference" type="text" maxlength="60" aria-label="Payment reference" class="abc-input text-xs py-1.5" placeholder="Approval / Ref # (optional)">
                    </div>

                    <div class="space-y-1 pt-2 border-t border-white/10">
                        <div class="flex justify-between text-xs text-[#BFB4A5]">
                            <span>Subtotal</span>
                            <span id="pos-subtotal" class="tabular-nums font-medium text-[#F4EEE5]">₱0.00</span>
                        </div>
                        <div id="pos-discount-row" class="hidden flex justify-between text-xs text-[#9CAF94]">
                            <span>Discount</span>
                            <span id="pos-discount-amount" class="tabular-nums font-medium">-₱0.00</span>
                        </div>
                        <div class="flex justify-between items-baseline pt-1">
                            <span class="text-xs font-semibold uppercase tracking-wider text-[#BFB4A5]">Total</span>
                            <span id="pos-total" class="text-[22px] font-semibold text-[#D2B077] tabular-nums">₱0.00</span>
                        </div>
                    </div>

                    <div id="pos-error-box" class="hidden rounded-lg border border-[#E07A7A]/40 bg-[#E07A7A]/12 px-3 py-2 text-xs text-[#F5C6C6]" role="alert">
                        <span id="pos-error-msg"></span>
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

                <div class="space-y-2 pt-2 border-t border-white/10">
                    <div class="text-xs font-semibold uppercase tracking-wider text-[#BFB4A5]">Item Note (Optional)</div>
                    <div class="flex flex-wrap gap-1.5">
                        <button type="button" data-config-quick-note="Less Ice" aria-pressed="false"
                                class="px-2.5 py-1 rounded-md text-xs bg-[#28241F] text-[#BFB4A5] border border-white/10 hover:text-[#F4EEE5] transition-colors">
                            Less Ice
                        </button>
                        <button type="button" data-config-quick-note="No Ice" aria-pressed="false"
                                class="px-2.5 py-1 rounded-md text-xs bg-[#28241F] text-[#BFB4A5] border border-white/10 hover:text-[#F4EEE5] transition-colors">
                            No Ice
                        </button>
                        <button type="button" data-config-quick-note="Less Sugar" aria-pressed="false"
                                class="px-2.5 py-1 rounded-md text-xs bg-[#28241F] text-[#BFB4A5] border border-white/10 hover:text-[#F4EEE5] transition-colors">
                            Less Sugar
                        </button>
                        <button type="button" data-config-quick-note="Extra Hot" aria-pressed="false"
                                class="px-2.5 py-1 rounded-md text-xs bg-[#28241F] text-[#BFB4A5] border border-white/10 hover:text-[#F4EEE5] transition-colors">
                            Extra Hot
                        </button>
                    </div>
                    <input id="pos-config-note" type="text" maxlength="120" aria-label="Item note" class="abc-input text-xs py-1.5" placeholder="Custom item note (max 120 chars)...">
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
                            <span class="text-[#BFB4A5]">Payment Method</span>
                            <span id="pos-success-method" class="font-medium text-[#F4EEE5]">Cash</span>
                        </div>
                        <div id="pos-success-ref-row" class="hidden px-4 py-2.5 flex justify-between">
                            <span class="text-[#BFB4A5]">Reference</span>
                            <span id="pos-success-ref" class="font-medium text-[#F4EEE5] tabular-nums"></span>
                        </div>
                        <div id="pos-success-cash-row" class="px-4 py-2.5 flex justify-between">
                            <span class="text-[#BFB4A5]">Cash Received</span>
                            <span id="pos-success-cash" class="font-medium text-[#F4EEE5] tabular-nums">₱0.00</span>
                        </div>
                        <div id="pos-success-change-row" class="px-4 py-2.5 flex justify-between">
                            <span class="text-[#BFB4A5]">Change Due</span>
                            <span id="pos-success-change" class="font-semibold text-[#22A85B] tabular-nums">₱0.00</span>
                        </div>
                    </div>
                    <a id="pos-success-detail-link" href="#" class="abc-surface px-4 py-3 flex items-center justify-between gap-2 text-sm hover:border-[#D2B077]/45 transition-colors">
                        <span class="font-medium text-[#F4EEE5]">View Full Order Details</span>
                        <svg class="w-4 h-4 text-[#BFB4A5]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 6l6 6-6 6" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    </a>
                    <div id="pos-success-email-status" class="hidden rounded-lg border border-[#D2B077]/35 bg-[#D2B077]/10 px-3 py-2 text-xs text-[#F4EEE5] text-left"></div>
                    <form id="pos-dialog-email-form" class="abc-surface p-3 space-y-2 text-left">
                        <div class="text-[11px] font-semibold uppercase tracking-[0.14em] text-[#D2B077]">Send Receipt by Email</div>
                        <div class="flex gap-2">
                            <input id="pos-dialog-receipt-email" type="email" maxlength="191" required aria-label="Customer email" class="abc-input text-xs py-1.5 flex-1" placeholder="customer@example.ph">
                            <button type="submit" id="pos-dialog-email-submit" class="abc-btn-primary py-1.5 px-3 text-xs shrink-0">Send</button>
                        </div>
                        <label class="inline-flex items-center gap-2 text-[11px] text-[#BFB4A5] cursor-pointer">
                            <input id="pos-dialog-marketing-opt-in" type="checkbox" class="accent-[#D2B077]">
                            <span>Opt in to seasonal drinks newsletter</span>
                        </label>
                    </form>
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
