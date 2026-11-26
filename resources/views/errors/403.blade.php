<x-layouts.guest title="Access Denied (403)">
    <div class="abc-panel max-w-md w-full p-8 text-center space-y-4">
        <div class="text-xs font-semibold uppercase tracking-[0.18em] text-[#D2B077]">Error 403</div>
        <h1 class="font-display text-3xl font-semibold text-[#F4EEE5]">Permission Required</h1>
        <p class="text-sm text-[#BFB4A5]">Your staff role does not have access to this page or order record.</p>
        <div class="pt-2">
            <a href="{{ auth()->check() ? (auth()->user()->isAdmin() ? route('dashboard') : route('pos.index')) : route('login') }}" class="abc-btn-primary">
                Return to Workspace
            </a>
        </div>
    </div>
</x-layouts.guest>
