@php
use App\Support\BusinessTime;
    use App\Support\Money;
@endphp

<x-layouts.app title="Receipt {{ $order->order_number }}">
    <div class="max-w-3xl mx-auto space-y-6">
        <div class="no-print flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <h1 class="font-display text-3xl font-semibold text-[#F4EEE5]">Printable Receipt</h1>
                <p class="text-sm text-[#BFB4A5]">Switch between 80mm thermal and A4 layout, then print without duplicating the sale.</p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <button type="button" data-receipt-layout="80mm" aria-pressed="true" class="abc-btn-primary py-2 px-3 text-xs">80mm Thermal</button>
                <button type="button" data-receipt-layout="a4" aria-pressed="false" class="abc-btn-secondary py-2 px-3 text-xs">A4 View</button>
                <button type="button" data-print-receipt class="abc-btn-primary py-2 px-4 text-xs">Print Receipt</button>
                <a href="{{ route('sales.index') }}" class="abc-btn-secondary py-2 px-3 text-xs">Sales History</a>
                <a href="{{ route('pos.index') }}" class="abc-btn-secondary py-2 px-3 text-xs">POS</a>
            </div>
        </div>

        <article id="receipt-paper" class="receipt-paper text-xs leading-relaxed">
            <div class="text-center border-b border-dashed border-neutral-400 pb-3 mb-3 space-y-1">
                <div class="text-base font-bold tracking-wide uppercase">Artisan Brew Co.</div>
                <div class="text-[11px] text-neutral-600">Specialty Coffee &amp; Bakery Counter</div>
                <div class="text-[11px] font-semibold tabular-nums pt-1">{{ $order->order_number }}</div>
            </div>

            <div class="space-y-1 border-b border-dashed border-neutral-400 pb-3 mb-3 text-[11px]">
                <div class="flex justify-between gap-2">
                    <span class="text-neutral-600">Date (Manila):</span>
                    <span class="font-medium text-right">{{ BusinessTime::format($order->created_at) }}</span>
                </div>
                <div class="flex justify-between gap-2">
                    <span class="text-neutral-600">Cashier:</span>
                    <span class="font-medium text-right">{{ $order->staff_name_snapshot }}</span>
                </div>
                <div class="flex justify-between gap-2">
                    <span class="text-neutral-600">Customer:</span>
                    <span class="font-medium text-right">{{ $order->customer_name_snapshot }}</span>
                </div>
            </div>

            <div class="border-b border-dashed border-neutral-400 pb-3 mb-3 space-y-2">
                @foreach($order->sales as $sale)
                    <div>
                        <div class="flex justify-between gap-2 font-semibold">
                            <span>{{ $sale->quantity }}× {{ $sale->product_name_snapshot }}</span>
                            <span class="tabular-nums shrink-0">{{ Money::formatPhp($sale->total_price) }}</span>
                        </div>
                        @if($sale->variant_label_snapshot)
                            <div class="text-[11px] text-neutral-700 pl-3">{{ $sale->variant_label_snapshot }}</div>
                        @endif
                        @foreach($sale->saleAddons as $addon)
                            <div class="text-[11px] text-neutral-700 pl-3 flex justify-between">
                                <span>+ {{ $addon->addon_name_snapshot }}</span>
                                <span class="tabular-nums">{{ Money::formatPhp($addon->addon_price_snapshot) }}</span>
                            </div>
                        @endforeach
                        <div class="text-[11px] text-neutral-600 pl-3 tabular-nums">
                            @ {{ Money::formatPhp($sale->unit_price) }} each
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="space-y-1.5 text-xs border-b border-dashed border-neutral-400 pb-3 mb-3">
                <div class="flex justify-between text-sm font-bold">
                    <span>TOTAL (PHP)</span>
                    <span class="tabular-nums">{{ Money::formatPhp($order->total_amount) }}</span>
                </div>
                <div class="flex justify-between text-[11px]">
                    <span>Cash Received</span>
                    <span class="tabular-nums">{{ Money::formatPhp($order->cash_received) }}</span>
                </div>
                <div class="flex justify-between text-[11px] font-semibold">
                    <span>Change</span>
                    <span class="tabular-nums">{{ Money::formatPhp($order->change_amount) }}</span>
                </div>
            </div>

            <div class="text-center text-[11px] text-neutral-600 space-y-1">
                <div>{{ config('pos.receipt_footer') }}</div>
                <div>Reprint ID: {{ $order->order_number }}</div>
            </div>
        </article>
    </div>
</x-layouts.app>
