@php
use App\Support\BusinessTime;
    use App\Support\Money;
@endphp

<x-layouts.app title="{{ $customer->full_name }}">
    <div class="max-w-4xl mx-auto space-y-6">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <h1 class="font-display text-3xl sm:text-4xl font-semibold text-[#F4EEE5]">{{ $customer->full_name }}</h1>
                <p class="text-sm text-[#BFB4A5] mt-1">{{ $customer->displayIdentifier() }}</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('customers.index') }}" class="abc-btn-secondary">All Customers</a>
                <a href="{{ route('customers.edit', $customer) }}" class="abc-btn-primary">Edit Customer</a>
                @can('delete', $customer)
                    <form method="POST"
                          action="{{ route('customers.destroy', $customer) }}"
                          data-confirm-title="Remove Customer"
                          data-confirm="{{ $orders->total() > 0 ? 'Remove this customer from active lists while preserving historical sales receipts?' : 'Permanently delete this unreferenced customer?' }}">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="abc-btn-danger">Delete</button>
                    </form>
                @endcan
            </div>
        </div>

        <div class="abc-panel p-5 space-y-2 text-sm">
            <div><span class="text-[#BFB4A5]">Joined:</span> {{ BusinessTime::format($customer->created_at, 'M j, Y') }}</div>
            <div><span class="text-[#BFB4A5]">Notes:</span> {{ $customer->notes ?: 'No notes recorded.' }}</div>
        </div>

        <div class="abc-panel overflow-hidden">
            <div class="px-5 py-4 border-b border-white/10">
                <h2 class="font-display text-2xl font-semibold text-[#F4EEE5]">Order History</h2>
            </div>

            @if($orders->isEmpty())
                <div class="p-6 text-sm text-[#BFB4A5]">No orders recorded for this customer yet.</div>
            @else
                <div class="overflow-x-auto">
                    <table class="abc-table">
                        <thead>
                            <tr>
                                <th>Order #</th>
                                <th>Staff</th>
                                <th class="text-right">Lines</th>
                                <th class="text-right">Total</th>
                                <th>Date (Manila)</th>
                                <th class="text-right">Receipt</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($orders as $order)
                                @php
$canViewOrder = auth()->user()->can('view', $order);
@endphp
                                <tr>
                                    <td class="tabular-nums font-medium text-[#D2B077]">
                                        @if($canViewOrder)
                                            <a href="{{ route('orders.show', $order) }}" class="hover:underline">{{ $order->order_number }}</a>
                                        @else
                                            {{ $order->order_number }}
                                        @endif
                                    </td>
                                    <td>{{ $order->staff_name_snapshot }}</td>
                                    <td class="text-right tabular-nums">{{ $order->sales_count }}</td>
                                    <td class="text-right font-semibold tabular-nums">{{ Money::formatPhp($order->total_amount) }}</td>
                                    <td class="text-xs text-[#BFB4A5]">{{ BusinessTime::format($order->created_at) }}</td>
                                    <td class="text-right">
                                        @if($canViewOrder)
                                            <a href="{{ route('orders.receipt', $order) }}" class="abc-btn-secondary py-1 px-2.5 text-xs">Receipt</a>
                                        @else
                                            <span class="text-xs text-[#BFB4A5]">Other cashier</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="px-4 pb-4">{{ $orders->links() }}</div>
            @endif
        </div>
    </div>
</x-layouts.app>
