@php
use App\Support\Money;
@endphp

<x-layouts.app title="Edit {{ $product->name }}">
    <div class="max-w-4xl mx-auto space-y-6">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <h1 class="font-display text-3xl sm:text-4xl font-semibold text-[#F5F0E8]">{{ $product->name }}</h1>
                <p class="text-sm text-[#BDB5AA] mt-1">
                    Current stock: <strong class="text-[#C6A66B] tabular-nums">{{ $product->stock_quantity }}</strong> · Version #{{ $product->stock_version }}
                </p>
            </div>
            <a href="{{ route('products.index') }}" class="abc-btn-secondary">Back to Products</a>
        </div>

        <form method="POST" action="{{ route('products.update', $product) }}" enctype="multipart/form-data" class="abc-panel p-6 space-y-5">
            @csrf
            @method('PUT')
            <input type="hidden" name="stock_version" value="{{ $product->stock_version }}">

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="space-y-1.5">
                    <label for="name" class="block text-sm font-medium text-[#F5F0E8]">Product Name</label>
                    <input id="name" name="name" type="text" required value="{{ old('name', $product->name) }}" class="abc-input">
                </div>

                <div class="space-y-1.5">
                    <label for="category_id" class="block text-sm font-medium text-[#F5F0E8]">Category</label>
                    <select id="category_id" name="category_id" class="abc-input">
                        <option value="">Uncategorized</option>
                        @foreach($categories as $category)
                            <option value="{{ $category->id }}" @selected((string) old('category_id', $product->category_id) === (string) $category->id)>{{ $category->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="space-y-1.5">
                    <label for="sku" class="block text-sm font-medium text-[#F5F0E8]">SKU</label>
                    <input id="sku" name="sku" type="text" value="{{ old('sku', $product->sku) }}" class="abc-input">
                </div>

                <div class="space-y-1.5">
                    <label for="price" class="block text-sm font-medium text-[#F5F0E8]">Base Price (PHP)</label>
                    <input id="price" name="price" type="number" step="0.01" min="0" required value="{{ old('price', $product->price) }}" class="abc-input tabular-nums">
                </div>

                <div class="space-y-1.5">
                    <label for="stock_quantity" class="block text-sm font-medium text-[#F5F0E8]">Stock Quantity</label>
                    <input id="stock_quantity" name="stock_quantity" type="number" step="1" min="0" required value="{{ old('stock_quantity', $product->stock_quantity) }}" class="abc-input tabular-nums">
                </div>

                <div class="space-y-1.5">
                    <label for="low_stock_threshold" class="block text-sm font-medium text-[#F5F0E8]">Low-Stock Threshold</label>
                    <input id="low_stock_threshold" name="low_stock_threshold" type="number" step="1" min="0" required value="{{ old('low_stock_threshold', $product->low_stock_threshold) }}" class="abc-input tabular-nums">
                </div>
            </div>

            <div class="space-y-1.5">
                <label for="description" class="block text-sm font-medium text-[#F5F0E8]">Description</label>
                <textarea id="description" name="description" rows="2" class="abc-input">{{ old('description', $product->description) }}</textarea>
            </div>

            <div class="flex flex-col sm:flex-row sm:items-center gap-4">
                @if($product->imageUrl())
                    <img src="{{ $product->imageUrl() }}" alt="{{ $product->name }}" class="w-32 h-24 rounded-lg object-cover border border-white/10">
                @endif
                <div class="flex-1 space-y-2">
                    <label for="image" class="block text-sm font-medium text-[#F5F0E8]">Replace Product Image</label>
                    <input id="image" name="image" type="file" accept="image/jpeg,image/png,image/webp" class="abc-input text-xs">
                    @if($product->image)
                        <label class="inline-flex items-center gap-2 text-xs text-[#BDB5AA] cursor-pointer">
                            <input type="checkbox" name="remove_image" value="1" class="accent-[#C6A66B]">
                            <span>Remove current image</span>
                        </label>
                    @endif
                </div>
            </div>

            <div class="space-y-2">
                <div class="text-sm font-medium text-[#F5F0E8]">Eligible Paid Add-ons</div>
                @php
$selectedAddonIds = old('addon_ids', $product->addons->pluck('id')->all());
@endphp
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                    @foreach($addons as $addon)
                        <label class="abc-surface px-3 py-2 flex items-center justify-between gap-2 text-sm cursor-pointer">
                            <span class="flex items-center gap-2">
                                <input type="checkbox" name="addon_ids[]" value="{{ $addon->id }}" @checked(in_array($addon->id, $selectedAddonIds)) class="accent-[#C6A66B]">
                                <span>{{ $addon->name }}</span>
                            </span>
                            <span class="text-xs text-[#BDB5AA] tabular-nums">+{{ Money::formatPhp($addon->price) }}</span>
                        </label>
                    @endforeach
                </div>
            </div>

            <label class="inline-flex items-center gap-2 text-sm text-[#F5F0E8] cursor-pointer">
                <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $product->is_active)) class="accent-[#C6A66B]">
                <span>Available for sale on POS</span>
            </label>

            <div class="flex justify-end gap-3 pt-2">
                <button type="submit" class="abc-btn-primary">Save Changes</button>
            </div>
        </form>

        <section class="abc-panel p-6 space-y-4">
            <h2 class="font-display text-2xl font-semibold text-[#F5F0E8]">Quick Stock Adjustment</h2>
            <form method="POST" action="{{ route('products.adjust-stock', $product) }}" class="flex flex-wrap items-end gap-3">
                @csrf
                <input type="hidden" name="stock_version" value="{{ $product->stock_version }}">
                <div class="space-y-1">
                    <label for="delta" class="block text-xs font-medium text-[#BDB5AA]">Quantity Change (+ restock / − spoilage)</label>
                    <input id="delta" name="delta" type="number" step="1" required class="abc-input w-48 tabular-nums" placeholder="e.g. 12 or -2">
                </div>
                <button type="submit" class="abc-btn-secondary">Apply Stock Delta</button>
            </form>
        </section>

        <section class="abc-panel p-6 space-y-4">
            <h2 class="font-display text-2xl font-semibold text-[#F5F0E8]">Drink Size &amp; Temperature Variants</h2>
            <p class="text-xs text-[#BDB5AA]">Leave empty for pastries or single-serve items. Variants add their adjustment on top of the base price.</p>

            @if($product->variants->isNotEmpty())
                <div class="overflow-x-auto">
                    <table class="abc-table">
                        <thead>
                            <tr>
                                <th>Size</th>
                                <th>Temp</th>
                                <th class="text-right">Price Adjustment</th>
                                <th>Status</th>
                                <th class="text-right">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($product->variants as $variant)
                                <tr>
                                    <td>{{ $variant->size_label }}</td>
                                    <td class="capitalize">{{ $variant->temperature }}</td>
                                    <td class="text-right tabular-nums">+{{ Money::formatPhp($variant->price_adjustment) }}</td>
                                    <td>
                                        <x-status-badge :tone="$variant->is_active ? 'success' : 'neutral'">
                                            {{ $variant->is_active ? 'Active' : 'Inactive' }}
                                        </x-status-badge>
                                    </td>
                                    <td class="text-right">
                                        <form method="POST" action="{{ route('menu-config.variants.destroy', $variant) }}" class="inline">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="abc-btn-danger py-1 px-2.5 text-xs">Remove</button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif

            <form method="POST" action="{{ route('menu-config.variants.store', $product) }}" class="grid grid-cols-1 sm:grid-cols-4 gap-3 items-end pt-2 border-t border-white/10">
                @csrf
                <div class="space-y-1">
                    <label for="size_label" class="block text-xs font-medium text-[#BDB5AA]">Size Label</label>
                    <input id="size_label" name="size_label" type="text" required class="abc-input text-sm py-2" placeholder="16 oz (Large)">
                </div>
                <div class="space-y-1">
                    <label for="temperature" class="block text-xs font-medium text-[#BDB5AA]">Temperature</label>
                    <select id="temperature" name="temperature" class="abc-input text-sm py-2">
                        <option value="hot">Hot</option>
                        <option value="iced">Iced</option>
                    </select>
                </div>
                <div class="space-y-1">
                    <label for="price_adjustment" class="block text-xs font-medium text-[#BDB5AA]">Adjustment (PHP)</label>
                    <input id="price_adjustment" name="price_adjustment" type="number" step="0.01" min="0" value="0.00" required class="abc-input text-sm py-2 tabular-nums">
                </div>
                <button type="submit" class="abc-btn-primary py-2">Add Variant</button>
            </form>
        </section>
    </div>
</x-layouts.app>
