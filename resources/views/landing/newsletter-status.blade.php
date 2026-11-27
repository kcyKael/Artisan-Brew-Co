<x-layouts.guest title="Newsletter Preferences">
    <div class="w-full max-w-lg abc-panel p-6 sm:p-10 space-y-6">
        <div class="space-y-3">
            <x-brand-logo />
            @if($mode === 'confirm')
                <h1 class="font-display text-3xl font-semibold text-[#F4EEE5]">
                    {{ $success ? 'Subscription Confirmed' : 'Confirmation Link Invalid' }}
                </h1>
                <p class="text-sm text-[#BFB4A5]">
                    @if($success && $subscriber)
                        Welcome to Artisan Brew Co. seasonal notes and single-origin releases for <strong class="text-[#F4EEE5]">{{ $subscriber->email }}</strong>.
                    @else
                        This confirmation link is invalid, expired, or no longer eligible. You may subscribe again from our home page.
                    @endif
                </p>
            @elseif($mode === 'unsubscribe')
                <h1 class="font-display text-3xl font-semibold text-[#F4EEE5]">
                    {{ $success ? 'Unsubscribed Successfully' : 'Link Not Found' }}
                </h1>
                <p class="text-sm text-[#BFB4A5]">
                    @if($success && $subscriber)
                        <strong class="text-[#F4EEE5]">{{ $subscriber->email }}</strong> has been removed from Artisan Brew Co. promotional campaigns. Transactional receipts will still be sent when requested.
                    @else
                        This unsubscribe link could not be matched to a subscriber record.
                    @endif
                </p>
            @else
                <h1 class="font-display text-3xl font-semibold text-[#F4EEE5]">Email Preferences</h1>
                @if($success && $subscriber)
                    <p class="text-sm text-[#BFB4A5]">
                        Address: <strong class="text-[#F4EEE5]">{{ $subscriber->email }}</strong><br>
                        Current status: <strong class="text-[#D2B077]">{{ ucfirst($subscriber->status) }}</strong>
                    </p>
                    @if($subscriber->status !== 'unsubscribed')
                        <form method="POST" action="{{ route('newsletter.unsubscribe', $token) }}">
                            @csrf
                            <button type="submit" class="abc-btn-danger w-full py-2.5 text-sm">Unsubscribe from Promotional Emails</button>
                        </form>
                    @endif
                @else
                    <p class="text-sm text-[#BFB4A5]">This preference link is invalid or expired.</p>
                @endif
            @endif
        </div>

        <div class="pt-3 border-t border-white/10">
            <a href="{{ route('home') }}" class="abc-btn-secondary w-full justify-center">Return to Artisan Brew Co.</a>
        </div>
    </div>
</x-layouts.guest>
