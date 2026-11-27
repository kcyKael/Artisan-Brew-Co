@props(['order', 'animate' => true])

@php
    use App\Support\BusinessTime;
    use App\Support\Money;

    $orderTypeLabel = ($order->order_type ?? 'dine_in') === 'take_out' ? 'Take-out' : 'Dine-in';
    $paymentLabel = match ($order->payment_method ?? 'cash') {
        'card' => 'Card',
        'gcash' => 'GCash',
        default => 'Cash',
    };
@endphp

<div class="abc-printer-stage"
     data-thermal-printer
     data-animate="{{ $animate ? 'true' : 'false' }}"
     data-state="{{ $animate ? 'ready' : 'done' }}">
    <div class="abc-printer-chute">
        <article id="receipt-paper" class="receipt-paper text-xs leading-relaxed">
            <div class="text-center border-b border-dashed border-stone-300 pb-3 mb-3 space-y-1">
                <img src="{{ asset('images/abc-logo.png') }}" alt="Artisan Brew Co." class="w-10 h-10 mx-auto object-contain border-0">
                <div class="text-base font-bold tracking-wide uppercase text-[#1A1D21]">Artisan Brew Co.</div>
                <div class="text-[11px] text-[#5F584F]">Specialty Coffee &amp; Bakery Counter · Manila</div>
                <div class="text-[11px] font-semibold tabular-nums pt-1 text-[#1A1D21]">{{ $order->order_number }}</div>
                @if($order->status === 'voided')
                    <div class="mt-2 py-1 px-2 border-2 border-red-600 text-red-700 font-bold text-xs uppercase tracking-widest">
                        VOIDED
                    </div>
                @endif
            </div>

            <div class="space-y-1 border-b border-dashed border-stone-300 pb-3 mb-3 text-[11px]">
                <div class="flex justify-between gap-2">
                    <span class="text-[#5F584F]">Date (Manila):</span>
                    <span class="font-medium text-right text-[#1A1D21]">{{ BusinessTime::format($order->created_at) }}</span>
                </div>
                <div class="flex justify-between gap-2">
                    <span class="text-[#5F584F]">Cashier:</span>
                    <span class="font-medium text-right text-[#1A1D21]">{{ $order->staff_name_snapshot }}</span>
                </div>
                <div class="flex justify-between gap-2">
                    <span class="text-[#5F584F]">Customer:</span>
                    <span class="font-medium text-right text-[#1A1D21]">{{ $order->customer_name_snapshot }}</span>
                </div>
                <div class="flex justify-between gap-2">
                    <span class="text-[#5F584F]">Order Type:</span>
                    <span class="font-medium text-right text-[#1A1D21]">{{ $orderTypeLabel }}</span>
                </div>
                @if(filled($order->cup_name))
                    <div class="flex justify-between gap-2">
                        <span class="text-[#5F584F]">Cup Name:</span>
                        <span class="font-medium text-right text-[#1A1D21]">{{ $order->cup_name }}</span>
                    </div>
                @endif
                @if(filled($order->order_note))
                    <div class="flex justify-between gap-2">
                        <span class="text-[#5F584F]">Order Note:</span>
                        <span class="font-medium text-right text-[#1A1D21]">{{ $order->order_note }}</span>
                    </div>
                @endif
            </div>

            <div class="border-b border-dashed border-stone-300 pb-3 mb-3 space-y-2">
                @foreach($order->sales as $sale)
                    <div>
                        <div class="flex justify-between gap-2 font-semibold text-[#1A1D21]">
                            <span>{{ $sale->quantity }}× {{ $sale->product_name_snapshot }}</span>
                            <span class="tabular-nums shrink-0">{{ Money::formatPhp($sale->total_price) }}</span>
                        </div>
                        @if($sale->variant_label_snapshot)
                            <div class="text-[11px] text-[#5F584F] pl-3">{{ $sale->variant_label_snapshot }}</div>
                        @endif
                        @foreach($sale->saleAddons as $addon)
                            <div class="text-[11px] text-[#5F584F] pl-3 flex justify-between">
                                <span>+ {{ $addon->addon_name_snapshot }}</span>
                                <span class="tabular-nums">{{ Money::formatPhp($addon->addon_price_snapshot) }}</span>
                            </div>
                        @endforeach
                        @if(filled($sale->note))
                            <div class="text-[11px] text-[#5F584F] pl-3 italic">Note: {{ $sale->note }}</div>
                        @endif
                        <div class="text-[11px] text-[#5F584F] pl-3 tabular-nums">
                            @ {{ Money::formatPhp($sale->unit_price) }} each
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="space-y-1.5 text-xs border-b border-dashed border-stone-300 pb-3 mb-3">
                <div class="flex justify-between text-[11px] text-[#5F584F]">
                    <span>Subtotal</span>
                    <span class="tabular-nums">{{ Money::formatPhp($order->subtotal_amount) }}</span>
                </div>
                @if((float) $order->discount_amount > 0)
                    <div class="flex justify-between text-[11px] text-[#5F584F]">
                        <span>{{ $order->discount_label ?: 'Discount' }}</span>
                        <span class="tabular-nums">-{{ Money::formatPhp($order->discount_amount) }}</span>
                    </div>
                @endif
                <div class="flex justify-between text-sm font-bold text-[#1A1D21]">
                    <span>TOTAL (PHP)</span>
                    <span class="tabular-nums">{{ Money::formatPhp($order->total_amount) }}</span>
                </div>
                <div class="flex justify-between text-[11px] text-[#5F584F]">
                    <span>Payment Method</span>
                    <span class="text-[#1A1D21]">{{ $paymentLabel }}</span>
                </div>
                @if(filled($order->payment_reference))
                    <div class="flex justify-between text-[11px] text-[#5F584F]">
                        <span>Reference</span>
                        <span class="tabular-nums text-[#1A1D21]">{{ $order->payment_reference }}</span>
                    </div>
                @endif
                @if(($order->payment_method ?? 'cash') === 'cash')
                    <div class="flex justify-between text-[11px] text-[#5F584F]">
                        <span>Cash Received</span>
                        <span class="tabular-nums text-[#1A1D21]">{{ Money::formatPhp($order->cash_received) }}</span>
                    </div>
                    <div class="flex justify-between text-[11px] font-semibold text-[#1A1D21]">
                        <span>Change Due</span>
                        <span class="tabular-nums">{{ Money::formatPhp($order->change_amount) }}</span>
                    </div>
                @endif
            </div>

            <div class="text-center text-[11px] text-[#5F584F] space-y-1">
                <p>{{ config('pos.receipt_footer') }}</p>
                <p class="text-[10px]">All prices in PHP (₱) · {{ BusinessTime::timezone() }}</p>
            </div>
        </article>
    </div>

    <div class="abc-printer-body no-print" aria-hidden="true">
        <div class="abc-printer-slot-recess">
            <div class="abc-printer-cutter-bar"></div>
        </div>
        <div class="abc-printer-lip">
            <div class="abc-printer-cutter-teeth"></div>
        </div>
        <div class="abc-printer-deck">
            <div class="abc-printer-brand">
                <span class="abc-printer-badge">ABC</span>
                <span class="abc-printer-model">THERMAL · 80MM</span>
            </div>
            <div class="abc-printer-leds">
                <span class="abc-printer-led"></span>
            </div>
        </div>
        <div class="abc-printer-shadow"></div>
    </div>

    <div class="abc-printer-controls no-print">
        <div class="abc-printer-status-wrap" aria-live="polite">
            <span class="abc-printer-status-dot" aria-hidden="true"></span>
            <span class="abc-printer-status-text" data-printer-status>{{ $animate ? 'Ready' : 'Receipt ready' }}</span>
        </div>
        <button type="button" data-replay-printer class="abc-btn-secondary py-1.5 px-3 text-xs">
            Replay Print
        </button>
    </div>
</div>
