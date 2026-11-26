<x-layouts.guest title="Session Expired (419)">
    <div class="abc-panel max-w-md w-full p-8 text-center space-y-4">
        <div class="text-xs font-semibold uppercase tracking-[0.18em] text-[#C6A66B]">Error 419</div>
        <h1 class="font-display text-3xl font-semibold text-[#F5F0E8]">Session Expired</h1>
        <p class="text-sm text-[#BDB5AA]">Your security token expired while idle. Please refresh or sign in again.</p>
        <div class="pt-2">
            <a href="{{ route('login') }}" class="abc-btn-primary">Sign In Again</a>
        </div>
    </div>
</x-layouts.guest>
