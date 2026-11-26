@php
    use App\Support\BusinessTime;
    use App\Support\Money;
@endphp

<x-layouts.app title="Receipt {{ $order->order_number }}">
    <div class="max-w-5xl mx-auto space-y-6">
        <div class="no-print flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <h1 class="font-display text-3xl font-semibold text-[#F4EEE5]">Order Confirmation &amp; Receipt</h1>
                <p class="text-sm text-[#BFB4A5]">Recorded {{ BusinessTime::format($order->created_at) }} · Reprint anytime without duplicating the sale.</p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <button type="button" data-receipt-layout="80mm" aria-pressed="true" class="abc-btn-primary py-2 px-3 text-xs">80mm Thermal</button>
                <button type="button" data-receipt-layout="a4" aria-pressed="false" class="abc-btn-secondary py-2 px-3 text-xs">A4 View</button>
                <a href="{{ route('sales.index') }}" class="abc-btn-secondary py-2 px-3 text-xs">Sales History</a>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">
            <section class="no-print lg:col-span-6 abc-panel p-6 sm:p-8 text-center space-y-5">
                <div class="mx-auto w-14 h-14 rounded-full bg-[var(--color-success-tint-dark)] border border-[#22A85B]/40 flex items-center justify-center text-[#22A85B]">
                    <svg class="w-7 h-7" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
                        <path d="M5 13l4 4L19 7" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </div>

                <div class="space-y-1">
                    <h2 class="font-display text-3xl font-semibold text-[#F4EEE5]">Payment Successful!</h2>
                    <p class="text-xs text-[#BFB4A5] tabular-nums">Receipt {{ $order->order_number }}</p>
                </div>

                <div class="abc-surface p-4 space-y-1">
                    <div class="text-[11px] font-semibold uppercase tracking-[0.16em] text-[#BFB4A5]">Total Amount Paid</div>
                    <div class="text-3xl font-semibold text-[#22A85B] tabular-nums">{{ Money::formatPhp($order->total_amount) }}</div>
                </div>

                <div class="abc-surface divide-y divide-white/10 text-sm text-left">
                    <div class="px-4 py-3 flex items-center justify-between gap-2">
                        <span class="text-[#BFB4A5]">Cash Received</span>
                        <span class="font-medium text-[#F4EEE5] tabular-nums">{{ Money::formatPhp($order->cash_received) }}</span>
                    </div>
                    <div class="px-4 py-3 flex items-center justify-between gap-2">
                        <span class="text-[#BFB4A5]">Change Due</span>
                        <span class="font-semibold text-[#22A85B] tabular-nums">{{ Money::formatPhp($order->change_amount) }}</span>
                    </div>
                </div>

                <a href="{{ route('orders.show', $order) }}" class="abc-surface px-4 py-3 flex items-center justify-between gap-3 text-sm hover:border-[#D2B077]/45 transition-colors">
                    <span class="font-medium text-[#F4EEE5]">View Full Order Details</span>
                    <svg class="w-4 h-4 text-[#BFB4A5]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M9 6l6 6-6 6" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </a>

                <div class="flex flex-col sm:flex-row gap-3 pt-1">
                    <a href="{{ route('pos.index') }}" class="abc-btn-secondary flex-1 min-h-[44px]">New Order</a>
                    <button type="button" data-print-receipt class="abc-btn-success flex-1 min-h-[44px]">
                        <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M6 9V3h12v6M6 18H4a2 2 0 01-2-2v-5a2 2 0 012-2h16a2 2 0 012 2v5a2 2 0 01-2 2h-2" stroke-linecap="round" stroke-linejoin="round"/>
                            <rect x="6" y="14" width="12" height="7"/>
                        </svg>
                        <span>Print Receipt</span>
                    </button>
                </div>
            </section>

            <section class="lg:col-span-6 flex justify-center">
                <x-thermal-receipt :order="$order" />
            </section>
        </div>
    </div>
</x-layouts.app>
