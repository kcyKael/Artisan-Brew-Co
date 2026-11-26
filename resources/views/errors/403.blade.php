<x-layouts.guest title="Access Denied (403)">
    <div class="abc-panel max-w-md w-full p-8 text-center space-y-4">
        <div class="text-xs font-semibold uppercase tracking-[0.18em] text-[#C6A66B]">Error 403</div>
        <h1 class="font-display text-3xl font-semibold text-[#F5F0E8]">Permission Required</h1>
        <p class="text-sm text-[#BDB5AA]">Your staff role does not have access to this page or order record.</p>
        <div class="pt-2">
            <a href="{{ auth()->check() ? (auth()->user()->isAdmin() ? route('dashboard') : route('pos.index')) : route('login') }}" class="abc-btn-primary">
                Return to Workspace
            </a>
        </div>
    </div>
</x-layouts.guest>
