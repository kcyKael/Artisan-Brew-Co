@props(['tone' => 'neutral'])

@php
$classes = match ($tone) {
        'success' => 'bg-[rgba(156,175,148,0.14)] text-[#9CAF94] border-[#9CAF94]/35',
        'warning' => 'bg-[rgba(224,168,91,0.14)] text-[#E0A85B] border-[#E0A85B]/35',
        'danger' => 'bg-[rgba(224,122,122,0.14)] text-[#E07A7A] border-[#E07A7A]/35',
        'accent' => 'bg-[rgba(210,176,119,0.14)] text-[#D2B077] border-[#D2B077]/35',
        default => 'bg-white/[0.06] text-[#BFB4A5] border-white/10',
    };
@endphp

<span {{ $attributes->merge(['class' => "inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium border {$classes}"]) }}>
    {{ $slot }}
</span>
