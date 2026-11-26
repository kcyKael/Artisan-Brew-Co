<x-layouts.app title="Add Staff Member">
    <div class="max-w-2xl mx-auto space-y-6">
        <div class="flex items-center justify-between gap-4">
            <h1 class="font-display text-3xl sm:text-4xl font-semibold text-[#F5F0E8]">Add Staff Member</h1>
            <a href="{{ route('staff.index') }}" class="abc-btn-secondary">Back to Staff</a>
        </div>

        <form method="POST" action="{{ route('staff.store') }}" enctype="multipart/form-data" class="abc-panel p-6 space-y-4">
            @csrf

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="space-y-1.5">
                    <label for="username" class="block text-sm font-medium text-[#F5F0E8]">Username</label>
                    <input id="username" name="username" type="text" required value="{{ old('username') }}" class="abc-input" placeholder="e.g. barista2">
                </div>

                <div class="space-y-1.5">
                    <label for="full_name" class="block text-sm font-medium text-[#F5F0E8]">Full Name</label>
                    <input id="full_name" name="full_name" type="text" required value="{{ old('full_name') }}" class="abc-input" placeholder="e.g. Mateo Cruz">
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="space-y-1.5">
                    <label for="role" class="block text-sm font-medium text-[#F5F0E8]">Role</label>
                    <select id="role" name="role" class="abc-input">
                        <option value="cashier" @selected(old('role', 'cashier') === 'cashier')>Cashier</option>
                        <option value="admin" @selected(old('role') === 'admin')>Administrator</option>
                    </select>
                </div>

                <div class="flex items-end pb-2">
                    <label class="inline-flex items-center gap-2 text-sm text-[#F5F0E8] cursor-pointer">
                        <input type="checkbox" name="is_active" value="1" @checked(old('is_active', true)) class="accent-[#C6A66B]">
                        <span>Account is active</span>
                    </label>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="space-y-1.5">
                    <label for="password" class="block text-sm font-medium text-[#F5F0E8]">Password (min 8 chars)</label>
                    <input id="password" name="password" type="password" required autocomplete="new-password" class="abc-input">
                </div>

                <div class="space-y-1.5">
                    <label for="password_confirmation" class="block text-sm font-medium text-[#F5F0E8]">Confirm Password</label>
                    <input id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password" class="abc-input">
                </div>
            </div>

            <div class="space-y-1.5">
                <label for="avatar" class="block text-sm font-medium text-[#F5F0E8]">Avatar (Optional · JPEG, PNG, WebP · max 3 MB)</label>
                <input id="avatar" name="avatar" type="file" accept="image/jpeg,image/png,image/webp" class="abc-input text-xs">
            </div>

            <div class="flex justify-end gap-3 pt-2">
                <a href="{{ route('staff.index') }}" class="abc-btn-secondary">Cancel</a>
                <button type="submit" class="abc-btn-primary">Create Staff Account</button>
            </div>
        </form>
    </div>
</x-layouts.app>
