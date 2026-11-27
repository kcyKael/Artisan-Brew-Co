@php
    use App\Support\Money;
@endphp

<x-landing-shell title="{{ config('pos.brand_name', 'Artisan Brew Co.') }} — Contemporary Coffee Studio">
    <section id="hero" class="relative min-h-[100svh] pt-24 pb-16 lg:pt-20 lg:pb-0 flex items-center overflow-hidden bg-[rgb(22,20,18)]">
        <div class="pointer-events-none absolute inset-0 studio-atmosphere-glow"></div>
        <div class="pointer-events-none absolute inset-0 studio-grain opacity-70"></div>

        <div class="relative max-w-7xl mx-auto w-full px-5 sm:px-8 lg:px-12 grid grid-cols-1 lg:grid-cols-12 items-center gap-10 lg:gap-0">
            <div class="lg:col-span-6 relative z-20 space-y-7 pt-4 lg:py-20">
                <div data-hero-reveal class="inline-flex items-center gap-3">
                    <span class="h-px w-8 bg-[#D2B077]/70"></span>
                    <span class="text-xs font-semibold uppercase tracking-[0.28em] text-[#D2B077]">Artisan Brew Co.</span>
                </div>

                <h1 data-hero-reveal class="font-display text-5xl sm:text-6xl xl:text-[5.25rem] font-semibold leading-[0.98] tracking-[-0.025em] text-[#F4EEE5]">
                    Crafted slowly.<br>
                    <span class="text-[#F4EEE5]/90">Savored fully.</span>
                </h1>

                <p data-hero-reveal class="text-base sm:text-lg text-[#BFB4A5] max-w-lg leading-relaxed">
                    Exceptional coffee, thoughtful craft, and a moment worth making time for.
                </p>

                <div data-hero-reveal class="flex flex-wrap items-center gap-4 pt-2">
                    <a href="#signature-brews" class="studio-btn-primary">
                        Explore the Menu
                    </a>
                    <a href="#the-craft" class="studio-btn-ghost">
                        Our Story
                    </a>
                </div>

                <div data-hero-reveal class="pt-6 border-t border-white/10 max-w-md flex flex-wrap items-center gap-8 text-xs text-[#BFB4A5]">
                    <div>
                        <div class="text-[11px] uppercase tracking-[0.2em] text-[#BFB4A5]/75">Selection</div>
                        <div class="text-sm font-medium text-[#F4EEE5] mt-1">{{ $menuCount }} Handcrafted Offerings</div>
                    </div>
                    <div>
                        <div class="text-[11px] uppercase tracking-[0.2em] text-[#BFB4A5]/75">House Focus</div>
                        <div class="text-sm font-medium text-[#F4EEE5] mt-1">Single-Origin &amp; Small Batch</div>
                    </div>
                </div>
            </div>

            <div class="lg:col-span-7 lg:-ml-16 relative z-10 flex items-center justify-center">
                <div id="hero-cup-stage"
                     data-logo-url="{{ asset('images/abc-logo.png') }}"
                     class="relative w-full h-[360px] sm:h-[430px] lg:h-[680px]">
                    <div id="hero-cup-canvas" class="absolute inset-0 opacity-0 transition-opacity duration-700"></div>

                    <div id="hero-cup-fallback" class="absolute inset-0 flex flex-col items-center justify-center transition-opacity duration-700 select-none">
                        <div class="relative flex flex-col items-center">
                            <div class="hero-steam-wisp w-24 h-24 rounded-full bg-gradient-to-t from-[#F4EEE5]/5 via-[#F4EEE5]/15 to-transparent blur-xl -mb-6"></div>
                            <div class="w-56 h-56 sm:w-64 sm:h-64 rounded-full bg-gradient-to-b from-[#25201C] to-[rgb(22,20,18)] border border-[#D2B077]/25 shadow-[0_32px_60px_-20px_rgba(0,0,0,0.85)] flex flex-col items-center justify-center p-8 text-center">
                                <img src="{{ asset('images/abc-logo.png') }}" alt="Artisan Brew Co. emblem" class="w-24 h-24 object-contain border-0 opacity-90">
                                <span class="mt-3 text-[10px] uppercase tracking-[0.28em] text-[#D2B077]">Single-Origin Studio</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section id="signature-brews" class="studio-anchor relative py-24 sm:py-32 border-t border-white/10 bg-[#1A1816]">
        <div class="max-w-7xl mx-auto px-5 sm:px-8 lg:px-12 space-y-16">
            <div data-reveal class="flex flex-col md:flex-row md:items-end justify-between gap-8">
                <div class="space-y-4 max-w-2xl">
                    <span class="text-xs font-semibold uppercase tracking-[0.26em] text-[#D2B077]">Signature Brews</span>
                    <h2 class="font-display text-4xl sm:text-5xl lg:text-6xl font-semibold leading-[1.03] text-[#F4EEE5]">
                        Pours shaped by patience and origin.
                    </h2>
                </div>
                <div class="flex flex-col items-start md:items-end gap-3">
                    <p class="text-sm text-[#BFB4A5] max-w-xs md:text-right leading-relaxed">
                        Every pour and bake on our bar comes directly from our live kitchen and espresso counter.
                    </p>
                    <a href="{{ route('menu.public') }}" class="inline-flex items-center gap-2 text-sm font-medium text-[#D2B077] hover:text-[#DFC08B] transition-colors">
                        <span>View Full Menu</span>
                        <span aria-hidden="true">&rarr;</span>
                    </a>
                </div>
            </div>

            @if($featuredBrews->isNotEmpty())
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-x-8 gap-y-14">
                    @foreach($featuredBrews as $brew)
                        <article data-reveal class="editorial-card group flex flex-col space-y-5 @if($loop->index % 3 === 1) lg:mt-10 @endif">
                            <a href="{{ route('menu.public') }}{{ $brew->category_id ? '#cat-' . $brew->category_id : '' }}" class="block editorial-media relative aspect-[4/5] overflow-hidden rounded-2xl bg-[#161412] border border-white/10">
                                @if($brew->imageUrl())
                                    <img src="{{ $brew->imageUrl() }}"
                                         alt="{{ $brew->name }}"
                                         loading="lazy"
                                         class="w-full h-full object-cover object-center">
                                @else
                                    <x-product-image :product="$brew" class="w-full h-full rounded-none border-0" />
                                @endif
                                <div class="pointer-events-none absolute inset-0 bg-gradient-to-t from-[rgb(22,20,18)]/70 via-transparent to-transparent"></div>
                                <div class="absolute bottom-4 left-5 right-5 flex items-center justify-between text-xs">
                                    <span class="uppercase tracking-[0.2em] text-[#D2B077]">{{ $brew->category?->name ?? 'House Pour' }}</span>
                                    <span class="font-semibold text-[#F4EEE5] tabular-nums">{{ Money::formatPhp($brew->price) }}</span>
                                </div>
                            </a>

                            <div class="space-y-2 px-1">
                                <div class="flex items-baseline justify-between gap-4">
                                    <h3 class="font-display text-2xl sm:text-3xl font-semibold text-[#F4EEE5] group-hover:text-[#D2B077] transition-colors">
                                        {{ $brew->name }}
                                    </h3>
                                </div>
                                @if(filled($brew->description))
                                    <p class="text-sm text-[#BFB4A5] leading-relaxed line-clamp-2">
                                        {{ $brew->description }}
                                    </p>
                                @endif
                            </div>
                        </article>
                    @endforeach
                </div>
            @endif
        </div>
    </section>

    <section id="the-craft" class="studio-anchor relative py-24 sm:py-32 border-t border-white/10 bg-[rgb(22,20,18)] overflow-hidden">
        <div class="max-w-7xl mx-auto px-5 sm:px-8 lg:px-12 grid grid-cols-1 lg:grid-cols-12 gap-12 lg:gap-16 items-center">
            <div data-reveal class="lg:col-span-6 space-y-8">
                <div class="space-y-4">
                    <span class="text-xs font-semibold uppercase tracking-[0.26em] text-[#D2B077]">The Craft</span>
                    <h2 class="font-display text-4xl sm:text-6xl font-semibold leading-[1.02] text-[#F4EEE5]">
                        Specialty single-origin beans.<br>Small-batch care.
                    </h2>
                </div>

                <p class="text-base text-[#BFB4A5] leading-relaxed max-w-xl">
                    At Artisan Brew Co., every beverage is pulled to order in our dark-stone coffee studio—balancing roasted espresso clarity, steamed milk sweetness, and seasonal infusions measured to the gram.
                </p>

                <div class="divide-y divide-white/10 border-y border-white/10">
                    <div class="py-5 flex items-baseline justify-between gap-6">
                        <span class="text-xs uppercase tracking-[0.2em] text-[#D2B077]">01 · Origin</span>
                        <span class="text-sm text-[#F4EEE5] text-right">Curated single-origin espresso &amp; ceremonial tea</span>
                    </div>
                    <div class="py-5 flex items-baseline justify-between gap-6">
                        <span class="text-xs uppercase tracking-[0.2em] text-[#D2B077]">02 · Preparation</span>
                        <span class="text-sm text-[#F4EEE5] text-right">Prepared hot, iced, or oat-infused per cup</span>
                    </div>
                    <div class="py-5 flex items-baseline justify-between gap-6">
                        <span class="text-xs uppercase tracking-[0.2em] text-[#D2B077]">03 · Pairing</span>
                        <span class="text-sm text-[#F4EEE5] text-right">Small-batch viennoiserie and house chocolate bakes</span>
                    </div>
                </div>
            </div>

            <div class="lg:col-span-6 relative">
                <div data-reveal class="relative grid grid-cols-12 gap-4 items-center">
                    <div class="col-span-10 col-start-3 aspect-[4/5] rounded-2xl overflow-hidden border border-white/10 bg-[#1E1A16]">
                        <img data-parallax-img
                             src="{{ $craftBrew?->imageUrl() ?? asset('images/cafe-interior-bar.jpg') }}"
                             alt="{{ $craftBrew ? $craftBrew->name . ' poured at Artisan Brew Co.' : 'Artisan Brew Co. espresso studio' }}"
                             loading="lazy"
                             class="w-full h-[112%] -mt-[6%] object-cover object-center">
                    </div>
                    <div class="col-span-6 col-start-1 -mt-28 sm:-mt-36 relative z-10 aspect-square rounded-2xl overflow-hidden border border-[#D2B077]/25 bg-[#161412] shadow-2xl">
                        <img src="{{ asset('images/cafe-interior-bar.jpg') }}"
                             alt="Warm lighting across the Artisan Brew Co. espresso counter"
                             loading="lazy"
                             class="w-full h-full object-cover object-center">
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section id="atmosphere" class="studio-anchor relative py-24 sm:py-32 border-t border-white/10 bg-[#1E1A16]/55">
        <div class="max-w-7xl mx-auto px-5 sm:px-8 lg:px-12 space-y-14">
            <div data-reveal class="max-w-2xl space-y-4">
                <span class="text-xs font-semibold uppercase tracking-[0.26em] text-[#D2B077]">A Moment at ABC</span>
                <h2 class="font-display text-4xl sm:text-5xl lg:text-6xl font-semibold leading-[1.04] text-[#F4EEE5]">
                    Charcoal stone, warm wood, and unhurried light.
                </h2>
                <p class="text-base text-[#BFB4A5] leading-relaxed">
                    Stepping inside means leaving the noise at the door—warm amber table lamps, quiet timber surfaces, and an unhurried rhythm built around conversation and craft coffee.
                </p>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-stretch">
                <figure data-reveal class="lg:col-span-7 group relative overflow-hidden rounded-2xl border border-white/10 bg-[#161412] min-h-[340px] sm:min-h-[460px]">
                    <img data-parallax-img
                         src="{{ asset('images/cafe-interior-lounge.jpg') }}"
                         alt="Artisan Brew Co. dining room with textured charcoal stone wall and timber seating"
                         loading="lazy"
                         class="w-full h-[112%] -mt-[6%] object-cover object-center">
                    <div class="pointer-events-none absolute inset-0 bg-gradient-to-t from-[rgb(22,20,18)]/85 via-[rgb(22,20,18)]/20 to-transparent"></div>
                    <figcaption class="absolute bottom-6 left-6 right-6 flex flex-col sm:flex-row sm:items-end justify-between gap-2">
                        <div>
                            <div class="text-[11px] uppercase tracking-[0.22em] text-[#D2B077]">Main Studio</div>
                            <div class="font-display text-2xl font-semibold text-[#F4EEE5]">Textured stone &amp; quiet timber tables</div>
                        </div>
                    </figcaption>
                </figure>

                <figure data-reveal class="lg:col-span-5 group relative overflow-hidden rounded-2xl border border-white/10 bg-[#161412] min-h-[340px] sm:min-h-[460px]">
                    <img data-parallax-img
                         src="{{ asset('images/cafe-interior-bar.jpg') }}"
                         alt="Artisan Brew Co. illuminated counter bar and elevated wood seating"
                         loading="lazy"
                         class="w-full h-[112%] -mt-[6%] object-cover object-center">
                    <div class="pointer-events-none absolute inset-0 bg-gradient-to-t from-[rgb(22,20,18)]/85 via-[rgb(22,20,18)]/20 to-transparent"></div>
                    <figcaption class="absolute bottom-6 left-6 right-6">
                        <div class="text-[11px] uppercase tracking-[0.22em] text-[#D2B077]">The Counter</div>
                        <div class="font-display text-2xl font-semibold text-[#F4EEE5]">Warm amber glow over the brew bar</div>
                    </figcaption>
                </figure>
            </div>
        </div>
    </section>

    <section id="visit" class="studio-anchor relative py-28 sm:py-36 border-t border-white/10 bg-[rgb(22,20,18)] overflow-hidden">
        <div class="pointer-events-none absolute inset-0 studio-atmosphere-glow opacity-75"></div>
        <div class="relative max-w-4xl mx-auto px-5 sm:px-8 text-center space-y-8" data-reveal>
            <span class="inline-block text-xs font-semibold uppercase tracking-[0.28em] text-[#D2B077]">Artisan Brew Co.</span>
            <h2 class="font-display text-5xl sm:text-6xl lg:text-7xl font-semibold leading-[0.98] text-[#F4EEE5]">
                Your next favorite cup awaits.
            </h2>
            <p class="text-base sm:text-lg text-[#BFB4A5] max-w-xl mx-auto leading-relaxed">
                Browse all {{ $menuCount }} active pours and bakes across {{ $categoryCount }} house categories—ready hot, iced, or customized to your taste at the counter.
            </p>
            <div class="flex flex-wrap items-center justify-center gap-4 pt-2">
                <a href="{{ route('menu.public') }}" class="studio-btn-primary">
                    Explore Full Menu
                </a>
                <a href="#hero" class="studio-btn-ghost">
                    Back to Top
                </a>
            </div>
        </div>
    </section>
</x-landing-shell>
