@props(['title' => 'Artisan Brew Co.'])

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title }} — Artisan Brew Co.</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('scripts')
</head>
<body class="bg-[#111110] text-[#F5F0E8] antialiased">
@php
$authUser = auth()->user();
    $isAdmin = $authUser?->isAdmin() ?? false;
    $navItems = array_values(array_filter([
        $isAdmin ? ['route' => 'dashboard', 'pattern' => 'dashboard*', 'label' => 'Dashboard', 'icon' => 'chart'] : null,
        ['route' => 'pos.index', 'pattern' => 'pos*', 'label' => 'POS Terminal', 'icon' => 'pos'],
        ['route' => 'sales.record', 'pattern' => 'sales/record*', 'label' => 'Record Sale', 'icon' => 'plus-circle'],
        ['route' => 'sales.index', 'pattern' => ['sales', 'orders*'], 'label' => 'Sales History', 'icon' => 'receipt'],
        $isAdmin ? ['route' => 'products.index', 'pattern' => 'products*', 'label' => 'Products', 'icon' => 'coffee'] : null,
        ['route' => 'customers.index', 'pattern' => 'customers*', 'label' => 'Customers', 'icon' => 'users'],
        $isAdmin ? ['route' => 'staff.index', 'pattern' => 'staff*', 'label' => 'Staff', 'icon' => 'badge'] : null,
        $isAdmin ? ['route' => 'menu-config.index', 'pattern' => 'menu-config*', 'label' => 'Menu Options', 'icon' => 'sliders'] : null,
        ['route' => 'profile.edit', 'pattern' => 'profile*', 'label' => 'My Profile', 'icon' => 'user'],
    ]));
@endphp

<div class="min-h-screen flex flex-col lg:flex-row">
    <aside class="no-print hidden lg:flex lg:w-64 lg:shrink-0 lg:flex-col bg-[#161513] border-r border-white/10">
        <div class="p-5 border-b border-white/10">
            <a href="{{ $isAdmin ? route('dashboard') : route('pos.index') }}" class="block">
                <x-brand-logo />
            </a>
        </div>

        <nav class="flex-1 p-3 space-y-1 overflow-y-auto" aria-label="Main navigation">
            @foreach($navItems as $item)
                @php
$active = is_array($item['pattern'])
                        ? collect($item['pattern'])->contains(fn ($p) => request()->is($p))
                        : request()->is($item['pattern']);
