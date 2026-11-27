@php
    use App\Support\Money;
@endphp

<x-landing-shell title="{{ config('pos.brand_name', 'Artisan Brew Co.') }} — Contemporary Coffee Studio">
    <x-slot:intro>
        <div id="abc-intro-overlay"
             class="hidden fixed inset-0 z-[60] bg-[rgb(22,20,18)] overflow-hidden select-none"
             role="dialog"
             aria-modal="true"
             aria-label="Artisan Brew Co. opening sequence"
             data-cup-src="{{ asset('images/hero-coffee-cup.webp') }}"
             data-logo-src="{{ asset('images/abc-logo.png') }}"
             data-bean-srcs="{{ implode('|', [asset('images/intro-bean-1.webp'), asset('images/intro-bean-2.webp'), asset('images/intro-bean-3.webp')]) }}">
            <div class="pointer-events-none absolute inset-0 studio-atmosphere-glow opacity-85"></div>
            <div class="pointer-events-none absolute inset-0 studio-grain"></div>

            <button type="button"
                    id="abc-intro-skip"
                    class="fixed top-4 right-4 sm:top-7 sm:right-8 z-30 whitespace-nowrap inline-flex items-center gap-2 px-3.5 py-2 sm:px-4 rounded-full text-[11px] sm:text-xs font-medium uppercase tracking-[0.16em] text-[#F4EEE5] bg-white/[0.06] border border-white/20 hover:border-[#D2B077] hover:text-[#D2B077] transition-colors cursor-pointer">
                <span>Skip intro</span>
                <span aria-hidden="true">&rarr;</span>
            </button>

            <div id="intro-beans-layer" class="pointer-events-none absolute inset-0 overflow-hidden" aria-hidden="true">
                <img data-intro-bean data-depth="far" src="{{ asset('images/intro-bean-3.webp') }}" alt="" width="170" height="220" class="intro-bean intro-bean-blur-far w-10 sm:w-12 left-[14%]">
                <img data-intro-bean data-depth="mid" src="{{ asset('images/intro-bean-1.webp') }}" alt="" width="169" height="220" class="intro-bean intro-bean-blur-mid w-14 sm:w-16 left-[31%]">
                <img data-intro-bean data-depth="near" src="{{ asset('images/intro-bean-2.webp') }}" alt="" width="203" height="220" class="intro-bean w-16 sm:w-20 left-[49%]">
                <img data-intro-bean data-depth="far" src="{{ asset('images/intro-bean-1.webp') }}" alt="" width="169" height="220" class="intro-bean intro-bean-blur-far w-11 sm:w-13 left-[66%]">
                <img data-intro-bean data-depth="near" src="{{ asset('images/intro-bean-3.webp') }}" alt="" width="170" height="220" class="intro-bean w-14 sm:w-18 left-[78%]">
                <img data-intro-bean data-depth="mid" src="{{ asset('images/intro-bean-2.webp') }}" alt="" width="203" height="220" class="intro-bean intro-bean-blur-mid w-12 sm:w-15 left-[41%]">
            </div>

            <div id="intro-reveal-layer" class="relative z-10 h-full w-full flex flex-col items-center justify-center px-6 text-center">
                <div id="intro-cup-wrap" class="relative w-[min(78vw,430px)] sm:w-[min(64vw,470px)] aspect-[816/652] flex items-center justify-center opacity-0">
                    <div id="intro-cup-halo" class="pointer-events-none absolute inset-4 rounded-full bg-[radial-gradient(circle,rgba(210,176,119,0.22)_0%,rgba(90,56,28,0.12)_42%,rgba(22,20,18,0)_72%)] blur-2xl"></div>
                    <div id="intro-steam-layer" class="pointer-events-none absolute left-[24%] right-[32%] -top-[28%] h-[48%]" aria-hidden="true">
                        <svg viewBox="0 0 180 220" fill="none" class="w-full h-full overflow-visible">
                            <defs>
                                <linearGradient id="introSteamGrad" x1="0" y1="1" x2="0" y2="0">
                                    <stop offset="0%" stop-color="#F4EEE5" stop-opacity="0"/>
                                    <stop offset="32%" stop-color="#F4EEE5" stop-opacity="0.34"/>
                                    <stop offset="72%" stop-color="#D2B077" stop-opacity="0.14"/>
                                    <stop offset="100%" stop-color="#F4EEE5" stop-opacity="0"/>
                                </linearGradient>
                            </defs>
                            <path data-intro-steam d="M62 210 C42 155, 86 112, 60 56 C46 28, 62 12, 74 0" stroke="url(#introSteamGrad)" stroke-width="11" stroke-linecap="round" class="steam-ribbon"/>
                            <path data-intro-steam d="M96 215 C118 160, 74 118, 102 62 C116 32, 96 14, 88 2" stroke="url(#introSteamGrad)" stroke-width="13" stroke-linecap="round" class="steam-ribbon"/>
                            <path data-intro-steam d="M126 205 C106 154, 140 108, 114 54 C102 30, 112 16, 120 4" stroke="url(#introSteamGrad)" stroke-width="9" stroke-linecap="round" class="steam-ribbon"/>
                        </svg>
                    </div>
                    <img id="intro-cup-visual"
                         src="{{ asset('images/hero-coffee-cup.webp') }}"
                         alt="Artisan Brew Co. dark ceramic coffee cup and matching saucer"
                         width="816"
                         height="652"
                         class="relative z-10 w-full h-full object-contain drop-shadow-[0_28px_40px_rgba(0,0,0,0.72)]">
                </div>

                <div id="intro-brand-mark" class="mt-5 flex flex-col items-center gap-3 opacity-0">
                    <img src="{{ asset('images/abc-logo.png') }}"
                         alt="Artisan Brew Co."
                         width="52"
                         height="52"
                         class="w-12 h-12 sm:w-14 sm:h-14 object-contain border-0 bg-transparent">
                    <span class="text-[11px] uppercase tracking-[0.34em] text-[#D2B077]">Artisan Brew Co.</span>
                </div>
            </div>
        </div>
    </x-slot:intro>

    <section id="hero" class="relative min-h-[100svh] pt-28 pb-16 lg:pt-24 lg:pb-12 flex items-center overflow-hidden studio-hero-backdrop">
        <div class="studio-hero-light" aria-hidden="true"></div>
        <div class="studio-window-gobo" aria-hidden="true">
            <div class="studio-window-gobo-panes"></div>
        </div>
        <div class="studio-stone-surface" aria-hidden="true"></div>
        <div class="studio-left-calm" aria-hidden="true"></div>
        <div class="pointer-events-none absolute inset-0 studio-grain" aria-hidden="true"></div>

        <div class="relative z-10 max-w-7xl mx-auto w-full px-5 sm:px-8 lg:px-12 grid grid-cols-1 lg:grid-cols-12 items-center gap-12 lg:gap-10">
            <div class="lg:col-span-6 relative z-20 space-y-7">
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

            <div class="lg:col-span-6 relative z-10 flex items-center justify-center">
                <div id="hero-cup-stage"
                     class="relative w-full max-w-[360px] sm:max-w-[440px] lg:max-w-[560px] mx-auto pt-14 sm:pt-16 pb-10 px-2 sm:px-4">
                    <div id="hero-cup-entrance" class="relative aspect-[816/652] w-full">
                        <div id="hero-cup-shadow"
                             aria-hidden="true"
                             class="hero-cup-shadow pointer-events-none absolute left-[46%] -translate-x-1/2 -bottom-[5%] w-[82%] h-[18%] rounded-full"></div>
                        <div id="hero-cup-contact-shadow"
                             aria-hidden="true"
                             class="hero-cup-contact-shadow pointer-events-none absolute left-[48%] -translate-x-1/2 bottom-[0.5%] w-[56%] h-[9%] rounded-full"></div>

                        <div id="hero-cup-parallax" class="relative w-full h-full will-change-transform">
                            <div id="hero-cup-steam"
                                 aria-hidden="true"
                                 class="pointer-events-none absolute left-[24%] right-[32%] -top-[34%] h-[52%] z-20 will-change-transform">
                                <svg viewBox="0 0 220 240" fill="none" class="w-full h-full overflow-visible">
                                    <defs>
                                        <linearGradient id="heroSteamPrimary" x1="0" y1="1" x2="0" y2="0">
                                            <stop offset="0%" stop-color="#F4EEE5" stop-opacity="0"/>
                                            <stop offset="28%" stop-color="#F4EEE5" stop-opacity="0.32"/>
                                            <stop offset="68%" stop-color="#D2B077" stop-opacity="0.14"/>
                                            <stop offset="100%" stop-color="#F4EEE5" stop-opacity="0"/>
                                        </linearGradient>
                                        <linearGradient id="heroSteamSoft" x1="0" y1="1" x2="0" y2="0">
                                            <stop offset="0%" stop-color="#F4EEE5" stop-opacity="0"/>
                                            <stop offset="35%" stop-color="#F4EEE5" stop-opacity="0.22"/>
                                            <stop offset="100%" stop-color="#F4EEE5" stop-opacity="0"/>
                                        </linearGradient>
                                    </defs>
                                    <path data-hero-steam-wisp d="M64 232 C42 178, 88 128, 58 68 C44 40, 62 18, 76 4" stroke="url(#heroSteamPrimary)" stroke-width="11" stroke-linecap="round" class="steam-ribbon"/>
                                    <path data-hero-steam-wisp d="M106 236 C132 178, 82 126, 114 66 C128 38, 108 18, 96 2" stroke="url(#heroSteamPrimary)" stroke-width="14" stroke-linecap="round" class="steam-ribbon"/>
                                    <path data-hero-steam-wisp d="M144 226 C122 174, 160 124, 134 68 C122 44, 134 22, 142 8" stroke="url(#heroSteamSoft)" stroke-width="10" stroke-linecap="round" class="steam-ribbon"/>
                                    <path data-hero-steam-wisp d="M84 228 C102 182, 66 138, 90 84 C102 58, 88 32, 82 14" stroke="url(#heroSteamSoft)" stroke-width="8" stroke-linecap="round" class="steam-ribbon"/>
                                </svg>
                            </div>

                            <div id="hero-cup-float" class="relative z-10 w-full h-full will-change-transform">
                                <img id="hero-cup-img"
                                     src="{{ asset('images/hero-coffee-cup.webp') }}"
                                     alt="Warm-black stoneware ceramic coffee cup filled with espresso latte art on a matching saucer"
                                     width="816"
                                     height="652"
                                     fetchpriority="high"
                                     decoding="async"
                                     class="w-full h-full object-contain select-none">
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section id="signature-brews" class="studio-anchor studio-section-divider studio-section-brews relative py-24 sm:py-32 overflow-hidden">
        <div class="pointer-events-none absolute inset-0 studio-grain" aria-hidden="true"></div>
        <div class="relative z-10 max-w-7xl mx-auto px-5 sm:px-8 lg:px-12 space-y-16">
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

    <section id="the-craft" class="studio-anchor studio-section-divider studio-section-craft relative py-24 sm:py-32 overflow-hidden">
        <div class="pointer-events-none absolute inset-0 studio-grain" aria-hidden="true"></div>
        <div class="relative z-10 max-w-7xl mx-auto px-5 sm:px-8 lg:px-12 grid grid-cols-1 lg:grid-cols-12 gap-12 lg:gap-16 items-center">
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
                             src="{{ $craftBrew?->imageUrl() ?? asset('images/moment-counter-bar.webp') }}"
                             alt="{{ $craftBrew ? $craftBrew->name . ' poured at Artisan Brew Co.' : 'Artisan Brew Co. espresso studio' }}"
                             loading="lazy"
                             decoding="async"
                             class="w-full h-[112%] -mt-[6%] object-cover object-center">
                    </div>
                    <div class="col-span-6 col-start-1 -mt-28 sm:-mt-36 relative z-10 aspect-square rounded-2xl overflow-hidden border border-[#D2B077]/25 bg-[#161412] shadow-2xl">
                        <img src="{{ asset('images/moment-counter-bar.webp') }}"
                             alt="Matte-black espresso machine and amber pendant lamps on the Artisan Brew Co. bar"
                             width="876"
                             height="1400"
                             loading="lazy"
                             decoding="async"
                             class="w-full h-full object-cover object-[52%_46%]">
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section id="atmosphere" class="studio-anchor studio-section-divider studio-section-atmosphere relative py-24 sm:py-32 overflow-hidden">
        <div class="pointer-events-none absolute inset-0 studio-grain" aria-hidden="true"></div>
        <div class="relative z-10 max-w-7xl mx-auto px-5 sm:px-8 lg:px-12 space-y-14">
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
                <figure data-reveal class="lg:col-span-7 group relative overflow-hidden rounded-2xl border border-white/10 bg-[#161412] aspect-[4/5] sm:aspect-[4/4] lg:aspect-auto lg:h-[520px]">
                    <img data-parallax-img
                         src="{{ asset('images/moment-main-studio.webp') }}"
                         alt="Quiet walnut dining tables, textured charcoal stone wall, brass lamps, and indoor greenery at Artisan Brew Co."
                         width="1024"
                         height="1280"
                         loading="lazy"
                         decoding="async"
                         class="w-full h-[112%] -mt-[6%] object-cover object-[45%_54%]">
                    <div class="pointer-events-none absolute inset-x-0 bottom-0 h-44 bg-gradient-to-t from-[rgb(16,14,13)]/90 via-[rgb(18,16,14)]/45 to-transparent"></div>
                    <figcaption class="absolute bottom-6 left-6 right-6 flex flex-col sm:flex-row sm:items-end justify-between gap-2">
                        <div>
                            <div class="text-[11px] uppercase tracking-[0.22em] text-[#D2B077]">Main Studio</div>
                            <div class="font-display text-2xl font-semibold text-[#F4EEE5]">Textured stone &amp; quiet timber tables</div>
                        </div>
                    </figcaption>
                </figure>

                <figure data-reveal class="lg:col-span-5 group relative overflow-hidden rounded-2xl border border-white/10 bg-[#161412] aspect-[4/5] sm:aspect-[4/4] lg:aspect-auto lg:h-[520px]">
                    <img data-parallax-img
                         src="{{ asset('images/moment-counter-bar.webp') }}"
                         alt="Matte-black espresso machine, dual coffee grinders, and warm amber pendant lights at the Artisan Brew Co. counter"
                         width="876"
                         height="1400"
                         loading="lazy"
                         decoding="async"
                         class="w-full h-[112%] -mt-[6%] object-cover object-[52%_46%]">
                    <div class="pointer-events-none absolute inset-x-0 bottom-0 h-44 bg-gradient-to-t from-[rgb(16,14,13)]/90 via-[rgb(18,16,14)]/45 to-transparent"></div>
                    <figcaption class="absolute bottom-6 left-6 right-6">
                        <div class="text-[11px] uppercase tracking-[0.22em] text-[#D2B077]">The Counter</div>
                        <div class="font-display text-2xl font-semibold text-[#F4EEE5]">Warm amber glow over the brew bar</div>
                    </figcaption>
                </figure>
            </div>
        </div>
    </section>

    <section id="visit" class="studio-anchor studio-section-divider studio-section-visit relative py-28 sm:py-36 overflow-hidden">
        <div class="pointer-events-none absolute inset-0 studio-grain" aria-hidden="true"></div>
        <div class="relative z-10 max-w-4xl mx-auto px-5 sm:px-8 text-center space-y-8" data-reveal>
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
