@props(['title' => 'Artisan Brew Co.'])

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title }} — Artisan Brew Co.</title>
    <link rel="icon" type="image/png" href="{{ asset('favicon.png') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('scripts')
</head>
<body class="bg-[#111110] text-[#F4EEE5] antialiased">
@php
$authUser = auth()->user();
    $isAdmin = $authUser?->isAdmin() ?? false;
    $alertCount = $lowStockAlertCount ?? 0;

    $navGroups = array_values(array_filter([
        [
            'label' => 'Operations',
            'items' => array_values(array_filter([
                $isAdmin ? ['route' => 'dashboard', 'pattern' => 'dashboard*', 'label' => 'Dashboard', 'icon' => 'chart', 'badge' => null] : null,
                ['route' => 'pos.index', 'pattern' => 'pos*', 'label' => 'New Sale / POS', 'icon' => 'pos', 'badge' => null],
                ['route' => 'sales.record', 'pattern' => 'sales/record*', 'label' => 'Record Sale', 'icon' => 'plus-circle', 'badge' => null],
                $isAdmin ? ['route' => 'products.index', 'pattern' => 'products*', 'label' => 'Menu & Stock', 'icon' => 'coffee', 'badge' => $alertCount > 0 ? $alertCount : null] : null,
                ['route' => 'customers.index', 'pattern' => 'customers*', 'label' => 'Customers', 'icon' => 'users', 'badge' => null],
            ])),
        ],
        [
            'label' => 'Reports',
            'items' => [
                ['route' => 'sales.index', 'pattern' => ['sales', 'orders*'], 'label' => 'Sales History', 'icon' => 'receipt', 'badge' => null],
            ],
        ],
        [
            'label' => 'System',
            'items' => array_values(array_filter([
                $isAdmin ? ['route' => 'staff.index', 'pattern' => 'staff*', 'label' => 'Staff', 'icon' => 'badge', 'badge' => null] : null,
                $isAdmin ? ['route' => 'menu-config.index', 'pattern' => 'menu-config*', 'label' => 'Menu Configuration', 'icon' => 'sliders', 'badge' => null] : null,
                $isAdmin ? ['route' => 'admin.email.settings', 'pattern' => 'admin/email*', 'label' => 'Email Center', 'icon' => 'mail', 'badge' => null] : null,
                ['route' => 'profile.edit', 'pattern' => 'profile*', 'label' => 'Profile', 'icon' => 'user', 'badge' => null],
            ])),
        ],
    ]));

    $manilaNow = \App\Support\BusinessTime::nowManila()->format('M d, Y · g:i A') . ' PHT';
@endphp

<div class="abc-ambient-backdrop no-print" aria-hidden="true"></div>

<div class="relative z-10 min-h-screen flex flex-col lg:flex-row">
    <aside class="no-print hidden lg:flex lg:sticky lg:top-0 lg:h-screen lg:w-64 lg:shrink-0 lg:flex-col bg-[#161412] border-r border-white/10">
        <div class="px-5 py-4 border-b border-white/10">
            <a href="{{ $isAdmin ? route('dashboard') : route('pos.index') }}" class="block rounded-lg">
                <x-brand-logo />
            </a>
        </div>

        <nav class="flex-1 px-3 py-4 space-y-5 overflow-y-auto" aria-label="Main navigation">
            @foreach($navGroups as $group)
                <div class="space-y-1">
                    <div class="px-3 pb-1 text-[10px] font-semibold uppercase tracking-[0.16em] text-[#BFB4A5]/75">
                        {{ $group['label'] }}
                    </div>
                    @foreach($group['items'] as $item)
                        @php
$active = is_array($item['pattern'])
                                ? collect($item['pattern'])->contains(fn ($p) => request()->is($p))
                                : request()->is($item['pattern']);
