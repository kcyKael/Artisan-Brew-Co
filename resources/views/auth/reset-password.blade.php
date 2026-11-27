<x-layouts.guest title="Choose New Password">
    <div class="w-full max-w-lg abc-panel p-6 sm:p-10 space-y-6">
        <div class="space-y-3">
            <x-brand-logo />
            <h1 class="font-display text-3xl sm:text-4xl font-semibold text-[#F4EEE5]">Set New Password</h1>
            <p class="text-sm text-[#BFB4A5]">
                Choose a strong password with at least 8 characters including letters and numbers. All existing staff sessions will be signed out.
            </p>
        </div>

        @if($errors->any())
            <div class="rounded-lg border border-[#E07A7A]/40 bg-[#E07A7A]/12 px-4 py-3 text-sm text-[#F5C6C6]" role="alert">
                {{ $errors->first() }}
            </div>
        @endif

        <form method="POST" action="{{ route('password.update') }}" class="space-y-4">
            @csrf
            <input type="hidden" name="token" value="{{ $token }}">

            <div class="space-y-1.5">
                <label for="email" class="block text-sm font-medium text-[#F4EEE5]">Email Address</label>
                <input id="email" name="email" type="email" value="{{ old('email', $email) }}" required autocomplete="email" class="abc-input">
            </div>

            <div class="space-y-1.5">
                <label for="password" class="block text-sm font-medium text-[#F4EEE5]">New Password</label>
                <input id="password" name="password" type="password" required autofocus autocomplete="new-password" class="abc-input" placeholder="Minimum 8 chars (letters &amp; numbers)">
            </div>

            <div class="space-y-1.5">
                <label for="password_confirmation" class="block text-sm font-medium text-[#F4EEE5]">Confirm New Password</label>
                <input id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password" class="abc-input">
            </div>

            <button type="submit" class="abc-btn-primary w-full py-2.5 text-sm">
                Reset Password
            </button>
        </form>

        <div class="pt-3 border-t border-white/10 text-xs text-[#BFB4A5]">
            <a href="{{ route('login') }}" class="text-[#D2B077] hover:underline underline-offset-4">&larr; Back to Staff Sign In</a>
        </div>
    </div>
</x-layouts.guest>
