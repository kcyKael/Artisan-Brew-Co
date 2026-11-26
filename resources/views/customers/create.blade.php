<x-layouts.app title="Add Customer">
    <div class="max-w-2xl mx-auto space-y-6">
        <div class="flex items-center justify-between gap-4">
            <h1 class="font-display text-3xl sm:text-4xl font-semibold text-[#F5F0E8]">Add Customer</h1>
            <a href="{{ route('customers.index') }}" class="abc-btn-secondary">Back to Customers</a>
        </div>

        <form method="POST" action="{{ route('customers.store') }}" class="abc-panel p-6 space-y-4">
            @csrf

            <div class="space-y-1.5">
                <label for="full_name" class="block text-sm font-medium text-[#F5F0E8]">Full Name</label>
                <input id="full_name" name="full_name" type="text" required value="{{ old('full_name') }}" class="abc-input" placeholder="e.g. Maria Santos">
            </div>

            <div class="space-y-1.5">
                <label for="email" class="block text-sm font-medium text-[#F5F0E8]">Email Address</label>
                <input id="email" name="email" type="email" required value="{{ old('email') }}" class="abc-input" placeholder="maria@example.ph">
            </div>

            <div class="space-y-1.5">
                <label for="phone" class="block text-sm font-medium text-[#F5F0E8]">Phone Number (Optional)</label>
                <input id="phone" name="phone" type="text" value="{{ old('phone') }}" class="abc-input" placeholder="+63 917 555 0199">
            </div>

            <div class="space-y-1.5">
                <label for="notes" class="block text-sm font-medium text-[#F5F0E8]">Preferences / Notes (Optional)</label>
                <textarea id="notes" name="notes" rows="2" class="abc-input">{{ old('notes') }}</textarea>
            </div>

            <div class="flex justify-end gap-3 pt-2">
                <a href="{{ route('customers.index') }}" class="abc-btn-secondary">Cancel</a>
                <button type="submit" class="abc-btn-primary">Create Customer</button>
            </div>
        </form>
    </div>
</x-layouts.app>
