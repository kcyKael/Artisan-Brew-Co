<?php

namespace App\Http\Controllers;

use App\Models\Addon;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class MenuConfigController extends Controller
{
    public function index(): View
    {
        $categories = Category::query()->withCount('products')->orderBy('sort_order')->orderBy('name')->get();
        $addons = Addon::query()->withCount('products')->orderBy('sort_order')->orderBy('name')->get();
        $products = Product::query()
            ->active()
            ->with(['category', 'variants', 'addons'])
            ->orderBy('name')
            ->get();

        return view('menu-config.index', [
            'categories' => $categories,
            'addons' => $addons,
            'products' => $products,
        ]);
    }

    public function storeCategory(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:80', 'unique:categories,name'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:999'],
            'is_drink' => ['nullable', 'boolean'],
        ]);

        Category::create([
            'name' => $data['name'],
            'slug' => Str::slug($data['name']).'-'.Str::lower(Str::random(4)),
            'sort_order' => (int) ($data['sort_order'] ?? 0),
            'is_drink' => $request->boolean('is_drink', true),
            'is_active' => true,
        ]);

        return back()->with('status', "Category '{$data['name']}' created.");
    }

    public function updateCategory(Request $request, Category $category): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:80', Rule::unique('categories', 'name')->ignore($category->id)],
            'sort_order' => ['required', 'integer', 'min:0', 'max:999'],
            'is_drink' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $category->update([
            'name' => $data['name'],
            'sort_order' => (int) $data['sort_order'],
            'is_drink' => $request->boolean('is_drink', false),
            'is_active' => $request->boolean('is_active', false),
        ]);

        return back()->with('status', "Category '{$category->name}' updated.");
    }

    public function destroyCategory(Category $category): RedirectResponse
    {
        if ($category->products()->exists()) {
            return back()->withErrors(['category' => "Cannot delete '{$category->name}' while products are assigned to it."]);
        }

        $name = $category->name;
        $category->delete();

        return back()->with('status', "Category '{$name}' deleted.");
    }

    public function storeAddon(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:80', 'unique:addons,name'],
            'price' => ['required', 'numeric', 'min:0', 'max:9999.99', 'regex:/^\d+(?:\.\d{1,2})?$/'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:999'],
        ]);

        Addon::create([
            'name' => $data['name'],
            'price' => $data['price'],
            'sort_order' => (int) ($data['sort_order'] ?? 0),
            'is_active' => true,
        ]);

        return back()->with('status', "Add-on '{$data['name']}' added.");
    }

    public function updateAddon(Request $request, Addon $addon): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:80', Rule::unique('addons', 'name')->ignore($addon->id)],
            'price' => ['required', 'numeric', 'min:0', 'max:9999.99', 'regex:/^\d+(?:\.\d{1,2})?$/'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:999'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $addon->update([
            'name' => $data['name'],
            'price' => $data['price'],
            'sort_order' => (int) $data['sort_order'],
            'is_active' => $request->boolean('is_active', false),
        ]);

        return back()->with('status', "Add-on '{$addon->name}' updated.");
    }

    public function destroyAddon(Addon $addon): RedirectResponse
    {
        $name = $addon->name;
        $addon->products()->detach();
        $addon->delete();

        return back()->with('status', "Add-on '{$name}' deleted.");
    }

    public function storeVariant(Request $request, Product $product): RedirectResponse
    {
        $data = $request->validate([
            'size_label' => ['required', 'string', 'min:1', 'max:40'],
            'temperature' => ['required', Rule::in([ProductVariant::TEMP_HOT, ProductVariant::TEMP_ICED])],
            'price_adjustment' => ['required', 'numeric', 'min:0', 'max:9999.99', 'regex:/^\d+(?:\.\d{1,2})?$/'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:999'],
        ]);

        $duplicate = ProductVariant::query()
            ->where('product_id', $product->id)
            ->where('size_label', $data['size_label'])
            ->where('temperature', $data['temperature'])
            ->exists();

        if ($duplicate) {
            return back()->withErrors(['size_label' => 'That size and temperature combination already exists for this product.']);
        }

        ProductVariant::create([
            'product_id' => $product->id,
            'size_label' => $data['size_label'],
            'temperature' => $data['temperature'],
            'price_adjustment' => $data['price_adjustment'],
            'is_active' => true,
            'sort_order' => (int) ($data['sort_order'] ?? 0),
        ]);

        return back()->with('status', "Added variant to '{$product->name}'.");
    }

    public function updateVariant(Request $request, ProductVariant $variant): RedirectResponse
    {
        $data = $request->validate([
            'size_label' => ['required', 'string', 'min:1', 'max:40'],
            'temperature' => ['required', Rule::in([ProductVariant::TEMP_HOT, ProductVariant::TEMP_ICED])],
            'price_adjustment' => ['required', 'numeric', 'min:0', 'max:9999.99', 'regex:/^\d+(?:\.\d{1,2})?$/'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $duplicate = ProductVariant::query()
            ->where('product_id', $variant->product_id)
            ->where('size_label', $data['size_label'])
            ->where('temperature', $data['temperature'])
            ->where('id', '!=', $variant->id)
            ->exists();

        if ($duplicate) {
            return back()->withErrors(['size_label' => 'Duplicate size and temperature combination for this product.']);
        }

        $variant->update([
            'size_label' => $data['size_label'],
            'temperature' => $data['temperature'],
            'price_adjustment' => $data['price_adjustment'],
            'is_active' => $request->boolean('is_active', false),
        ]);

        return back()->with('status', 'Drink variant updated.');
    }

    public function destroyVariant(ProductVariant $variant): RedirectResponse
    {
        $variant->delete();

        return back()->with('status', 'Drink variant removed.');
    }

    public function syncProductAddons(Request $request, Product $product): RedirectResponse
    {
        $data = $request->validate([
            'addon_ids' => ['nullable', 'array'],
            'addon_ids.*' => ['integer', 'exists:addons,id'],
        ]);

        $product->addons()->sync($data['addon_ids'] ?? []);

        return back()->with('status', "Updated eligible add-ons for '{$product->name}'.");
    }
}
