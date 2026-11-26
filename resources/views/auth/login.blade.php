<x-layouts.guest title="Staff Sign In">
    <div class="w-full max-w-md space-y-6">
        <div class="text-center space-y-3">
            <div class="inline-flex justify-center">
                <x-brand-logo />
            </div>
            <h1 class="font-display text-3xl sm:text-4xl font-semibold text-[#F5F0E8]">Staff Sign In</h1>
            <p class="text-sm text-[#BDB5AA]">Enter your Artisan Brew Co. staff credentials to open the counter or management console.</p>
        </div>

        <div class="abc-panel p-6 sm:p-8 space-y-5">
            @if(session('status'))
                <div class="rounded-lg border border-[#58B987]/35 bg-[#58B987]/10 px-4 py-3 text-sm text-[#A9E5C5]" role="status">
                    {{ session('status') }}
                </div>
            @endif

            @if($errors->any())
                <div class="rounded-lg border border-[#E6777D]/40 bg-[#E6777D]/10 px-4 py-3 text-sm text-[#F4B4B8]" role="alert">
                    {{ $errors->first() }}
                </div>
            @endif

            <form method="POST" action="{{ route('login.store') }}" class="space-y-4">
                @csrf

                <div class="space-y-1.5">
                    <label for="username" class="block text-sm font-medium text-[#F5F0E8]">Username</label>
                    <input id="username" name="username" type="text" value="{{ old('username') }}" required autofocus autocomplete="username" class="abc-input" placeholder="e.g. cashier or admin">
                </div>

                <div class="space-y-1.5">
                    <label for="password" class="block text-sm font-medium text-[#F5F0E8]">Password</label>
                    <input id="password" name="password" type="password" required autocomplete="current-password" class="abc-input" placeholder="Enter your password">
                </div>

                <label class="inline-flex items-center gap-2 text-sm text-[#BDB5AA] cursor-pointer">
                    <input type="checkbox" name="remember" value="1" class="accent-[#C6A66B]">
                    <span>Keep me signed in on this terminal</span>
                </label>

                <button type="submit" class="abc-btn-primary w-full py-2.5 text-sm">
                    Sign In to Counter
                </button>
            </form>
        </div>

        <p class="text-center text-xs text-[#BDB5AA]">
            Authorized Artisan Brew Co. staff only · All transactions recorded in PHP (₱) · Asia/Manila
        </p>
    </div>
</x-layouts.guest>
