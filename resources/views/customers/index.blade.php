<x-layouts.app title="Customers">
    <div class="space-y-6">
        <div class="flex flex-col sm:flex-row sm:items-end justify-between gap-4">
            <div>
                <h1 class="font-display text-3xl sm:text-4xl font-semibold text-[#F4EEE5]">Customers</h1>
                <p class="text-sm text-[#BFB4A5] mt-1">Manage cafe customer profiles. Walk-in sales do not require a customer record.</p>
            </div>
            <a href="{{ route('customers.create') }}" class="abc-btn-primary self-start sm:self-auto">Add Customer</a>
        </div>

        <form method="GET" action="{{ route('customers.index') }}" class="abc-panel p-4 flex flex-wrap gap-3 items-end">
            <div class="flex-1 min-w-[220px] space-y-1">
                <label for="cust-search" class="block text-xs font-medium text-[#BFB4A5]">Search Customers</label>
                <input id="cust-search" name="search" type="search" value="{{ $search }}" class="abc-input text-sm py-2" placeholder="Name, email, or phone...">
            </div>
            <button type="submit" class="abc-btn-primary py-2 px-4 text-xs">Search</button>
            <a href="{{ route('customers.index') }}" class="abc-btn-secondary py-2 px-3 text-xs">Reset</a>
        </form>

        @if($customers->isEmpty())
            <x-empty-state title="No customers found" description="Add a customer profile or reset your search filter.">
                <x-slot:action>
                    <a href="{{ route('customers.create') }}" class="abc-btn-primary">Add Customer</a>
                </x-slot:action>
            </x-empty-state>
        @else
            <div class="abc-panel overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="abc-table">
                        <thead>
                            <tr>
                                <th>Full Name</th>
                                <th>Email</th>
                                <th>Phone</th>
                                <th class="text-right">Orders</th>
                                <th class="text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($customers as $customer)
                                <tr>
                                    <td>
                                        <a href="{{ route('customers.show', $customer) }}" class="font-medium text-[#F4EEE5] hover:text-[#D2B077]">
                                            {{ $customer->full_name }}
                                        </a>
                                    </td>
                                    <td class="text-sm text-[#BFB4A5]">{{ $customer->email }}</td>
                                    <td class="text-sm text-[#BFB4A5] tabular-nums">{{ $customer->phone ?: '—' }}</td>
                                    <td class="text-right tabular-nums">{{ $customer->orders_count }}</td>
                                    <td class="text-right whitespace-nowrap">
                                        <div class="inline-flex items-center gap-2">
                                            <a href="{{ route('customers.show', $customer) }}" class="abc-btn-secondary py-1 px-2.5 text-xs">View</a>
                                            <a href="{{ route('customers.edit', $customer) }}" class="abc-btn-secondary py-1 px-2.5 text-xs">Edit</a>
                                            @can('delete', $customer)
                                                <form method="POST"
                                                      action="{{ route('customers.destroy', $customer) }}"
                                                      data-confirm-title="Remove Customer"
                                                      data-confirm="{{ $customer->orders_count > 0 ? 'Remove this customer from active lists while preserving historical sales receipts?' : 'Permanently delete this unreferenced customer?' }}">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="abc-btn-danger py-1 px-2.5 text-xs">Delete</button>
                                                </form>
                                            @endcan
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="px-4 pb-4">{{ $customers->links() }}</div>
            </div>
        @endif
    </div>
</x-layouts.app>
