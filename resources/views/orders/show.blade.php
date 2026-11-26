@php
use App\Support\BusinessTime;
    use App\Support\Money;
@endphp

<x-layouts.app title="Order {{ $order->order_number }}">
    <div class="max-w-4xl mx-auto space-y-6">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <h1 class="font-display text-3xl sm:text-4xl font-semibold text-[#F5F0E8] tabular-nums">Order {{ $order->order_number }}</h1>
                <p class="text-sm text-[#BDB5AA] mt-1">
                    Completed {{ BusinessTime::format($order->created_at) }} (Asia/Manila) · Channel: {{ $order->source === 'record_sale' ? 'Record Sale' : 'POS Terminal' }}
                </p>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('sales.index') }}" class="abc-btn-secondary">Back to Sales History</a>
                <a href="{{ route('orders.receipt', $order) }}" class="abc-btn-primary">Print / View Receipt</a>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div class="abc-panel p-4 space-y-1">
                <div class="text-xs uppercase tracking-wider text-[#BDB5AA]">Customer</div>
                <div class="text-base font-semibold text-[#F5F0E8]">{{ $order->customer_name_snapshot }}</div>
                @if($order->customer)
                    <div class="text-xs text-[#BDB5AA]">{{ $order->customer->displayIdentifier() }}</div>
                @endif
            </div>

            <div class="abc-panel p-4 space-y-1">
                <div class="text-xs uppercase tracking-wider text-[#BDB5AA]">Processed By</div>
                <div class="text-base font-semibold text-[#F5F0E8]">{{ $order->staff_name_snapshot }}</div>
                @if($order->staff)
                    <div class="text-xs text-[#BDB5AA]">{{ ucfirst($order->staff->role) }} · {{ $order->staff->username }}</div>
                @endif
            </div>

            <div class="abc-panel p-4 space-y-1">
                <div class="text-xs uppercase tracking-wider text-[#BDB5AA]">Cash Payment</div>
                <div class="text-xl font-semibold text-[#C6A66B] tabular-nums">{{ Money::formatPhp($order->total_amount) }}</div>
                <div class="text-xs text-[#BDB5AA] tabular-nums">
                    Cash: {{ Money::formatPhp($order->cash_received) }} · Change: {{ Money::formatPhp($order->change_amount) }}
                </div>
            </div>
        </div>

        <div class="abc-panel overflow-hidden">
            <div class="px-5 py-4 border-b border-white/10">
                <h2 class="font-display text-2xl font-semibold text-[#F5F0E8]">Line Items (Historical Snapshot)</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="abc-table">
                    <thead>
                        <tr>
                            <th>Product &amp; Options</th>
                            <th class="text-right">Unit Price</th>
                            <th class="text-right">Qty</th>
                            <th class="text-right">Line Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($order->sales as $sale)
                            <tr>
                                <td>
                                    <div class="font-medium text-[#F5F0E8]">{{ $sale->product_name_snapshot }}</div>
                                    @if($sale->variant_label_snapshot)
                                        <div class="text-xs text-[#C6A66B]">{{ $sale->variant_label_snapshot }}</div>
                                    @endif
                                    @if($sale->saleAddons->isNotEmpty())
                                        <div class="text-xs text-[#BDB5AA]">
                                            Add-ons:
                                            @foreach($sale->saleAddons as $addon)
                                                {{ $addon->addon_name_snapshot }} ({{ Money::formatPhp($addon->addon_price_snapshot) }})@unless($loop->last), @endunless
                                            @endforeach
                                        </div>
                                    @endif
                                </td>
                                <td class="text-right tabular-nums">{{ Money::formatPhp($sale->unit_price) }}</td>
                                <td class="text-right tabular-nums">{{ $sale->quantity }}</td>
                                <td class="text-right font-semibold tabular-nums">{{ Money::formatPhp($sale->total_price) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-layouts.app>
