@props([
    'title' => 'Artisan Brew Co.',
    'description' => 'Exceptional coffee, thoughtful craft, and a moment worth making time for. Single-origin espresso, ceremonial matcha, and house-baked pastries.',
    'ogImage' => null,
])
@php
    $ogImageUrl = $ogImage ?: asset('images/og-preview.jpg');
    $canonicalUrl = url()->current();
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }}</title>
    <meta name="description" content="{{ $description }}">
    <meta name="theme-color" content="#161412">
    <link rel="canonical" href="{{ $canonicalUrl }}">
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="{{ config('pos.brand_name', 'Artisan Brew Co.') }}">
    <meta property="og:title" content="{{ $title }}">
    <meta property="og:description" content="{{ $description }}">
    <meta property="og:url" content="{{ $canonicalUrl }}">
    <meta property="og:image" content="{{ $ogImageUrl }}">
    <meta property="og:image:secure_url" content="{{ $ogImageUrl }}">
    <meta property="og:image:type" content="image/jpeg">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    <meta property="og:image:alt" content="Artisan Brew Co. — Contemporary Coffee Studio">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="{{ $title }}">
    <meta name="twitter:description" content="{{ $description }}">
    <meta name="twitter:image" content="{{ $ogImageUrl }}">
    <link rel="icon" type="image/png" href="{{ asset('favicon.png') }}">
    @vite(['resources/css/landing.css', 'resources/js/landing.js'])
