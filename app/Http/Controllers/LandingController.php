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

    public function about(): View
    {
        $developers = [
            [
                'name' => 'Kyl Aldric Valencia',
                'handle' => 'kcyKael',
                'role' => 'Project Lead, Full-Stack Developer, and System Architect',
                'focus' => 'Led overall system architecture, end-to-end Laravel 12 engineering, Brevo HTTPS email infrastructure, deployment, and security hardening.',
                'github' => 'https://github.com/kcyKael',
                'badge' => 'Lead & Architect',
            ],
            [
                'name' => 'Emran Ryan Maina',
                'handle' => 'ramenagiii',
                'role' => 'Backend Developer and Database Developer',
                'focus' => 'Designed relational schema integrity, transactional POS checkout queries, stock locking, and historical ledger persistence.',
                'github' => 'https://github.com/ramenagiii',
                'badge' => 'Backend & Database',
            ],
            [
                'name' => 'Jerahmeel Romero',
                'handle' => 'jdgr1',
                'role' => 'Frontend and Product Module Developer, UI/UX Lead Developer',
                'focus' => 'Crafted the dark coffee-studio interface, product catalog & menu configuration flows, and responsive POS counter interactions.',
                'github' => 'https://github.com/jdgr1',
                'badge' => 'UI/UX & Product',
            ],
            [
                'name' => 'Erin Gabrielle Maliñana',
                'handle' => 'O3r1n-Br13ll3',
                'role' => 'Quality Assurance and Integration Developer, UI/UX Assistant Developer',
                'focus' => 'Validated end-to-end POS, voiding, and email workflows, feature test coverage, accessibility polish, and UI consistency.',
                'github' => 'https://github.com/O3r1n-Br13ll3',
                'badge' => 'QA & Integration',
            ],
        ];

        return view('landing.about', [
            'developers' => $developers,
        ]);
    }
}
