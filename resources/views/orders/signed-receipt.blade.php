<x-layouts.guest title="Receipt {{ $order->order_number }}">
    <div class="w-full max-w-2xl space-y-6">
        <div class="abc-panel p-6 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <div class="text-xs uppercase tracking-[0.2em] text-[#D2B077]">Digital Customer Receipt</div>
                <h1 class="font-display text-3xl font-semibold text-[#F4EEE5] tabular-nums">Order {{ $order->order_number }}</h1>
            </div>
            <a href="{{ route('home') }}" class="abc-btn-secondary self-start sm:self-auto">Visit Artisan Brew Co.</a>
        </div>

        <div class="flex justify-center">
            <x-thermal-receipt :order="$order" />
        </div>
    </div>
</x-layouts.guest>