@endphp
                        <a href="{{ route($item['route']) }}"
                           class="flex items-center justify-between gap-2 px-3 py-2.5 rounded-lg text-sm font-medium transition-colors {{ $active ? 'bg-[#D2B077] text-[#111110] font-semibold shadow-sm' : 'text-[#BFB4A5] hover:text-[#F4EEE5] hover:bg-[#28241F]/70' }}">
                            <span class="flex items-center gap-3 min-w-0">
                                @include('components.nav-icon', ['name' => $item['icon']])
                                <span class="truncate">{{ $item['label'] }}</span>
                            </span>
                            @if($item['badge'])
                                <span class="px-1.5 py-0.5 rounded-full text-[11px] font-semibold tabular-nums {{ $active ? 'bg-[#111110] text-[#D2B077]' : 'bg-[#E0A85B]/20 text-[#E0A85B]' }}">
                                    {{ $item['badge'] }}
                                </span>
                            @endif
                        </a>
                    @endforeach
                </div>
            @endforeach
        </nav>

        <div class="p-3.5 border-t border-white/10 space-y-2.5">
            <a href="{{ route('profile.edit') }}" class="flex items-center gap-3 rounded-lg bg-[#1D1B18] border border-white/10 p-2.5 hover:border-[#D2B077]/40 transition-colors">
                @if($authUser->avatarUrl())
                    <img src="{{ $authUser->avatarUrl() }}" alt="{{ $authUser->full_name }}" class="w-9 h-9 rounded-full object-cover border border-[#D2B077]/45 shrink-0">
                @else
                    <div class="w-9 h-9 rounded-full bg-[#28241F] border border-[#D2B077]/45 flex items-center justify-center text-xs font-semibold text-[#D2B077] shrink-0">
                        {{ $authUser->initials() }}
                    </div>
                @endif
                <div class="min-w-0">
                    <div class="text-[10px] uppercase tracking-[0.14em] text-[#BFB4A5]">Signed in as</div>
                    <div class="text-sm font-medium text-[#F4EEE5] truncate">{{ $authUser->full_name }}</div>
                </div>
            </a>

            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="abc-btn-secondary w-full text-xs py-2">
                    Sign Out
                </button>
            </form>
        </div>
    </aside>

    <div class="flex-1 min-w-0 flex flex-col">
        <header class="no-print sticky top-0 z-30 bg-[#1D1B18]/95 border-b border-white/10 px-4 sm:px-6 lg:px-8 py-3 flex items-center justify-between gap-3">
            <div class="flex items-center gap-3 min-w-0">
                <button type="button" id="mobile-nav-open" class="lg:hidden abc-btn-secondary py-2 px-2.5" aria-label="Open navigation menu" aria-expanded="false" aria-controls="mobile-nav-drawer">
                    <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75">
                        <path d="M4 6h16M4 12h16M4 18h16" stroke-linecap="round"/>
                    </svg>
                </button>

                <a href="{{ $isAdmin ? route('dashboard') : route('pos.index') }}" class="lg:hidden flex items-center gap-2">
                    <x-brand-logo :compact="true" />
                    <span class="font-display text-lg font-semibold text-[#F4EEE5] hidden sm:inline">Artisan Brew Co.</span>
                </a>

                <div class="hidden sm:flex items-center gap-2 rounded-lg bg-[#28241F] border border-white/10 px-3 py-1.5 text-xs text-[#BFB4A5]">
                    <span class="font-medium text-[#F4EEE5]">Manila Counter</span>
                    <span aria-hidden="true">·</span>
                    <span data-live-clock class="tabular-nums">{{ $manilaNow }}</span>
                </div>
            </div>

            <div class="flex items-center gap-2.5">
                <a href="{{ route('pos.index') }}" class="abc-btn-primary py-2 px-3.5 text-xs sm:text-sm">
                    New Sale
                </a>

                <a href="{{ route('profile.edit') }}" class="hidden sm:flex items-center gap-2.5 rounded-lg bg-[#28241F] border border-white/10 px-2.5 py-1.5 hover:border-[#D2B077]/40 transition-colors">
                    @if($authUser->avatarUrl())
                        <img src="{{ $authUser->avatarUrl() }}" alt="{{ $authUser->full_name }}" class="w-7 h-7 rounded-full object-cover border border-[#D2B077]/45">
                    @else
                        <div class="w-7 h-7 rounded-full bg-[#1D1B18] border border-[#D2B077]/45 flex items-center justify-center text-[11px] font-semibold text-[#D2B077]">
                            {{ $authUser->initials() }}
                        </div>
                    @endif
                    <div class="leading-tight text-left pr-1">
                        <div class="text-xs font-medium text-[#F4EEE5]">{{ $authUser->full_name }}</div>
                        <div class="text-[11px] text-[#BFB4A5] capitalize">{{ $authUser->role }}</div>
                    </div>
                </a>
            </div>
        </header>

        <div id="mobile-nav-drawer" class="no-print hidden fixed inset-0 z-50 lg:hidden" role="dialog" aria-modal="true" aria-label="Navigation menu">
            <div class="fixed inset-0 bg-black/75" data-close-drawer></div>
            <div class="fixed inset-y-0 left-0 h-dvh w-72 max-w-[85vw] bg-[#161412] border-r border-white/10 flex flex-col z-10">
                <div class="p-4 border-b border-white/10 flex items-center justify-between">
                    <x-brand-logo />
                    <button type="button" data-close-drawer class="abc-btn-secondary py-1.5 px-2.5 text-xs" aria-label="Close navigation menu">Close</button>
                </div>
                <nav class="flex-1 p-3 space-y-4 overflow-y-auto">
                    @foreach($navGroups as $group)
                        <div class="space-y-1">
                            <div class="px-3 pb-1 text-[10px] font-semibold uppercase tracking-[0.16em] text-[#BFB4A5]/75">
                                {{ $group['label'] }}
                            </div>
                            @foreach($group['items'] as $item)
                                @php
