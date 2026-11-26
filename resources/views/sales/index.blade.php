@php
use App\Support\BusinessTime;
    use App\Support\Money;
@endphp

<x-layouts.app title="Sales History">
    <div class="space-y-6">
        <div class="flex flex-col sm:flex-row sm:items-end justify-between gap-4">
            <div>
                <h1 class="font-display text-3xl sm:text-4xl font-semibold text-[#F5F0E8]">Sales History</h1>
                <p class="text-sm text-[#BDB5AA] mt-1">
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
                <label for="sales-search" class="block text-xs font-medium text-[#BDB5AA]">Search</label>
                <input id="sales-search" name="search" type="search" value="{{ $filters['search'] }}" class="abc-input text-sm py-2" placeholder="Product, order #, customer, or staff...">
            </div>

            <div class="space-y-1">
                <label for="sales-from" class="block text-xs font-medium text-[#BDB5AA]">From (Manila)</label>
                <input id="sales-from" name="from" type="date" value="{{ $filters['from'] }}" class="abc-input text-sm py-2">
            </div>

            <div class="space-y-1">
                <label for="sales-to" class="block text-xs font-medium text-[#BDB5AA]">To (Manila)</label>
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
                                <tr>
                                    <td class="tabular-nums whitespace-nowrap">
                                        @if($sale->order)
                                            <a href="{{ route('orders.show', $sale->order) }}" class="font-medium text-[#C6A66B] hover:underline">
                                                {{ $sale->order->order_number }}
                                            </a>
                                        @else
                                            <span class="text-[#BDB5AA]">#{{ $sale->id }}</span>
                                        @endif
                                    </td>
                                    <td>
                                        <div class="font-medium text-[#F5F0E8]">{{ $sale->product_name_snapshot }}</div>
                                        @if($sale->variant_label_snapshot)
                                            <div class="text-xs text-[#C6A66B]">{{ $sale->variant_label_snapshot }}</div>
                                        @endif
                                        @if($sale->saleAddons->isNotEmpty())
                                            <div class="text-xs text-[#BDB5AA]">+ {{ $sale->saleAddons->pluck('addon_name_snapshot')->implode(', ') }}</div>
                                        @endif
                                    </td>
                                    <td>{{ $sale->order?->customer_name_snapshot ?? $sale->customer?->full_name ?? 'Walk-in customer' }}</td>
                                    <td>{{ $sale->order?->staff_name_snapshot ?? $sale->staff?->full_name ?? 'Staff' }}</td>
                                    <td class="text-right font-medium tabular-nums">{{ $sale->quantity }}</td>
                                    <td class="text-right font-semibold text-[#F5F0E8] tabular-nums">{{ Money::formatPhp($sale->total_price) }}</td>
                                    <td class="text-xs text-[#BDB5AA] whitespace-nowrap">{{ BusinessTime::format($sale->created_at) }}</td>
                                    <td class="text-right whitespace-nowrap">
                                        @if($sale->order)
                                            <a href="{{ route('orders.receipt', $sale->order) }}" class="abc-btn-secondary py-1 px-2.5 text-xs">Receipt</a>
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