@endphp
                <a href="{{ route($item['route']) }}"
                   class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-colors {{ $active ? 'bg-[#24211D] text-[#C6A66B] border border-[#C6A66B]/30' : 'text-[#BDB5AA] hover:text-[#F5F0E8] hover:bg-white/[0.04]' }}">
                    @include('components.nav-icon', ['name' => $item['icon']])
                    <span>{{ $item['label'] }}</span>
                </a>
            @endforeach
        </nav>

        <div class="p-4 border-t border-white/10 space-y-3">
            <div class="flex items-center gap-3">
                @if($authUser->avatarUrl())
                    <img src="{{ $authUser->avatarUrl() }}" alt="{{ $authUser->full_name }}" class="w-9 h-9 rounded-full object-cover border border-[#C6A66B]/40">
                @else
                    <div class="w-9 h-9 rounded-full bg-[#24211D] border border-[#C6A66B]/40 flex items-center justify-center text-xs font-semibold text-[#C6A66B]">
                        {{ $authUser->initials() }}
                    </div>
                @endif
                <div class="min-w-0">
                    <div class="text-sm font-medium text-[#F5F0E8] truncate">{{ $authUser->full_name }}</div>
                    <div class="text-xs text-[#BDB5AA] capitalize">{{ $authUser->role }} · {{ $authUser->username }}</div>
                </div>
            </div>

            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="abc-btn-secondary w-full text-xs py-2">
                    Sign Out
                </button>
            </form>
        </div>
    </aside>

    <header class="no-print lg:hidden sticky top-0 z-30 bg-[#161513]/95 border-b border-white/10 px-4 py-3 flex items-center justify-between gap-3">
        <a href="{{ $isAdmin ? route('dashboard') : route('pos.index') }}" class="flex items-center gap-2.5">
            <x-brand-logo :compact="true" />
            <span class="font-display text-lg font-semibold text-[#F5F0E8]">Artisan Brew Co.</span>
        </a>
        <div class="flex items-center gap-2">
            <span class="hidden sm:inline text-xs text-[#BDB5AA]">{{ $authUser->full_name }} ({{ ucfirst($authUser->role) }})</span>
            <button type="button" id="mobile-nav-open" class="abc-btn-secondary py-2 px-3" aria-label="Open navigation menu">
                <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75">
                    <path d="M4 6h16M4 12h16M4 18h16" stroke-linecap="round"/>
                </svg>
                <span class="text-xs">Menu</span>
            </button>
        </div>
    </header>

    <div id="mobile-nav-drawer" class="no-print hidden fixed inset-0 z-50 lg:hidden" role="dialog" aria-modal="true" aria-label="Navigation menu">
        <div class="fixed inset-0 bg-black/70" data-close-drawer></div>
        <div class="fixed inset-y-0 left-0 w-72 max-w-[85vw] bg-[#161513] border-r border-white/10 flex flex-col z-10">
            <div class="p-4 border-b border-white/10 flex items-center justify-between">
                <x-brand-logo />
                <button type="button" data-close-drawer class="abc-btn-secondary py-1.5 px-2.5 text-xs" aria-label="Close navigation menu">Close</button>
            </div>
            <nav class="flex-1 p-3 space-y-1 overflow-y-auto">
                @foreach($navItems as $item)
                    @php
$active = is_array($item['pattern'])
                            ? collect($item['pattern'])->contains(fn ($p) => request()->is($p))
                            : request()->is($item['pattern']);
@endphp
                    <a href="{{ route($item['route']) }}"
                       class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium {{ $active ? 'bg-[#24211D] text-[#C6A66B] border border-[#C6A66B]/30' : 'text-[#BDB5AA] hover:text-[#F5F0E8]' }}">
                        @include('components.nav-icon', ['name' => $item['icon']])
                        <span>{{ $item['label'] }}</span>
                    </a>
                @endforeach
            </nav>
            <div class="p-4 border-t border-white/10 space-y-3">
                <div class="text-xs text-[#BDB5AA]">Signed in as <strong class="text-[#F5F0E8]">{{ $authUser->full_name }}</strong> ({{ ucfirst($authUser->role) }})</div>
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
                <div class="no-print mb-5 rounded-lg border border-[#58B987]/35 bg-[#58B987]/10 px-4 py-3 text-sm text-[#A9E5C5]" role="status">
                    {{ session('status') }}
                </div>
            @endif

            @if($errors->any())
                <div class="no-print mb-5 rounded-lg border border-[#E6777D]/40 bg-[#E6777D]/10 px-4 py-3 text-sm text-[#F4B4B8]" role="alert">
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

<dialog id="abc-confirm-dialog" class="m-auto rounded-xl bg-[#1B1A18] text-[#F5F0E8] border border-white/15 p-0 max-w-md w-[92vw] backdrop:bg-black/75">
    <div class="p-5 space-y-3">
        <h2 id="abc-confirm-title" class="font-display text-2xl font-semibold">Confirm Action</h2>
        <p id="abc-confirm-message" class="text-sm text-[#BDB5AA]">Are you sure?</p>
        <div class="flex justify-end gap-2 pt-2">
            <button type="button" id="abc-confirm-cancel" class="abc-btn-secondary">Cancel</button>
            <button type="button" id="abc-confirm-accept" class="abc-btn-danger">Confirm</button>
        </div>
    </div>
</dialog>
</body>
</html>
