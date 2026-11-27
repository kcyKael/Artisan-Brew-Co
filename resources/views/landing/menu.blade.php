@php
    use App\Support\Money;
@endphp

<x-landing-shell
    title="Menu — {{ config('pos.brand_name', 'Artisan Brew Co.') }}"
    description="Explore Artisan Brew Co.'s live studio menu: single-origin espresso, ceremonial matcha, cold-steeped chocolate, and house-baked pastries.">
    <section class="relative pt-32 pb-16 overflow-hidden studio-hero-backdrop">
        <div class="pointer-events-none absolute inset-0 studio-atmosphere-glow opacity-85" aria-hidden="true"></div>
        <div class="pointer-events-none absolute inset-0 studio-grain" aria-hidden="true"></div>
        <div class="relative z-10 max-w-7xl mx-auto px-5 sm:px-8 lg:px-12 space-y-8">
            <div class="flex flex-col md:flex-row md:items-end justify-between gap-6">
                <div class="space-y-4 max-w-2xl">
                    <a href="{{ route('home') }}" class="inline-flex items-center gap-2 text-xs uppercase tracking-[0.22em] text-[#BFB4A5] hover:text-[#D2B077] transition-colors">
                        <span aria-hidden="true">&larr;</span>
                        <span>Back to Coffee Studio</span>
                    </a>
                    <h1 class="font-display text-5xl sm:text-6xl font-semibold leading-[1.0] text-[#F4EEE5]">
                        The Studio Menu
                    </h1>
                    <p class="text-base text-[#BFB4A5] leading-relaxed">
                        Prepared daily in small batches. All prices in Philippine Peso (₱), synced directly with our counter catalog.
                    </p>
                </div>
            </div>

            @if($categories->isNotEmpty())
                <nav aria-label="Menu categories" class="flex flex-wrap gap-2.5 pt-2">
                    @foreach($categories as $category)
                        <a href="#cat-{{ $category->id }}" class="px-4 py-2 rounded-full text-xs uppercase tracking-[0.14em] border border-white/12 bg-white/[0.03] text-[#BFB4A5] hover:border-[#D2B077]/50 hover:text-[#F4EEE5] transition-colors">
                            {{ $category->name }}
                        </a>
                    @endforeach
                </nav>
            @endif
        </div>
    </section>

    <section class="studio-section-divider studio-section-brews relative py-16 sm:py-24 space-y-20 overflow-hidden">
        <div class="pointer-events-none absolute inset-0 studio-grain" aria-hidden="true"></div>
        <div class="relative z-10 max-w-7xl mx-auto px-5 sm:px-8 lg:px-12 space-y-20">
            @forelse($categories as $category)
                <div id="cat-{{ $category->id }}" class="studio-anchor space-y-8">
                    <div class="flex items-baseline justify-between gap-4 border-b border-white/10 pb-4">
                        <h2 class="font-display text-3xl sm:text-4xl font-semibold text-[#F4EEE5]">{{ $category->name }}</h2>
                        <span class="text-xs uppercase tracking-[0.2em] text-[#D2B077]">{{ $category->products->count() }} {{ $category->products->count() === 1 ? 'Item' : 'Items' }}</span>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-x-8 gap-y-12">
                        @foreach($category->products as $product)
                            <article class="editorial-card group space-y-4">
                                <div class="editorial-media relative aspect-[4/3] rounded-2xl overflow-hidden border border-white/10 bg-[#161412]">
                                    @if($product->imageUrl())
                                        <img src="{{ $product->imageUrl() }}"
                                             alt="{{ $product->name }}"
                                             loading="lazy"
                                             class="w-full h-full object-cover object-center">
                                    @else
                                        <x-product-image :product="$product" class="w-full h-full rounded-none border-0" />
                                    @endif
                                    @if($product->isSoldOut())
                                        <span class="absolute top-3.5 right-3.5 px-3 py-1 rounded-full text-[11px] font-medium bg-[#161412]/85 text-[#BFB4A5] border border-white/15">
                                            Currently Sold Out
                                        </span>
                                    @endif
                                </div>

                                <div class="space-y-2">
                                    <div class="flex items-baseline justify-between gap-4">
                                        <h3 class="font-display text-2xl font-semibold text-[#F4EEE5]">{{ $product->name }}</h3>
                                        <span class="text-sm font-semibold text-[#D2B077] tabular-nums shrink-0">{{ Money::formatPhp($product->price) }}</span>
                                    </div>
                                    @if(filled($product->description))
                                        <p class="text-sm text-[#BFB4A5] leading-relaxed">{{ $product->description }}</p>
                                    @endif
                                    @if($product->variants->isNotEmpty())
                                        <div class="flex flex-wrap gap-1.5 pt-1">
                                            @foreach($product->variants as $variant)
                                                @php
                                                    $variantPrice = (float) $product->price + (float) $variant->price_adjustment;
                                                @endphp
                                                <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[11px] bg-white/[0.04] text-[#BFB4A5] border border-white/10">
                                                    <span>{{ $variant->label }}</span>
                                                    <span class="text-[#F4EEE5] tabular-nums">{{ Money::formatPhp($variantPrice) }}</span>
                                                </span>
                                            @endforeach
                                        </div>
                                    @endif
                                </div>
                            </article>
                        @endforeach
                    </div>
                </div>
            @empty
                <div class="text-center py-16 text-[#BFB4A5]">
                    No active menu items are currently published.
                </div>
            @endforelse

            @if($uncategorizedProducts->isNotEmpty())
                <div class="space-y-8">
                    <div class="flex items-baseline justify-between gap-4 border-b border-white/10 pb-4">
                        <h2 class="font-display text-3xl sm:text-4xl font-semibold text-[#F4EEE5]">Seasonal &amp; Featured</h2>
                        <span class="text-xs uppercase tracking-[0.2em] text-[#D2B077]">{{ $uncategorizedProducts->count() }} Items</span>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-x-8 gap-y-12">
                        @foreach($uncategorizedProducts as $product)
                            <article class="editorial-card group space-y-4">
                                <div class="editorial-media relative aspect-[4/3] rounded-2xl overflow-hidden border border-white/10 bg-[#161412]">
                                    @if($product->imageUrl())
                                        <img src="{{ $product->imageUrl() }}" alt="{{ $product->name }}" loading="lazy" class="w-full h-full object-cover object-center">
                                    @else
                                        <x-product-image :product="$product" class="w-full h-full rounded-none border-0" />
                                    @endif
                                </div>
                                <div class="flex items-baseline justify-between gap-4">
                                    <h3 class="font-display text-2xl font-semibold text-[#F4EEE5]">{{ $product->name }}</h3>
                                    <span class="text-sm font-semibold text-[#D2B077] tabular-nums shrink-0">{{ Money::formatPhp($product->price) }}</span>
                                </div>
                            </article>
                        @endforeach
                    </div>
                </div>
            @endif

            @if($addons->isNotEmpty())
                <div class="border-t border-white/10 pt-14 space-y-6">
                    <div class="space-y-1">
                        <span class="text-xs uppercase tracking-[0.22em] text-[#D2B077]">Customizations</span>
                        <h2 class="font-display text-3xl font-semibold text-[#F4EEE5]">Available Add-Ons</h2>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                        @foreach($addons as $addon)
                            <div class="flex items-center justify-between px-5 py-3.5 rounded-xl bg-[rgb(22,20,18)] border border-white/10">
                                <span class="text-sm text-[#F4EEE5]">{{ $addon->name }}</span>
                                <span class="text-sm font-medium text-[#D2B077] tabular-nums">+{{ Money::formatPhp($addon->price) }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>
    </section>
</x-landing-shell>