$active = is_array($item['pattern'])
                                        ? collect($item['pattern'])->contains(fn ($p) => request()->is($p))
                                        : request()->is($item['pattern']);
@endphp
                                <a href="{{ route($item['route']) }}"
                                   class="flex items-center justify-between gap-2 px-3 py-2.5 rounded-lg text-sm font-medium {{ $active ? 'bg-[#D2B077] text-[#111110] font-semibold' : 'text-[#BFB4A5] hover:text-[#F4EEE5]' }}">
                                    <span class="flex items-center gap-3">
                                        @include('components.nav-icon', ['name' => $item['icon']])
                                        <span>{{ $item['label'] }}</span>
                                    </span>
                                    @if($item['badge'])
                                        <span class="px-1.5 py-0.5 rounded-full text-[11px] font-semibold tabular-nums {{ $active ? 'bg-[#111110] text-[#D2B077]' : 'bg-[#E0A85B]/20 text-[#E0A85B]' }}">
                                            {{ $item['badge'] }}
                                        </span>
                                    @endif
                                </a>
                            @endforeach
                        </div>
                    @endforeach
                </nav>
                <div class="p-4 border-t border-white/10 space-y-3">
                    <div class="text-xs text-[#BFB4A5]">Signed in as <strong class="text-[#F4EEE5]">{{ $authUser->full_name }}</strong> ({{ ucfirst($authUser->role) }})</div>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="abc-btn-secondary w-full text-xs">Sign Out</button>
                    </form>
                </div>
            </div>
        </div>

        <main class="abc-shell-content flex-1 min-w-0">
            <div class="max-w-[1440px] mx-auto px-4 sm:px-6 lg:px-8 py-6">
                @if(session('status'))
                    <div class="no-print mb-5 rounded-lg border border-[#9CAF94]/40 bg-[#9CAF94]/12 px-4 py-3 text-sm text-[#D7E4D1]" role="status">
                        {{ session('status') }}
                    </div>
                @endif

                @if($errors->any())
                    <div class="no-print mb-5 rounded-lg border border-[#E07A7A]/40 bg-[#E07A7A]/12 px-4 py-3 text-sm text-[#F5C6C6]" role="alert">
                        <div class="font-semibold mb-1">Please review the following:</div>
                        <ul class="list-disc list-inside space-y-0.5">
                            @foreach($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                {{ $slot }}
            </div>
        </main>
    </div>
</div>

<dialog id="abc-confirm-dialog" class="m-auto rounded-xl bg-[#1D1B18] text-[#F4EEE5] border border-white/15 p-0 max-w-md w-[92vw] backdrop:bg-black/75">
    <div class="p-5 space-y-3">
        <h2 id="abc-confirm-title" class="font-display text-2xl font-semibold">Confirm Action</h2>
        <p id="abc-confirm-message" class="text-sm text-[#BFB4A5]">Are you sure?</p>
        <div class="flex justify-end gap-2 pt-2">
            <button type="button" id="abc-confirm-cancel" class="abc-btn-secondary">Cancel</button>
            <button type="button" id="abc-confirm-accept" class="abc-btn-danger">Confirm</button>
        </div>
    </div>
</dialog>
</body>
</html>
