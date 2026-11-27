@props(['product', 'size' => 'card', 'alt' => null])

@php
    $sizeClasses = match ($size) {
        'thumb' => 'w-12 h-9 rounded-md',
        'sm' => 'w-10 h-10 rounded-lg',
        'preview' => 'w-32 h-24 rounded-lg',
        default => 'w-full h-full',
    };
    $iconClasses = match ($size) {
        'thumb', 'sm' => 'w-4 h-4',
        'preview' => 'w-8 h-8',
        default => 'w-10 h-10',
    };
    $imageUrl = $product?->imageUrl();
    $altText = $alt ?? ($product?->name ?? 'Product');
@endphp

<div {{ $attributes->class(['overflow-hidden shrink-0', $sizeClasses]) }}>
    @if($imageUrl)
        <img src="{{ $imageUrl }}" alt="{{ $altText }}" loading="lazy" class="h-full w-full object-cover" />
    @else
        <div data-product-image-placeholder class="h-full w-full flex items-center justify-center bg-gradient-to-br from-[#28241F] to-[#1A1714] border border-white/10 text-[#D2B077]/80" aria-hidden="true">
            <svg class="{{ $iconClasses }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                <path d="M17 8h1a4 4 0 1 1 0 8h-1" />
                <path d="M3 8h14v9a4 4 0 0 1-4 4H7a4 4 0 0 1-4-4Z" />
                <line x1="6" y1="2" x2="6" y2="4" />
                <line x1="10" y1="2" x2="10" y2="4" />
                <line x1="14" y1="2" x2="14" y2="4" />
            </svg>
        </div>
    @endif
</div>
