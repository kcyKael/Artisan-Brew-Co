@php
use App\Support\Money;
@endphp

<x-layouts.app title="Menu & Stock">
    <div class="space-y-6">
        <div class="flex flex-col sm:flex-row sm:items-end justify-between gap-4">
            <div>
                <h1 class="font-display text-3xl sm:text-4xl font-semibold text-[#F4EEE5]">Menu &amp; Stock</h1>
                <p class="text-sm text-[#BFB4A5] mt-1">Manage cafe menu items, base pricing, stock quantities, prepared images, and archives.</p>
            </div>
            <div class="flex gap-2">
                <a href="{{ route('menu-config.index') }}" class="abc-btn-secondary">Menu Configuration</a>
                <a href="{{ route('products.create') }}" class="abc-btn-primary">Add Product</a>
            </div>
        </div>

        <form method="GET" action="{{ route('products.index') }}" class="abc-panel p-4 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 items-end">
            <div class="space-y-1">
                <label for="prod-search" class="block text-xs font-medium text-[#BFB4A5]">Search</label>
                <input id="prod-search" name="search" type="search" value="{{ $filters['search'] }}" class="abc-input text-sm py-2" placeholder="Name or SKU...">
            </div>

            <div class="space-y-1">
                <label for="prod-cat" class="block text-xs font-medium text-[#BFB4A5]">Category</label>
                <select id="prod-cat" name="category_id" class="abc-input text-sm py-2">
                    <option value="">All categories</option>
                    @foreach($categories as $category)
                        <option value="{{ $category->id }}" @selected((string) $filters['category_id'] === (string) $category->id)>{{ $category->name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="space-y-1">
                <label for="prod-status" class="block text-xs font-medium text-[#BFB4A5]">Inventory Status</label>
                <select id="prod-status" name="status" class="abc-input text-sm py-2">
                    <option value="active" @selected($filters['status'] === 'active')>Active Menu</option>
                    <option value="low_stock" @selected($filters['status'] === 'low_stock')>Low stock</option>
                    <option value="sold_out" @selected($filters['status'] === 'sold_out')>Sold out</option>
                    <option value="archived" @selected($filters['status'] === 'archived')>Archived</option>
                    <option value="all" @selected($filters['status'] === 'all')>All Products</option>
                </select>
            </div>

            <div class="flex gap-2">
                <button type="submit" class="abc-btn-primary py-2 px-4 text-xs">Apply</button>
                <a href="{{ route('products.index') }}" class="abc-btn-secondary py-2 px-3 text-xs">Reset</a>
            </div>
        </form>

        @if($products->isEmpty())
            <x-empty-state title="No products found" description="Try another filter or add a new cafe product.">
                <x-slot:action>
                    <a href="{{ route('products.create') }}" class="abc-btn-primary">Add First Product</a>
                </x-slot:action>
            </x-empty-state>
        @else
            <div class="abc-panel overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="abc-table">
                        <thead>
                            <tr>
                                <th>Product</th>
                                <th>Category</th>
                                <th class="text-right">Base Price</th>
                                <th class="text-right">Stock</th>
                                <th>Status</th>
                                <th class="text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($products as $product)
                                <tr>
                                    <td>
                                        <div class="flex items-center gap-3">
                                            @if($product->imageUrl())
                                                <img src="{{ $product->imageUrl() }}" alt="{{ $product->name }}" class="w-12 h-9 rounded-md object-cover border border-white/10 shrink-0">
                                            @else
                                                <div class="w-12 h-9 rounded-md bg-[#28241F] border border-white/10 flex items-center justify-center text-[10px] text-[#BFB4A5] shrink-0">ABC</div>
                                            @endif
                                            <div>
                                                <div class="font-medium text-[#F4EEE5]">{{ $product->name }}</div>
                                                <div class="text-xs text-[#BFB4A5]">
                                                    {{ $product->sku ?: 'No SKU' }} · {{ $product->variants->count() }} variants · {{ $product->addons->count() }} add-ons
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="text-sm text-[#BFB4A5]">{{ $product->category?->name ?? 'Uncategorized' }}</td>
                                    <td class="text-right font-semibold tabular-nums">{{ Money::formatPhp($product->price) }}</td>
                                    <td class="text-right font-semibold tabular-nums">{{ $product->stock_quantity }}</td>
                                    <td>
                                        @if($product->isArchived())
                                            <x-status-badge tone="neutral">Archived</x-status-badge>
                                        @elseif(!$product->is_active)
                                            <x-status-badge tone="neutral">Hidden</x-status-badge>
                                        @elseif($product->isSoldOut())
                                            <x-status-badge tone="danger">Sold out</x-status-badge>
                                        @elseif($product->isLowStock())
                                            <x-status-badge tone="warning">Low stock</x-status-badge>
                                        @else
                                            <x-status-badge tone="success">Available</x-status-badge>
                                        @endif
                                    </td>
                                    <td class="text-right whitespace-nowrap">
                                        <div class="inline-flex items-center gap-2">
                                            <a href="{{ route('products.edit', $product) }}" class="abc-btn-secondary py-1 px-2.5 text-xs">Edit</a>
                                            @if($product->isArchived())
                                                <form method="POST" action="{{ route('products.restore', $product) }}">
                                                    @csrf
                                                    <button type="submit" class="abc-btn-primary py-1 px-2.5 text-xs">Restore</button>
                                                </form>
                                            @else
                                                <form method="POST"
                                                      action="{{ route('products.destroy', $product) }}"
                                                      data-confirm-title="{{ $product->sales_count > 0 ? 'Archive Product' : 'Delete Product' }}"
                                                      data-confirm="{{ $product->sales_count > 0 ? 'This product has historical sales and will be archived safely while preserving past receipts.' : 'Permanently delete this unreferenced product?' }}">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="abc-btn-danger py-1 px-2.5 text-xs">
                                                        {{ $product->sales_count > 0 ? 'Archive' : 'Delete' }}
                                                    </button>
                                                </form>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="px-4 pb-4">{{ $products->links() }}</div>
            </div>
        @endif
    </div>
</x-layouts.app>
