@props(['compact' => false])

<div class="flex items-center gap-3">
    <svg class="w-9 h-9 shrink-0" viewBox="0 0 64 64" fill="none" aria-hidden="true">
        <rect width="64" height="64" rx="14" fill="#1D1B18"/>
        <rect x="2" y="2" width="60" height="60" rx="12" stroke="#D2B077" stroke-opacity="0.48" stroke-width="1.5"/>
        <path d="M18 44L27.5 19H31L40.5 44M21.2 35.8H37.3" stroke="#D2B077" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"/>
        <path d="M44.5 22.5C47.8 22.5 50 24.5 50 27.5C50 30.2 48.2 31.8 45.8 32.3C48.6 32.8 50.5 34.8 50.5 37.8C50.5 41.4 47.8 43.8 43.8 43.8H40" stroke="#F4EEE5" stroke-width="2" stroke-linecap="round"/>
    </svg>
    @unless($compact)
        <div class="leading-tight">
            <div class="text-[10px] uppercase tracking-[0.18em] text-[#BFB4A5]">Workspace</div>
            <div class="font-display text-xl font-semibold tracking-tight text-[#F4EEE5]">Artisan Brew Co.</div>
        </div>
    @endunless
</div>
