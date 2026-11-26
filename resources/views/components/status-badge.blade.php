@props(['tone' => 'neutral'])

@php
$classes = match ($tone) {
        'success' => 'bg-[rgba(88,185,135,0.14)] text-[#58B987] border-[#58B987]/30',
        'warning' => 'bg-[rgba(224,164,88,0.14)] text-[#E0A458] border-[#E0A458]/30',
        'danger' => 'bg-[rgba(230,119,125,0.14)] text-[#E6777D] border-[#E6777D]/30',
        'accent' => 'bg-[rgba(198,166,107,0.14)] text-[#C6A66B] border-[#C6A66B]/30',
        default => 'bg-white/[0.06] text-[#BDB5AA] border-white/10',
    };
@endphp

<span {{ $attributes->merge(['class' => "inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium border {$classes}"]) }}>
    {{ $slot }}
</span>
