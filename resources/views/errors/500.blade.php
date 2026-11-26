<x-layouts.guest title="Server Error (500)">
    <div class="abc-panel max-w-md w-full p-8 text-center space-y-4">
        <div class="text-xs font-semibold uppercase tracking-[0.18em] text-[#D2B077]">Error 500</div>
        <h1 class="font-display text-3xl font-semibold text-[#F4EEE5]">Unexpected Counter Error</h1>
        <p class="text-sm text-[#BFB4A5]">Something went wrong on our server. No transaction was finalized if this occurred during checkout.</p>
        <div class="pt-2">
            <a href="{{ route('home') }}" class="abc-btn-primary">Return Home</a>
        </div>
    </div>
</x-layouts.guest>
