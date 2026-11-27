<x-landing-shell
    title="About the Developers — RootKitCats | {{ config('pos.brand_name', 'Artisan Brew Co.') }}"
    description="Meet RootKitCats, the engineering team behind the Artisan Brew Co. (ABC) contemporary coffee studio and POS platform.">

    <section class="relative pt-32 pb-16 sm:pb-20 overflow-hidden studio-hero-backdrop">
        <div class="pointer-events-none absolute inset-0 studio-atmosphere-glow opacity-85" aria-hidden="true"></div>
        <div class="pointer-events-none absolute inset-0 studio-grain" aria-hidden="true"></div>

        <div class="relative z-10 max-w-6xl mx-auto px-5 sm:px-8 lg:px-12">
            <a href="{{ route('home') }}" class="inline-flex items-center gap-2 text-xs uppercase tracking-[0.22em] text-[#BFB4A5] hover:text-[#D2B077] transition-colors mb-8">
                <span aria-hidden="true">&larr;</span>
                <span>Back to Coffee Studio</span>
            </a>

            <div class="grid grid-cols-1 lg:grid-cols-12 gap-10 lg:gap-12 items-center">
                <div class="lg:col-span-7 space-y-5" data-reveal>
                    <div class="inline-flex items-center gap-3">
                        <span class="h-px w-8 bg-[#C084FC]/70"></span>
                        <span class="text-xs font-semibold uppercase tracking-[0.26em] text-[#C084FC]">Engineering Collective</span>
                    </div>
                    <h1 class="font-display text-5xl sm:text-6xl lg:text-7xl font-semibold leading-[0.98] text-[#F4EEE5]">
                        Meet <span class="text-[#D2B077]">RootKitCats</span>
                    </h1>
                    <p class="text-base sm:text-lg text-[#BFB4A5] leading-relaxed max-w-2xl">
                        We are the four-person developer team behind <strong class="text-[#F4EEE5]">Artisan Brew Co. (ABC)</strong> — crafting a tactile coffee-studio experience paired with a reliable, atomic Point-of-Sale, inventory, analytics, and Brevo email platform.
                    </p>
                    <div class="flex flex-wrap items-center gap-3 pt-2">
                        <a href="https://github.com/kcyKael/Artisan-Brew-Co"
                           target="_blank"
                           rel="noopener noreferrer"
                           class="studio-btn-primary">
                            <span>View Repository on GitHub</span>
                        </a>
                        <a href="{{ route('menu.public') }}" class="studio-btn-ghost">
                            Explore Studio Menu
                        </a>
                    </div>
                </div>

                <div class="lg:col-span-5 flex justify-center lg:justify-end" data-reveal>
                    <div class="relative w-64 h-64 sm:w-80 sm:h-80 flex flex-col items-center justify-center">
                        <div class="rkc-logo-aura pointer-events-none absolute -inset-6 rounded-full bg-[radial-gradient(circle,rgba(168,85,247,0.34)_0%,rgba(192,132,252,0.16)_38%,rgba(210,176,119,0.06)_60%,transparent_76%)] blur-2xl" aria-hidden="true"></div>
                        <img src="{{ asset('images/rootkitcats-logo.png') }}"
                             alt="RootKitCats team emblem — purple-accented black cyber cat"
                             width="512"
                             height="512"
                             class="rkc-logo-float relative z-10 w-52 h-52 sm:w-64 sm:h-64 object-contain">
                        <span class="relative z-10 mt-2 text-[11px] uppercase tracking-[0.32em] text-[#D8B4FE]">RootKitCats</span>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="studio-section-divider relative py-20 sm:py-28 bg-[rgb(22,20,18)] overflow-hidden">
        <div class="pointer-events-none absolute inset-0 studio-grain" aria-hidden="true"></div>

        <div class="relative z-10 max-w-6xl mx-auto px-5 sm:px-8 lg:px-12 space-y-12">
            <div class="max-w-2xl space-y-3" data-reveal>
                <span class="inline-block text-xs font-semibold uppercase tracking-[0.26em] text-[#D2B077]">The Builders</span>
                <h2 class="font-display text-4xl sm:text-5xl font-semibold text-[#F4EEE5] leading-tight">
                    Four roles, one cohesive studio build.
                </h2>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 lg:gap-8">
                @foreach($developers as $dev)
                    <article class="abc-panel p-6 sm:p-8 flex flex-col justify-between gap-6 border border-white/10 hover:border-[#C084FC]/40 transition-colors" data-reveal>
                        <div class="space-y-4">
                            <div class="flex flex-wrap items-center justify-between gap-3">
                                <span class="inline-flex items-center px-3 py-1 rounded-full text-[11px] font-semibold uppercase tracking-[0.16em] bg-[#C084FC]/12 text-[#D8B4FE] border border-[#C084FC]/30">
                                    {{ $dev['badge'] }}
                                </span>
                                <span class="text-xs font-mono text-[#BFB4A5]">&#64;{{ $dev['handle'] }}</span>
                            </div>

                            <div class="space-y-1.5">
                                <h3 class="font-display text-3xl font-semibold text-[#F4EEE5]">{{ $dev['name'] }}</h3>
                                <p class="text-sm font-medium text-[#D2B077]">{{ $dev['role'] }}</p>
                            </div>

                            <p class="text-sm text-[#BFB4A5] leading-relaxed">
                                {{ $dev['focus'] }}
                            </p>
                        </div>

                        <div class="pt-4 border-t border-white/10 flex items-center justify-between gap-4">
                            <a href="{{ $dev['github'] }}"
                               target="_blank"
                               rel="noopener noreferrer"
                               class="inline-flex items-center gap-2 text-xs font-semibold uppercase tracking-[0.14em] text-[#F4EEE5] hover:text-[#D2B077] transition-colors">
                                <svg class="w-4 h-4 text-[#C084FC]" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                                    <path fill-rule="evenodd" clip-rule="evenodd" d="M12 2C6.477 2 2 6.484 2 12.017c0 4.425 2.865 8.18 6.839 9.504.5.092.682-.217.682-.483 0-.237-.008-.868-.013-1.703-2.782.605-3.369-1.343-3.369-1.343-.454-1.158-1.11-1.466-1.11-1.466-.908-.62.069-.608.069-.608 1.003.07 1.53 1.032 1.53 1.032.892 1.53 2.341 1.088 2.91.832.092-.647.35-1.088.636-1.338-2.22-.253-4.555-1.113-4.555-4.951 0-1.093.39-1.988 1.029-2.688-.103-.253-.446-1.272.098-2.65 0 0 .84-.27 2.75 1.026A9.564 9.564 0 0112 6.844c.85.004 1.705.115 2.504.337 1.909-1.296 2.747-1.027 2.747-1.027.546 1.379.202 2.398.1 2.651.64.7 1.028 1.595 1.028 2.688 0 3.848-2.339 4.695-4.566 4.943.359.309.678.92.678 1.855 0 1.338-.012 2.419-.012 2.747 0 .268.18.58.688.482A10.019 10.019 0 0022 12.017C22 6.484 17.522 2 12 2z"/>
                                </svg>
                                <span>GitHub Profile</span>
                                <span aria-hidden="true">&rarr;</span>
                            </a>
                            <span class="text-[11px] uppercase tracking-[0.16em] text-[#BFB4A5]/70">RootKitCats</span>
                        </div>
                    </article>
                @endforeach
            </div>
        </div>
    </section>

    <section class="studio-section-divider relative py-16 sm:py-24 bg-[#141210] overflow-hidden">
        <div class="max-w-6xl mx-auto px-5 sm:px-8 lg:px-12 grid grid-cols-1 md:grid-cols-3 gap-6" data-reveal>
            <div class="abc-panel p-6 space-y-2">
                <div class="text-xs uppercase tracking-[0.2em] text-[#D2B077]">Backend &amp; Persistence</div>
                <div class="font-display text-2xl font-semibold text-[#F4EEE5]">Laravel 12 &amp; InnoDB</div>
                <p class="text-xs text-[#BFB4A5] leading-relaxed">
                    Transactional checkout locking, immutable order snapshots, manager-override voiding, and UTC-to-Manila reporting.
                </p>
            </div>
            <div class="abc-panel p-6 space-y-2">
                <div class="text-xs uppercase tracking-[0.2em] text-[#D2B077]">Design &amp; Motion</div>
                <div class="font-display text-2xl font-semibold text-[#F4EEE5]">Tailwind v4 &amp; GSAP</div>
                <p class="text-xs text-[#BFB4A5] leading-relaxed">
                    Self-hosted typography, warm studio lighting, scroll-triggered reveals, and strict CSP-compliant scripts.
                </p>
            </div>
            <div class="abc-panel p-6 space-y-2">
                <div class="text-xs uppercase tracking-[0.2em] text-[#D2B077]">Communications</div>
                <div class="font-display text-2xl font-semibold text-[#F4EEE5]">Brevo HTTPS Pipeline</div>
                <p class="text-xs text-[#BFB4A5] leading-relaxed">
                    Queued transactional receipts, staff invitations, stock alerts, daily CSV summaries, and signed webhook sync.
                </p>
            </div>
        </div>
    </section>
</x-landing-shell>
