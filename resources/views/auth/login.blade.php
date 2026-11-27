<x-layouts.guest title="Staff Sign In">
    <div class="w-full max-w-5xl abc-panel overflow-hidden grid grid-cols-1 lg:grid-cols-12">
        <div class="lg:col-span-6 relative min-h-[240px] lg:min-h-[580px] bg-[#161412]">
            <img src="{{ asset('images/cafe-interior-bar.jpg') }}"
                 alt="Artisan Brew Co. charcoal stone counter, warm amber bar lighting, and espresso seating"
                 class="absolute inset-0 w-full h-full object-cover object-center">
            <div class="absolute inset-0 bg-gradient-to-t from-[#111110] via-[#111110]/45 to-[#111110]/20"></div>
            <div class="relative z-10 h-full flex flex-col justify-between p-6 sm:p-8">
                <x-brand-logo />
                <div class="space-y-2 max-w-sm">
                    <h2 class="font-display text-2xl sm:text-3xl font-semibold text-[#F4EEE5] leading-snug">
                        Crafted service from first pour to closing ledger.
                    </h2>
                    <p class="text-xs sm:text-sm text-[#BFB4A5]">
                        Specialty espresso counter, live cup stock tracking, and printed thermal receipts in PHP (₱).
                    </p>
                </div>
            </div>
        </div>

        <div class="lg:col-span-6 bg-[#1D1B18] p-6 sm:p-10 flex flex-col justify-center space-y-6">
            <div class="space-y-1.5">
                <h1 class="font-display text-3xl sm:text-4xl font-semibold text-[#F4EEE5]">Staff Sign In</h1>
                <p class="text-sm text-[#BFB4A5]">Enter your Artisan Brew Co. staff credentials to open the counter or management console.</p>
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

            <form method="POST" action="{{ route('login.store') }}" class="space-y-4">
                @csrf

                <div class="space-y-1.5">
                    <label for="username" class="block text-sm font-medium text-[#F4EEE5]">Username</label>
                    <input id="username" name="username" type="text" value="{{ old('username') }}" required autofocus autocomplete="username" class="abc-input" placeholder="e.g. cashier or admin">
                </div>

                <div class="space-y-1.5">
                    <label for="password" class="block text-sm font-medium text-[#F4EEE5]">Password</label>
                    <input id="password" name="password" type="password" required autocomplete="current-password" class="abc-input" placeholder="Enter your password">
                </div>

                <label class="inline-flex items-center gap-2 text-sm text-[#BFB4A5] cursor-pointer">
                    <input type="checkbox" name="remember" value="1" class="accent-[#D2B077]">
                    <span>Keep me signed in on this terminal</span>
                </label>

                <button type="submit" class="abc-btn-primary w-full py-2.5 text-sm">
                    Sign In to Counter
                </button>
            </form>

            <div class="pt-2 border-t border-white/10 flex flex-wrap items-center justify-between gap-2 text-xs text-[#BFB4A5]">
                <span>Authorized Artisan Brew Co. staff only · Asia/Manila business clock</span>
                <a href="{{ route('home') }}" class="text-[#D2B077] hover:underline underline-offset-4">&larr; Back to Artisan Brew Co.</a>
            </div>
        </div>
    </div>
</x-layouts.guest>
