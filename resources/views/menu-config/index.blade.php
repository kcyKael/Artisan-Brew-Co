@php
use App\Support\Money;
@endphp

<x-layouts.app title="Menu Options">
    <div class="space-y-6">
        <div class="flex flex-col sm:flex-row sm:items-end justify-between gap-4">
            <div>
                <h1 class="font-display text-3xl sm:text-4xl font-semibold text-[#F5F0E8]">Menu Categories, Variants &amp; Add-ons</h1>
                <p class="text-sm text-[#BDB5AA] mt-1">Configure drink sizes, hot/iced options, paid add-ons, and product eligibility.</p>
            </div>
            <a href="{{ route('products.index') }}" class="abc-btn-secondary self-start sm:self-auto">Manage Products</a>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <section class="abc-panel p-5 space-y-4">
                <h2 class="font-display text-2xl font-semibold text-[#F5F0E8]">Categories</h2>

                <form method="POST" action="{{ route('menu-config.categories.store') }}" class="grid grid-cols-1 sm:grid-cols-3 gap-2 items-end">
                    @csrf
                    <div class="sm:col-span-2 space-y-1">
                        <label for="cat-name" class="block text-xs font-medium text-[#BDB5AA]">New Category Name</label>
                        <input id="cat-name" name="name" type="text" required class="abc-input text-sm py-2" placeholder="e.g. Seasonal Specials">
                    </div>
                    <div class="flex items-center gap-2">
                        <label class="text-xs text-[#BDB5AA] inline-flex items-center gap-1.5">
                            <input type="checkbox" name="is_drink" value="1" checked class="accent-[#C6A66B]">
                            <span>Drink</span>
                        </label>
                        <button type="submit" class="abc-btn-primary py-2 px-3 text-xs flex-1">Add</button>
                    </div>
                </form>

                <div class="space-y-2">
                    @foreach($categories as $category)
                        <div class="abc-surface p-3 flex flex-wrap items-center justify-between gap-2">
                            <form method="POST" action="{{ route('menu-config.categories.update', $category) }}" class="flex-1 flex flex-wrap items-center gap-2">
                                @csrf
                                @method('PUT')
                                <input type="text" name="name" value="{{ $category->name }}" aria-label="Category name" class="abc-input py-1.5 text-xs flex-1 min-w-[130px]">
                                <input type="number" name="sort_order" value="{{ $category->sort_order }}" aria-label="Sort order" class="abc-input py-1.5 text-xs w-16 tabular-nums">
                                <label class="text-xs text-[#BDB5AA] inline-flex items-center gap-1">
                                    <input type="checkbox" name="is_drink" value="1" @checked($category->is_drink) class="accent-[#C6A66B]"> Drink
                                </label>
                                <label class="text-xs text-[#BDB5AA] inline-flex items-center gap-1">
                                    <input type="checkbox" name="is_active" value="1" @checked($category->is_active) class="accent-[#C6A66B]"> Active
                                </label>
                                <button type="submit" class="abc-btn-secondary py-1 px-2.5 text-xs">Save</button>
                            </form>
                            @if($category->products_count === 0)
                                <form method="POST" action="{{ route('menu-config.categories.destroy', $category) }}">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="abc-btn-danger py-1 px-2 text-xs">Delete</button>
                                </form>
                            @endif
                        </div>
                    @endforeach
                </div>
            </section>

            <section class="abc-panel p-5 space-y-4">
                <h2 class="font-display text-2xl font-semibold text-[#F5F0E8]">Paid Add-ons</h2>

                <form method="POST" action="{{ route('menu-config.addons.store') }}" class="grid grid-cols-1 sm:grid-cols-4 gap-2 items-end">
                    @csrf
                    <div class="sm:col-span-2 space-y-1">
                        <label for="addon-name" class="block text-xs font-medium text-[#BDB5AA]">New Add-on</label>
                        <input id="addon-name" name="name" type="text" required class="abc-input text-sm py-2" placeholder="e.g. Hazelnut Syrup">
                    </div>
                    <div class="space-y-1">
                        <label for="addon-price" class="block text-xs font-medium text-[#BDB5AA]">Price (PHP)</label>
                        <input id="addon-price" name="price" type="number" step="0.01" min="0" required class="abc-input text-sm py-2 tabular-nums" placeholder="25.00">
                    </div>
                    <button type="submit" class="abc-btn-primary py-2 text-xs">Add</button>
                </form>

                <div class="space-y-2">
                    @foreach($addons as $addon)
                        <div class="abc-surface p-3 flex flex-wrap items-center justify-between gap-2">
                            <form method="POST" action="{{ route('menu-config.addons.update', $addon) }}" class="flex-1 flex flex-wrap items-center gap-2">
                                @csrf
                                @method('PUT')
                                <input type="text" name="name" value="{{ $addon->name }}" aria-label="Add-on name" class="abc-input py-1.5 text-xs flex-1 min-w-[120px]">
                                <input type="number" step="0.01" min="0" name="price" value="{{ $addon->price }}" aria-label="Add-on price" class="abc-input py-1.5 text-xs w-24 tabular-nums">
                                <input type="hidden" name="sort_order" value="{{ $addon->sort_order }}">
                                <label class="text-xs text-[#BDB5AA] inline-flex items-center gap-1">
                                    <input type="checkbox" name="is_active" value="1" @checked($addon->is_active) class="accent-[#C6A66B]"> Active
                                </label>
                                <button type="submit" class="abc-btn-secondary py-1 px-2.5 text-xs">Save</button>
                            </form>
                            <form method="POST" action="{{ route('menu-config.addons.destroy', $addon) }}" data-confirm-title="Delete Add-on" data-confirm="Remove '{{ $addon->name }}' from eligible products? Past receipts remain intact.">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="abc-btn-danger py-1 px-2 text-xs">Delete</button>
                            </form>
                        </div>
                    @endforeach
                </div>
            </section>
        </div>

        <section class="abc-panel p-5 space-y-4">
            <h2 class="font-display text-2xl font-semibold text-[#F5F0E8]">Per-Product Variant &amp; Add-on Eligibility</h2>
            <div class="overflow-x-auto">
                <table class="abc-table">
                    <thead>
                        <tr>
                            <th>Product</th>
                            <th>Offered Variants</th>
                            <th>Eligible Add-ons</th>
                            <th class="text-right">Configure</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($products as $product)
                            <tr>
                                <td>
                                    <div class="font-medium text-[#F5F0E8]">{{ $product->name }}</div>
                                    <div class="text-xs text-[#BDB5AA]">{{ $product->category?->name ?? 'Menu' }} · Base {{ Money::formatPhp($product->price) }}</div>
                                </td>
                                <td class="text-xs text-[#BDB5AA]">
                                    @if($product->variants->isEmpty())
                                        <span>Single item (no drink variants)</span>
                                    @else
                                        {{ $product->variants->map(fn ($v) => $v->displayLabel().' (+'.Money::formatPhp($v->price_adjustment).')')->implode(', ') }}
                                    @endif
                                </td>
                                <td>
                                    <form method="POST" action="{{ route('menu-config.products.addons.sync', $product) }}" class="flex flex-wrap items-center gap-2">
                                        @csrf
                                        @method('PUT')
                                        @foreach($addons as $addon)
                                            <label class="text-xs inline-flex items-center gap-1 text-[#BDB5AA]">
                                                <input type="checkbox" name="addon_ids[]" value="{{ $addon->id }}" @checked($product->addons->contains('id', $addon->id)) class="accent-[#C6A66B]">
                                                <span>{{ $addon->name }}</span>
                                            </label>
                                        @endforeach
                                        <button type="submit" class="abc-btn-secondary py-1 px-2 text-xs">Save Add-ons</button>
                                    </form>
                                </td>
                                <td class="text-right whitespace-nowrap">
                                    <a href="{{ route('products.edit', $product) }}" class="abc-btn-secondary py-1 px-2.5 text-xs">Edit Variants</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</x-layouts.app>