</head>
<body class="bg-[rgb(22,20,18)] text-[#F4EEE5] antialiased selection:bg-[#D2B077]/30 selection:text-[#F4EEE5]">
    @isset($intro)
        {{ $intro }}
    @endisset

    <a href="#main-content" data-page-shell class="sr-only focus:not-sr-only focus:fixed focus:top-4 focus:left-4 focus:z-50 focus:px-4 focus:py-2 focus:rounded-full focus:bg-[#D2B077] focus:text-[#161412] focus:text-sm focus:font-semibold">
        Skip to content
    </a>

    <header id="landing-nav" data-page-shell class="landing-nav fixed top-0 inset-x-0 z-40">
        <div class="max-w-7xl mx-auto px-5 sm:px-8 lg:px-12 h-20 flex items-center justify-between gap-6">
            <a href="{{ route('home') }}" class="flex items-center gap-3 shrink-0 group">
                <img src="{{ asset('images/abc-logo.png') }}"
                     alt="Artisan Brew Co."
                     width="40"
                     height="40"
                     class="w-10 h-10 object-contain border-0 bg-transparent shadow-none transition-transform duration-300 group-hover:scale-105">
                <div class="leading-none">
                    <span class="block text-[10px] uppercase tracking-[0.24em] text-[#BFB4A5]">Coffee Studio</span>
                    <span class="font-display text-xl font-semibold tracking-tight text-[#F4EEE5]">Artisan Brew Co.</span>
                </div>
            </a>

            <nav aria-label="Primary" class="hidden md:flex items-center gap-8 text-sm text-[#BFB4A5]">
                <a href="{{ route('home') }}#signature-brews" class="hover:text-[#F4EEE5] transition-colors">Signature Brews</a>
                <a href="{{ route('home') }}#the-craft" class="hover:text-[#F4EEE5] transition-colors">The Craft</a>
                <a href="{{ route('home') }}#atmosphere" class="hover:text-[#F4EEE5] transition-colors">Atmosphere</a>
                <a href="{{ route('home') }}#contact" class="hover:text-[#F4EEE5] transition-colors">Contact</a>
            </nav>

            <div class="hidden md:flex items-center gap-4">
                @auth
                    <a href="{{ route(auth()->user()->isAdmin() ? 'dashboard' : 'pos.index') }}" class="text-xs tracking-[0.06em] uppercase text-[#BFB4A5] hover:text-[#D2B077] transition-colors px-2 py-1.5">
                        Staff Console
                    </a>
                @else
                    <a href="{{ route('login') }}" class="text-xs tracking-[0.06em] uppercase text-[#BFB4A5] hover:text-[#D2B077] transition-colors px-2 py-1.5">
                        Staff Sign In
                    </a>
                @endauth
                <a href="{{ route('menu.public') }}" class="studio-btn-primary py-2.5 px-5 text-xs uppercase tracking-[0.12em]">
                    Explore Menu
                </a>
            </div>

            <button type="button"
                    id="landing-menu-open"
                    class="md:hidden inline-flex items-center justify-center w-10 h-10 rounded-full border border-white/15 bg-white/5 text-[#F4EEE5] hover:border-[#D2B077]/50"
                    aria-label="Open navigation menu"
                    aria-controls="landing-mobile-drawer"
                    aria-expanded="false">
                <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                    <path d="M4 7h16M4 12h16M4 17h16" stroke-linecap="round"/>
                </svg>
            </button>
        </div>
    </header>

    <div id="landing-mobile-drawer" data-page-shell class="hidden fixed inset-0 z-50 md:hidden" role="dialog" aria-modal="true" aria-label="Site navigation">
        <div class="fixed inset-0 bg-black/75 backdrop-blur-sm" data-landing-menu-close></div>
        <div class="fixed inset-y-0 right-0 w-80 max-w-[85vw] bg-[#161412] border-l border-white/10 p-6 flex flex-col justify-between">
            <div class="space-y-8">
                <div class="flex items-center justify-between">
                    <a href="{{ route('home') }}" class="flex items-center gap-2.5">
                        <img src="{{ asset('images/abc-logo.png') }}" alt="Artisan Brew Co." width="36" height="36" class="w-9 h-9 object-contain border-0">
                        <span class="font-display text-lg font-semibold text-[#F4EEE5]">Artisan Brew Co.</span>
                    </a>
                    <button type="button" data-landing-menu-close class="w-9 h-9 rounded-full border border-white/15 text-[#BFB4A5] hover:text-[#F4EEE5] inline-flex items-center justify-center" aria-label="Close navigation menu">
                        &times;
                    </button>
                </div>

                <nav aria-label="Mobile" class="flex flex-col gap-4 text-lg font-display">
                    <a href="{{ route('home') }}#signature-brews" class="py-2 border-b border-white/10 text-[#F4EEE5] hover:text-[#D2B077]">Signature Brews</a>
                    <a href="{{ route('home') }}#the-craft" class="py-2 border-b border-white/10 text-[#F4EEE5] hover:text-[#D2B077]">The Craft</a>
                    <a href="{{ route('home') }}#atmosphere" class="py-2 border-b border-white/10 text-[#F4EEE5] hover:text-[#D2B077]">Atmosphere</a>
                    <a href="{{ route('home') }}#contact" class="py-2 border-b border-white/10 text-[#F4EEE5] hover:text-[#D2B077]">Contact</a>
                    <a href="{{ route('menu.public') }}" class="py-2 border-b border-white/10 text-[#D2B077]">Full Menu</a>
                </nav>
            </div>

            <div class="space-y-3 pt-6 border-t border-white/10">
                <a href="{{ route('menu.public') }}" class="studio-btn-primary w-full text-center">Explore the Menu</a>
                @auth
                    <a href="{{ route(auth()->user()->isAdmin() ? 'dashboard' : 'pos.index') }}" class="studio-btn-ghost w-full text-center text-xs uppercase tracking-[0.1em]">Staff Console</a>
                @else
                    <a href="{{ route('login') }}" class="studio-btn-ghost w-full text-center text-xs uppercase tracking-[0.1em]">Staff Sign In</a>
                @endauth
            </div>
        </div>
    </div>

    <main id="main-content" data-page-shell>
        {{ $slot }}
    </main>

    <footer data-page-shell class="border-t border-white/10 bg-[#141210]">
        <div class="max-w-7xl mx-auto px-5 sm:px-8 lg:px-12 py-12 lg:py-16">
            <div class="grid grid-cols-1 md:grid-cols-12 gap-10 items-start">
                <div class="md:col-span-5 space-y-4">
                    <a href="{{ route('home') }}" class="inline-flex items-center gap-3.5">
                        <img src="{{ asset('images/abc-logo.png') }}" alt="Artisan Brew Co." width="44" height="44" class="w-11 h-11 object-contain border-0 bg-transparent">
                        <div>
                            <div class="font-display text-2xl font-semibold text-[#F4EEE5]">{{ config('pos.brand_name', 'Artisan Brew Co.') }}</div>
                            <div class="text-[11px] uppercase tracking-[0.22em] text-[#BFB4A5]">{{ config('pos.brand_short', 'ABC') }} · Contemporary Coffee Studio</div>
                        </div>
                    </a>
                    <p class="text-sm text-[#BFB4A5] max-w-sm leading-relaxed">
                        Single-origin espresso, ceremonial matcha, cold-steeped chocolate, and house-baked pastries crafted daily.
                    </p>
                </div>

                <div class="md:col-span-4 space-y-3">
                    <div class="text-xs uppercase tracking-[0.2em] text-[#D2B077]">Studio Navigation</div>
                    <ul class="space-y-2 text-sm text-[#BFB4A5]">
                        <li><a href="{{ route('home') }}#signature-brews" class="hover:text-[#F4EEE5] transition-colors">Signature Brews</a></li>
                        <li><a href="{{ route('home') }}#the-craft" class="hover:text-[#F4EEE5] transition-colors">The Craft</a></li>
                        <li><a href="{{ route('home') }}#atmosphere" class="hover:text-[#F4EEE5] transition-colors">A Moment at ABC</a></li>
                        <li><a href="{{ route('menu.public') }}" class="hover:text-[#F4EEE5] transition-colors">Full Menu</a></li>
                    </ul>
                </div>

                <div class="md:col-span-3 space-y-3">
                    <div class="text-xs uppercase tracking-[0.2em] text-[#D2B077]">Barista Counter</div>
                    <p class="text-xs text-[#BFB4A5] leading-relaxed">
                        Asia/Manila counter clock · All menu pricing listed in PHP (₱).
                    </p>
                    <div class="pt-1">
                        @auth
                            <a href="{{ route(auth()->user()->isAdmin() ? 'dashboard' : 'pos.index') }}" class="inline-flex items-center gap-2 text-xs text-[#BFB4A5] hover:text-[#F4EEE5] underline underline-offset-4">
                                Open Staff Console
                            </a>
                        @else
                            <a href="{{ route('login') }}" class="inline-flex items-center gap-2 text-xs text-[#BFB4A5] hover:text-[#F4EEE5] underline underline-offset-4">
                                Staff Sign In
                            </a>
                        @endauth
                    </div>
                </div>
            </div>

            <div class="mt-10 pt-6 border-t border-white/10 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 text-xs text-[#BFB4A5]/80">
                <span>{{ config('pos.brand_name', 'Artisan Brew Co.') }}</span>
                <span>{{ config('pos.receipt_footer', 'Thank you for brewing with Artisan Brew Co.') }}</span>
            </div>
        </div>
    </footer>
</body>
</html>
