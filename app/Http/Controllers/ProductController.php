<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProductRequest;
use App\Http\Requests\StockAdjustmentRequest;
use App\Models\Addon;
use App\Models\Category;
use App\Models\Product;
use App\Services\ImagePreparationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ProductController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('search', ''));
        $categoryId = $request->query('category_id');
        $status = (string) $request->query('status', 'active');

        $query = Product::query()
            ->with(['category', 'variants', 'addons'])
            ->withCount('sales');

        match ($status) {
            'archived' => $query->archived(),
            'low_stock' => $query->lowStock(),
            'sold_out' => $query->soldOut(),
            'all' => null,
            default => $query->active(),
        };

        if ($categoryId !== null && $categoryId !== '' && ctype_digit((string) $categoryId)) {
            $query->where('category_id', (int) $categoryId);
        }

        if ($search !== '') {
            $like = '%'.$search.'%';
            $query->where(function ($sub) use ($like) {
                $sub->where('name', 'like', $like)
                    ->orWhere('sku', 'like', $like);
            });
        }

        $products = $query->orderBy('name')->paginate(15)->withQueryString();
        $categories = Category::query()->orderBy('sort_order')->orderBy('name')->get();

        return view('products.index', [
            'products' => $products,
            'categories' => $categories,
            'filters' => [
                'search' => $search,
                'category_id' => $categoryId,
                'status' => $status,
            ],
        ]);
    }

    public function create(): View
    {
        return view('products.create', [
            'categories' => Category::query()->where('is_active', true)->orderBy('sort_order')->get(),
            'addons' => Addon::query()->where('is_active', true)->orderBy('sort_order')->get(),
        ]);
    }

    public function store(ProductRequest $request, ImagePreparationService $images): RedirectResponse
    {
        $data = $request->validated();
        $preparedImage = null;

        if ($request->hasFile('image')) {
            $preparedImage = $images->prepareProductImage($request->file('image'));
        }

        try {
            $product = DB::transaction(function () use ($data, $request, $preparedImage): Product {
                $sku = trim((string) ($data['sku'] ?? ''));
                if ($sku === '') {
                    $sku = $this->generateUniqueSku(isset($data['category_id']) ? (int) $data['category_id'] : null);
                }

                $product = new Product([
                    'name' => $data['name'],
                    'category_id' => $data['category_id'] ?? null,
                    'sku' => $sku,
                    'description' => $data['description'] ?? null,
                    'price' => $data['price'],
                    'stock_quantity' => (int) $data['stock_quantity'],
                    'low_stock_threshold' => (int) $data['low_stock_threshold'],
                    'image' => $preparedImage,
                    'is_active' => $request->boolean('is_active', true),
                ]);
                $product->save();

                $product->addons()->sync($data['addon_ids'] ?? []);

                return $product;
            });
        } catch (\Throwable $e) {
            if ($preparedImage) {
                $images->deletePreparedImage($preparedImage);
            }
            throw $e;
        }

        return redirect()
            ->route('products.edit', $product)
            ->with('status', "Product '{$product->name}' created. Configure drink sizes/temperatures below if applicable.");
    }

    public function edit(Product $product): View
    {
        $product->load(['variants', 'addons', 'category'])->loadCount('sales');

        return view('products.edit', [
            'product' => $product,
            'categories' => Category::query()->orderBy('sort_order')->get(),
            'addons' => Addon::query()->orderBy('sort_order')->get(),
        ]);
    }

    public function update(ProductRequest $request, Product $product, ImagePreparationService $images): RedirectResponse
    {
        $data = $request->validated();
        $expectedVersion = (int) $data['stock_version'];
        $oldImage = $product->image;
        $newImage = null;

        if ($request->hasFile('image')) {
            $newImage = $images->prepareProductImage($request->file('image'));
        }

        $updated = DB::transaction(function () use ($product, $data, $request, $expectedVersion, $newImage): bool {
            $locked = Product::query()->whereKey($product->id)->lockForUpdate()->first();

            if (! $locked || (int) $locked->stock_version !== $expectedVersion) {
                return false;
            }

            $locked->fill([
                'name' => $data['name'],
                'category_id' => $data['category_id'] ?? null,
                'sku' => $data['sku'] ?: null,
                'description' => $data['description'] ?? null,
                'price' => $data['price'],
                'stock_quantity' => (int) $data['stock_quantity'],
                'low_stock_threshold' => (int) $data['low_stock_threshold'],
                'is_active' => $request->boolean('is_active', false),
            ]);

            if ($newImage) {
                $locked->image = $newImage;
            } elseif ($request->boolean('remove_image')) {
                $locked->image = null;
            }

            $locked->stock_version = $locked->stock_version + 1;
            $locked->save();
            $locked->addons()->sync($data['addon_ids'] ?? []);

            return true;
        });

        if (! $updated) {
            if ($newImage) {
                $images->deletePreparedImage($newImage);
            }

            return back()
                ->withInput()
                ->withErrors(['stock_quantity' => 'Stock changed from a completed sale or another edit while you had this form open. Reloaded latest values.']);
        }

        if ($newImage && $oldImage) {
            $images->deletePreparedImage($oldImage);
        } elseif ($request->boolean('remove_image') && $oldImage) {
            $images->deletePreparedImage($oldImage);
        }

        return redirect()
            ->route('products.edit', $product)
            ->with('status', "Product '{$data['name']}' updated.");
    }

    public function adjustStock(StockAdjustmentRequest $request, Product $product): RedirectResponse
    {
        $delta = (int) $request->validated('delta');
        $expectedVersion = (int) $request->validated('stock_version');

        $result = DB::transaction(function () use ($product, $delta, $expectedVersion): string {
            $locked = Product::query()->whereKey($product->id)->lockForUpdate()->first();
            if (! $locked || (int) $locked->stock_version !== $expectedVersion) {
                return 'stale';
            }

            $newStock = $locked->stock_quantity + $delta;
            if ($newStock < 0) {
                return 'negative';
            }

            $locked->stock_quantity = $newStock;
            $locked->stock_version = $locked->stock_version + 1;
            $locked->save();

            return 'ok';
        });

        if ($result === 'stale') {
            return back()->withErrors(['delta' => 'Inventory changed concurrently. Please retry with the latest stock count.']);
        }
        if ($result === 'negative') {
            return back()->withErrors(['delta' => 'Stock adjustment would cause negative inventory.']);
        }

        return back()->with('status', 'Stock updated.');
    }

    public function destroy(Product $product, ImagePreparationService $images): RedirectResponse
    {
        if ($product->sales()->exists()) {
            $product->archived_at = now();
            $product->is_active = false;
            $product->save();

            return redirect()
                ->route('products.index')
                ->with('status', "Product '{$product->name}' has historical sales and was archived safely.");
        }

        $oldImage = $product->image;
        $name = $product->name;
        $product->addons()->detach();
        $product->variants()->delete();
        $product->delete();
        $images->deletePreparedImage($oldImage);

        return redirect()
            ->route('products.index')
            ->with('status', "Product '{$name}' deleted permanently.");
    }

    public function restore(Product $product): RedirectResponse
    {
        $product->archived_at = null;
        $product->is_active = true;
        $product->save();

        return redirect()
            ->route('products.index')
            ->with('status', "Product '{$product->name}' restored to the active menu.");
    }

    private function generateUniqueSku(?int $categoryId): string
    {
        $categoryName = $categoryId !== null
            ? (string) (Category::query()->whereKey($categoryId)->value('name') ?? '')
            : '';

        $clean = strtoupper((string) preg_replace('/[^A-Za-z0-9]/', '', $categoryName));
        $cat3 = $clean === '' ? 'MNU' : str_pad(substr($clean, 0, 3), 3, 'X');

        $seq = Product::query()->count() + 1;
        do {
            $candidate = 'ABC-'.$cat3.'-'.str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
            $seq++;
        } while (Product::query()->where('sku', $candidate)->exists());

        return $candidate;
    }
}
