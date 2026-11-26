<x-layouts.app title="Edit {{ $customer->full_name }}">
    <div class="max-w-2xl mx-auto space-y-6">
        <div class="flex items-center justify-between gap-4">
            <h1 class="font-display text-3xl sm:text-4xl font-semibold text-[#F4EEE5]">Edit Customer</h1>
            <a href="{{ route('customers.show', $customer) }}" class="abc-btn-secondary">Back to Profile</a>
        </div>

        <form method="POST" action="{{ route('customers.update', $customer) }}" class="abc-panel p-6 space-y-4">
            @csrf
            @method('PUT')

            <div class="space-y-1.5">
                <label for="full_name" class="block text-sm font-medium text-[#F4EEE5]">Full Name</label>
                <input id="full_name" name="full_name" type="text" required value="{{ old('full_name', $customer->full_name) }}" class="abc-input">
            </div>

            <div class="space-y-1.5">
                <label for="email" class="block text-sm font-medium text-[#F4EEE5]">Email Address</label>
                <input id="email" name="email" type="email" required value="{{ old('email', $customer->email) }}" class="abc-input">
            </div>

            <div class="space-y-1.5">
                <label for="phone" class="block text-sm font-medium text-[#F4EEE5]">Phone Number (Optional)</label>
                <input id="phone" name="phone" type="text" value="{{ old('phone', $customer->phone) }}" class="abc-input">
            </div>

            <div class="space-y-1.5">
                <label for="notes" class="block text-sm font-medium text-[#F4EEE5]">Preferences / Notes (Optional)</label>
                <textarea id="notes" name="notes" rows="2" class="abc-input">{{ old('notes', $customer->notes) }}</textarea>
            </div>

            <div class="flex justify-end gap-3 pt-2">
                <a href="{{ route('customers.show', $customer) }}" class="abc-btn-secondary">Cancel</a>
                <button type="submit" class="abc-btn-primary">Save Changes</button>
            </div>
        </form>
    </div>
</x-layouts.app>
