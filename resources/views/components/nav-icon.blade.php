@props(['name'])

@switch($name)
    @case('chart')
        <svg class="w-4 h-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75"><path d="M4 19V5M4 19h16M8 15l4-4 3 3 5-6" stroke-linecap="round" stroke-linejoin="round"/></svg>
        @break
    @case('pos')
        <svg class="w-4 h-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75"><rect x="3" y="4" width="18" height="12" rx="2"/><path d="M7 20h10M12 16v4" stroke-linecap="round"/></svg>
        @break
    @case('plus-circle')
        <svg class="w-4 h-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75"><circle cx="12" cy="12" r="9"/><path d="M12 8v8M8 12h8" stroke-linecap="round"/></svg>
        @break
    @case('receipt')
        <svg class="w-4 h-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75"><path d="M6 3h12v18l-3-2-3 2-3-2-3 2V3z"/><path d="M9 8h6M9 12h6M9 16h4" stroke-linecap="round"/></svg>
        @break
    @case('coffee')
        <svg class="w-4 h-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75"><path d="M17 8h1a3 3 0 010 6h-1M4 8h13v6a5 5 0 01-5 5H9a5 5 0 01-5-5V8z"/><path d="M8 3v2M12 3v2" stroke-linecap="round"/></svg>
        @break
    @case('users')
        <svg class="w-4 h-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75"><circle cx="9" cy="8" r="3.5"/><path d="M3 19a6 6 0 0112 0"/><circle cx="17" cy="9" r="2.5"/><path d="M16 14a5 5 0 015 5" stroke-linecap="round"/></svg>
        @break
    @case('badge')
        <svg class="w-4 h-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75"><rect x="4" y="4" width="16" height="16" rx="3"/><circle cx="12" cy="10" r="2.5"/><path d="M7.5 17.5a4.5 4.5 0 019 0" stroke-linecap="round"/></svg>
        @break
    @case('sliders')
        <svg class="w-4 h-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75"><path d="M4 7h10M18 7h2M4 17h4M12 17h8M4 12h16" stroke-linecap="round"/><circle cx="16" cy="7" r="2"/><circle cx="10" cy="17" r="2"/></svg>
        @break
    @default
        <svg class="w-4 h-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75"><circle cx="12" cy="8" r="4"/><path d="M5 20a7 7 0 0114 0" stroke-linecap="round"/></svg>
@endswitch
