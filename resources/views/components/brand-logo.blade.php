@props(['compact' => false])

<div class="flex items-center gap-3">
    <img src="{{ asset('images/abc-logo.png') }}"
         alt="Artisan Brew Co. emblem"
         class="{{ $compact ? 'w-9 h-9' : 'w-11 h-11' }} shrink-0 object-contain border-0 rounded-none bg-transparent shadow-none">
    @unless($compact)
        <div class="leading-tight">
            <div class="text-[10px] uppercase tracking-[0.18em] text-[#BFB4A5]">Workspace</div>
            <div class="font-display text-xl font-semibold tracking-tight text-[#F4EEE5]">Artisan Brew Co.</div>
        </div>
    @endunless
</div>
