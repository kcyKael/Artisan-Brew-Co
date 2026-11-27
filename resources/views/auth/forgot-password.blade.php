<x-layouts.guest title="Reset Staff Password">
    <div class="w-full max-w-lg abc-panel p-6 sm:p-10 space-y-6">
        <div class="space-y-3">
            <x-brand-logo />
            <h1 class="font-display text-3xl sm:text-4xl font-semibold text-[#F4EEE5]">Forgot Password</h1>
            <p class="text-sm text-[#BFB4A5]">
                Enter the email address on your active Artisan Brew Co. staff account. We will send a single-use reset link valid for 60 minutes.
            </p>
        </div>

        @if(session('status'))
            <div class="rounded-lg border border-[#9CAF94]/40 bg-[#9CAF94]/12 px-4 py-3 text-sm text-[#D7E4D1]" role="status">
                {{ session('status') }}
            </div>
        @endif

        @if($errors->any())
            <div class="rounded-lg border border-[#E07A7A]/40 bg-[#E07A7A]/12 px-4 py-3 text-sm text-[#F5C6C6]" role="alert">
                {{ $errors->first() }}
            </div>
        @endif

        <form method="POST" action="{{ route('password.email') }}" class="space-y-4">
            @csrf

            <div class="space-y-1.5">
                <label for="email" class="block text-sm font-medium text-[#F4EEE5]">Staff Email Address</label>
                <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus autocomplete="email" class="abc-input" placeholder="barista@artisanbrewco.ph">
            </div>

            <button type="submit" class="abc-btn-primary w-full py-2.5 text-sm">
                Send Password Reset Link
            </button>
        </form>

        <div class="pt-3 border-t border-white/10 flex items-center justify-between text-xs text-[#BFB4A5]">
            <a href="{{ route('login') }}" class="text-[#D2B077] hover:underline underline-offset-4">&larr; Back to Staff Sign In</a>
            <a href="{{ route('home') }}" class="hover:text-[#F4EEE5]">Return Home</a>
        </div>
    </div>
</x-layouts.guest>
