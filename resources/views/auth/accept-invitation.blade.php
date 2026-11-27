<x-layouts.guest title="Activate Staff Account">
    <div class="w-full max-w-lg abc-panel p-6 sm:p-10 space-y-6">
        <div class="space-y-3">
            <x-brand-logo />
            <h1 class="font-display text-3xl sm:text-4xl font-semibold text-[#F4EEE5]">Staff Invitation</h1>
            @if($isValid && $invitation && $invitation->user)
                <p class="text-sm text-[#BFB4A5]">
                    Welcome <strong class="text-[#F4EEE5]">{{ $invitation->user->full_name }}</strong>. Set your personal password below to activate your <strong class="text-[#D2B077]">{{ ucfirst($invitation->user->role) }}</strong> account (<span class="font-mono text-[#F4EEE5]">{{ $invitation->user->username }}</span>).
                </p>
            @else
                <p class="text-sm text-[#BFB4A5]">
                    This staff invitation link is <strong class="text-[#E07A7A]">{{ $effectiveStatus }}</strong>. Please ask an administrator to send a fresh invitation.
                </p>
            @endif
        </div>

        @if($errors->any())
            <div class="rounded-lg border border-[#E07A7A]/40 bg-[#E07A7A]/12 px-4 py-3 text-sm text-[#F5C6C6]" role="alert">
                {{ $errors->first() }}
            </div>
        @endif

        @if($isValid && $invitation && $invitation->user)
            <form method="POST" action="{{ route('invitations.accept', $token) }}" class="space-y-4">
                @csrf

                <div class="space-y-1.5">
                    <label for="password" class="block text-sm font-medium text-[#F4EEE5]">Create Password</label>
                    <input id="password" name="password" type="password" required autofocus autocomplete="new-password" class="abc-input" placeholder="Minimum 8 chars (letters &amp; numbers)">
                </div>

                <div class="space-y-1.5">
                    <label for="password_confirmation" class="block text-sm font-medium text-[#F4EEE5]">Confirm Password</label>
                    <input id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password" class="abc-input">
                </div>

                <button type="submit" class="abc-btn-primary w-full py-2.5 text-sm">
                    Activate Staff Account
                </button>
            </form>
        @else
            <div class="pt-2">
                <a href="{{ route('login') }}" class="abc-btn-secondary w-full justify-center">Go to Staff Sign In</a>
            </div>
        @endif
    </div>
</x-layouts.guest>
