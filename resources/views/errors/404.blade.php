<x-layouts.guest title="Page Not Found (404)">
    <div class="abc-panel max-w-md w-full p-8 text-center space-y-4">
        <div class="text-xs font-semibold uppercase tracking-[0.18em] text-[#D2B077]">Error 404</div>
        <h1 class="font-display text-3xl font-semibold text-[#F4EEE5]">Page Not Found</h1>
        <p class="text-sm text-[#BFB4A5]">The requested Artisan Brew Co. page or receipt could not be found.</p>
        <div class="pt-2">
            <a href="{{ route('home') }}" class="abc-btn-primary">Return Home</a>
        </div>
    </div>
</x-layouts.guest>
