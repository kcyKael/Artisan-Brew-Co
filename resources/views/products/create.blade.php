<x-layouts.app title="Add Product">
    <div class="max-w-3xl mx-auto space-y-6">
        <div class="flex items-center justify-between gap-4">
            <h1 class="font-display text-3xl sm:text-4xl font-semibold text-[#F5F0E8]">Add Product</h1>
            <a href="{{ route('products.index') }}" class="abc-btn-secondary">Back to Products</a>
        </div>

        <form method="POST" action="{{ route('products.store') }}" enctype="multipart/form-data" class="abc-panel p-6 space-y-5">
            @csrf

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="space-y-1.5">
                    <label for="name" class="block text-sm font-medium text-[#F5F0E8]">Product Name</label>
                    <input id="name" name="name" type="text" required value="{{ old('name') }}" class="abc-input" placeholder="e.g. Spanish Latte">
                </div>

                <div class="space-y-1.5">
                    <label for="category_id" class="block text-sm font-medium text-[#F5F0E8]">Category</label>
                    <select id="category_id" name="category_id" class="abc-input">
                        <option value="">Uncategorized</option>
                        @foreach($categories as $category)
                            <option value="{{ $category->id }}" @selected((string) old('category_id') === (string) $category->id)>{{ $category->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="space-y-1.5">
                    <label for="sku" class="block text-sm font-medium text-[#F5F0E8]">SKU (Optional)</label>
                    <input id="sku" name="sku" type="text" value="{{ old('sku') }}" class="abc-input" placeholder="e.g. ABC-SIG-05">
                </div>

                <div class="space-y-1.5">
                    <label for="price" class="block text-sm font-medium text-[#F5F0E8]">Base Price (PHP)</label>
                    <input id="price" name="price" type="number" step="0.01" min="0" required value="{{ old('price', '150.00') }}" class="abc-input tabular-nums">
                </div>

                <div class="space-y-1.5">
                    <label for="stock_quantity" class="block text-sm font-medium text-[#F5F0E8]">Opening Stock</label>
                    <input id="stock_quantity" name="stock_quantity" type="number" step="1" min="0" required value="{{ old('stock_quantity', 20) }}" class="abc-input tabular-nums">
                </div>

                <div class="space-y-1.5">
                    <label for="low_stock_threshold" class="block text-sm font-medium text-[#F5F0E8]">Low-Stock Alert Threshold</label>
                    <input id="low_stock_threshold" name="low_stock_threshold" type="number" step="1" min="0" required value="{{ old('low_stock_threshold', 5) }}" class="abc-input tabular-nums">
                </div>
            </div>

            <div class="space-y-1.5">
                <label for="description" class="block text-sm font-medium text-[#F5F0E8]">Description</label>
                <textarea id="description" name="description" rows="2" class="abc-input">{{ old('description') }}</textarea>
            </div>

            <div class="space-y-1.5">
                <label for="image" class="block text-sm font-medium text-[#F5F0E8]">Product Image (JPEG, PNG, WebP · max 3 MB)</label>
                <input id="image" name="image" type="file" accept="image/jpeg,image/png,image/webp" class="abc-input text-xs">
            </div>

            <div class="space-y-2">
                <div class="text-sm font-medium text-[#F5F0E8]">Eligible Paid Add-ons</div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                    @foreach($addons as $addon)
                        <label class="abc-surface px-3 py-2 flex items-center justify-between gap-2 text-sm cursor-pointer">
                            <span class="flex items-center gap-2">
                                <input type="checkbox" name="addon_ids[]" value="{{ $addon->id }}" @checked(in_array($addon->id, old('addon_ids', []))) class="accent-[#C6A66B]">
                                <span>{{ $addon->name }}</span>
                            </span>
                            <span class="text-xs text-[#BDB5AA] tabular-nums">+₱{{ number_format((float) $addon->price, 2) }}</span>
                        </label>
                    @endforeach
                </div>
            </div>

            <label class="inline-flex items-center gap-2 text-sm text-[#F5F0E8] cursor-pointer">
                <input type="checkbox" name="is_active" value="1" @checked(old('is_active', true)) class="accent-[#C6A66B]">
                <span>Available for sale on POS</span>
            </label>

            <div class="flex justify-end gap-3 pt-2">
                <a href="{{ route('products.index') }}" class="abc-btn-secondary">Cancel</a>
                <button type="submit" class="abc-btn-primary">Save Product</button>
            </div>
        </form>
    </div>
</x-layouts.app>
