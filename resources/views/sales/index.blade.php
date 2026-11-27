@php
use App\Support\BusinessTime;
    use App\Support\Money;
@endphp

<x-layouts.app title="Sales History">
    <div class="space-y-6">
        <div class="flex flex-col sm:flex-row sm:items-end justify-between gap-4">
            <div>
                <h1 class="font-display text-3xl sm:text-4xl font-semibold text-[#F4EEE5]">Sales History</h1>
                <p class="text-sm text-[#BFB4A5] mt-1">
                    @if(auth()->user()->isAdmin())
                        Line-level ledger across all staff, products, customers, and receipts.
                    @else
                        Line-level ledger of sales you completed at the counter.
                    @endif
                </p>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('sales.record') }}" class="abc-btn-secondary">Record Sale</a>
                <a href="{{ route('pos.index') }}" class="abc-btn-primary">POS Terminal</a>
            </div>
        </div>

        <form method="GET" action="{{ route('sales.index') }}" class="abc-panel p-4 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3 items-end">
            <div class="lg:col-span-2 space-y-1">
                <label for="sales-search" class="block text-xs font-medium text-[#BFB4A5]">Search</label>
                <input id="sales-search" name="search" type="search" value="{{ $filters['search'] }}" class="abc-input text-sm py-2" placeholder="Product, order #, customer, or staff...">
            </div>

            <div class="space-y-1">
                <label for="sales-from" class="block text-xs font-medium text-[#BFB4A5]">From (Manila)</label>
                <input id="sales-from" name="from" type="date" value="{{ $filters['from'] }}" class="abc-input text-sm py-2">
            </div>

            <div class="space-y-1">
                <label for="sales-to" class="block text-xs font-medium text-[#BFB4A5]">To (Manila)</label>
                <input id="sales-to" name="to" type="date" value="{{ $filters['to'] }}" class="abc-input text-sm py-2">
            </div>

            <div class="flex gap-2">
                @if(auth()->user()->isAdmin())
                    <select name="staff_id" aria-label="Filter by staff" class="abc-input text-sm py-2">
                        <option value="">All staff</option>
                        @foreach($staffList as $member)
                            <option value="{{ $member->id }}" @selected((string) $filters['staff_id'] === (string) $member->id)>{{ $member->full_name }}</option>
                        @endforeach
                    </select>
                @endif
                <button type="submit" class="abc-btn-primary py-2 px-4 text-xs shrink-0">Filter</button>
                <a href="{{ route('sales.index') }}" class="abc-btn-secondary py-2 px-3 text-xs shrink-0">Reset</a>
            </div>
        </form>

        @if($sales->isEmpty())
            <x-empty-state title="No matching sales records" description="Adjust your date or search filters, or complete a sale at the counter.">
                <x-slot:action>
                    <a href="{{ route('pos.index') }}" class="abc-btn-primary">Open POS Terminal</a>
                </x-slot:action>
            </x-empty-state>
        @else
            <div class="abc-panel overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="abc-table">
                        <thead>
                            <tr>
                                <th>Order #</th>
                                <th>Product</th>
                                <th>Customer</th>
                                <th>Staff</th>
                                <th class="text-right">Qty</th>
                                <th class="text-right">Total Price</th>
                                <th>Date (Asia/Manila)</th>
                                <th class="text-right">Receipt</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($sales as $sale)
                                @php
                                    $order = $sale->order;
                                    $orderTypeLabel = ($order?->order_type ?? 'dine_in') === 'take_out' ? 'Take-out' : 'Dine-in';
                                    $paymentLabel = match ($order?->payment_method ?? 'cash') {
                                        'card' => 'Card',
                                        'gcash' => 'GCash',
                                        default => 'Cash',
                                    };
                                @endphp
                                <tr>
                                    <td class="tabular-nums whitespace-nowrap">
                                        @if($order)
                                            <div class="flex flex-col gap-1">
                                                <div class="flex items-center gap-2">
                                                    <a href="{{ route('orders.show', $order) }}" class="font-medium text-[#D2B077] hover:underline">
                                                        {{ $order->order_number }}
                                                    </a>
                                                    @if($order->status === 'voided')
                                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold bg-[#E07A7A]/15 text-[#E07A7A] border border-[#E07A7A]/30">Voided</span>
                                                    @endif
                                                </div>
                                                <div class="flex flex-wrap items-center gap-1.5 text-[11px]">
                                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full bg-white/5 text-[#BFB4A5] border border-white/10">{{ $orderTypeLabel }}</span>
                                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full bg-white/5 text-[#D2B077] border border-[#D2B077]/25">{{ $paymentLabel }}</span>
                                                </div>
                                                @if(filled($order->cup_name))
                                                    <div class="text-[11px] text-[#BFB4A5]">Cup: {{ $order->cup_name }}</div>
                                                @endif
                                            </div>
                                        @else
                                            <span class="text-[#BFB4A5]">#{{ $sale->id }}</span>
                                        @endif
                                    </td>
                                    <td>
                                        <div class="font-medium text-[#F4EEE5]">{{ $sale->product_name_snapshot }}</div>
                                        @if($sale->variant_label_snapshot)
                                            <div class="text-xs text-[#D2B077]">{{ $sale->variant_label_snapshot }}</div>
                                        @endif
                                        @if($sale->saleAddons->isNotEmpty())
                                            <div class="text-xs text-[#BFB4A5]">+ {{ $sale->saleAddons->pluck('addon_name_snapshot')->implode(', ') }}</div>
                                        @endif
                                        @if(filled($sale->note))
                                            <div class="text-xs text-[#BFB4A5] italic">Note: {{ $sale->note }}</div>
                                        @endif
                                    </td>
                                    <td>{{ $order?->customer_name_snapshot ?? $sale->customer?->full_name ?? 'Walk-in customer' }}</td>
                                    <td>{{ $order?->staff_name_snapshot ?? $sale->staff?->full_name ?? 'Staff' }}</td>
                                    <td class="text-right font-medium tabular-nums">{{ $sale->quantity }}</td>
                                    <td class="text-right font-semibold text-[#F4EEE5] tabular-nums">
                                        <div>{{ Money::formatPhp($sale->total_price) }}</div>
                                        @if($order && (float) $order->discount_amount > 0)
                                            <div class="text-[11px] font-normal text-[#9CAF94]">
                                                {{ $order->discount_label ?: 'Discount' }}: -{{ Money::formatPhp($order->discount_amount) }}
                                            </div>
                                        @endif
                                    </td>
                                    <td class="text-xs text-[#BFB4A5] whitespace-nowrap">{{ BusinessTime::format($sale->created_at) }}</td>
                                    <td class="text-right whitespace-nowrap">
                                        @if($order)
                                            <a href="{{ route('orders.receipt', $order) }}" class="abc-btn-secondary py-1 px-2.5 text-xs">Receipt</a>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="px-4 pb-4">
                    {{ $sales->links() }}
                </div>
            </div>
        @endif
    </div>
</x-layouts.app>
