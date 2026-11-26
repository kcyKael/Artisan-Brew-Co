@props(['order', 'animate' => true])

@php
    use App\Support\BusinessTime;
    use App\Support\Money;
@endphp

<div aria-live="polite">
    <article id="receipt-paper" class="receipt-paper {{ $animate ? 'receipt-unroll' : '' }} text-xs leading-relaxed">
        <div class="text-center border-b border-dashed border-neutral-300 pb-3 mb-3 space-y-1">
            <img src="{{ asset('images/abc-logo.png') }}" alt="Artisan Brew Co." class="w-10 h-10 mx-auto object-contain border-0">
            <div class="text-base font-bold tracking-wide uppercase text-[#1A1D21]">Artisan Brew Co.</div>
            <div class="text-[11px] text-[#6B7280]">Specialty Coffee &amp; Bakery Counter · Manila</div>
            <div class="text-[11px] font-semibold tabular-nums pt-1 text-[#1A1D21]">{{ $order->order_number }}</div>
        </div>

        <div class="receipt-line space-y-1 border-b border-dashed border-neutral-300 pb-3 mb-3 text-[11px]" style="--line-index: 0">
            <div class="flex justify-between gap-2">
                <span class="text-[#6B7280]">Date (Manila):</span>
                <span class="font-medium text-right text-[#1A1D21]">{{ BusinessTime::format($order->created_at) }}</span>
            </div>
            <div class="flex justify-between gap-2">
                <span class="text-[#6B7280]">Cashier:</span>
                <span class="font-medium text-right text-[#1A1D21]">{{ $order->staff_name_snapshot }}</span>
            </div>
            <div class="flex justify-between gap-2">
                <span class="text-[#6B7280]">Customer:</span>
                <span class="font-medium text-right text-[#1A1D21]">{{ $order->customer_name_snapshot }}</span>
            </div>
        </div>

        <div class="border-b border-dashed border-neutral-300 pb-3 mb-3 space-y-2">
            @foreach($order->sales as $idx => $sale)
                <div class="receipt-line" style="--line-index: {{ $idx + 1 }}">
                    <div class="flex justify-between gap-2 font-semibold text-[#1A1D21]">
                        <span>{{ $sale->quantity }}× {{ $sale->product_name_snapshot }}</span>
                        <span class="tabular-nums shrink-0">{{ Money::formatPhp($sale->total_price) }}</span>
                    </div>
                    @if($sale->variant_label_snapshot)
                        <div class="text-[11px] text-[#6B7280] pl-3">{{ $sale->variant_label_snapshot }}</div>
                    @endif
                    @foreach($sale->saleAddons as $addon)
                        <div class="text-[11px] text-[#6B7280] pl-3 flex justify-between">
                            <span>+ {{ $addon->addon_name_snapshot }}</span>
                            <span class="tabular-nums">{{ Money::formatPhp($addon->addon_price_snapshot) }}</span>
                        </div>
                    @endforeach
                    <div class="text-[11px] text-[#6B7280] pl-3 tabular-nums">
                        @ {{ Money::formatPhp($sale->unit_price) }} each
                    </div>
                </div>
            @endforeach
        </div>

        @php
            $totalsIndex = $order->sales->count() + 1;
        @endphp
        <div class="receipt-line space-y-1.5 text-xs border-b border-dashed border-neutral-300 pb-3 mb-3" style="--line-index: {{ $totalsIndex }}">
            <div class="flex justify-between text-[11px] text-[#6B7280]">
                <span>Subtotal</span>
                <span class="tabular-nums">{{ Money::formatPhp($order->subtotal_amount) }}</span>
            </div>
            <div class="flex justify-between text-sm font-bold text-[#1A1D21]">
                <span>TOTAL (PHP)</span>
                <span class="tabular-nums">{{ Money::formatPhp($order->total_amount) }}</span>
            </div>
            <div class="flex justify-between text-[11px] text-[#6B7280]">
                <span>Cash Received</span>
                <span class="tabular-nums text-[#1A1D21]">{{ Money::formatPhp($order->cash_received) }}</span>
            </div>
            <div class="flex justify-between text-[11px] font-semibold text-[#1A1D21]">
                <span>Change Due</span>
                <span class="tabular-nums">{{ Money::formatPhp($order->change_amount) }}</span>
            </div>
        </div>

        <div class="receipt-line text-center text-[11px] text-[#6B7280] space-y-1" style="--line-index: {{ $totalsIndex + 1 }}">
            <div>{{ config('pos.receipt_footer') }}</div>
            <div>Reprint ID: {{ $order->order_number }}</div>
        </div>
    </article>
</div>
