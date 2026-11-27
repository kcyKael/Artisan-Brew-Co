<?php

namespace App\Http\Controllers;

use App\Models\Addon;
use App\Models\Category;
use App\Models\Product;
use Illuminate\View\View;

class LandingController extends Controller
{
    public function index(): View
    {
        $products = Product::availableForPos()
            ->with(['category', 'variants' => fn ($query) => $query->where('is_active', true)->orderBy('price_adjustment')])
            ->orderBy('id')
            ->get();

        $signatureNames = [
            'Spanish Latte',
            'Salted Honey Oat Latte',
            'Matcha Latte',
            'Single-Origin Cortado',
            'Vanilla Sweet Cream Cold Brew',
            'Flat White',
        ];

        $byName = $products->keyBy('name');
        $featuredBrews = collect($signatureNames)
            ->map(fn (string $name) => $byName->get($name))
            ->filter()
            ->values();

        if ($featuredBrews->count() < 6) {
            $usedIds = $featuredBrews->pluck('id')->all();
            $extras = $products
                ->reject(fn (Product $product) => in_array($product->id, $usedIds, true))
                ->sortByDesc(fn (Product $product) => $product->image ? 1 : 0)
                ->take(6 - $featuredBrews->count());
            $featuredBrews = $featuredBrews->concat($extras)->values();
        }

        $craftBrew = $products->first(fn (Product $product) => $product->name === 'Single-Origin Cortado' && $product->image)
            ?? $products->first(fn (Product $product) => (bool) $product->image);

        return view('landing.index', [
            'featuredBrews' => $featuredBrews,
            'craftBrew' => $craftBrew,
            'menuCount' => $products->count(),
            'categoryCount' => Category::query()->where('is_active', true)->count(),
        ]);
    }

    public function menu(): View
    {
        $categories = Category::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->with([
                'products' => fn ($query) => $query
                    ->availableForPos()
                    ->with(['variants' => fn ($variantQuery) => $variantQuery->where('is_active', true)->orderBy('price_adjustment')])
                    ->orderBy('name'),
            ])
            ->get()
            ->filter(fn (Category $category) => $category->products->isNotEmpty())
            ->values();

        $uncategorizedProducts = Product::availableForPos()
            ->whereNull('category_id')
            ->with(['variants' => fn ($variantQuery) => $variantQuery->where('is_active', true)->orderBy('price_adjustment')])
            ->orderBy('name')
            ->get();

        $addons = Addon::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return view('landing.menu', [
            'categories' => $categories,
            'uncategorizedProducts' => $uncategorizedProducts,
            'addons' => $addons,
        ]);
    }
}
