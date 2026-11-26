<x-layouts.app title="Edit {{ $staff->full_name }}">
    <div class="max-w-2xl mx-auto space-y-6">
        <div class="flex items-center justify-between gap-4">
            <h1 class="font-display text-3xl sm:text-4xl font-semibold text-[#F5F0E8]">Edit {{ $staff->full_name }}</h1>
            <a href="{{ route('staff.index') }}" class="abc-btn-secondary">Back to Staff</a>
        </div>

        <form method="POST" action="{{ route('staff.update', $staff) }}" enctype="multipart/form-data" class="abc-panel p-6 space-y-5">
            @csrf
            @method('PUT')

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="space-y-1.5">
                    <label for="username" class="block text-sm font-medium text-[#F5F0E8]">Username</label>
                    <input id="username" name="username" type="text" required value="{{ old('username', $staff->username) }}" class="abc-input">
                </div>

                <div class="space-y-1.5">
                    <label for="full_name" class="block text-sm font-medium text-[#F5F0E8]">Full Name</label>
                    <input id="full_name" name="full_name" type="text" required value="{{ old('full_name', $staff->full_name) }}" class="abc-input">
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="space-y-1.5">
                    <label for="role" class="block text-sm font-medium text-[#F5F0E8]">Role</label>
                    <select id="role" name="role" class="abc-input">
                        <option value="cashier" @selected(old('role', $staff->role) === 'cashier')>Cashier</option>
                        <option value="admin" @selected(old('role', $staff->role) === 'admin')>Administrator</option>
                    </select>
                </div>

                <div class="flex items-end pb-2">
                    <label class="inline-flex items-center gap-2 text-sm text-[#F5F0E8] cursor-pointer">
                        <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $staff->is_active)) class="accent-[#C6A66B]">
                        <span>Account is active</span>
                    </label>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="space-y-1.5">
                    <label for="password" class="block text-sm font-medium text-[#F5F0E8]">New Password (leave blank to keep current)</label>
                    <input id="password" name="password" type="password" autocomplete="new-password" class="abc-input">
                </div>

                <div class="space-y-1.5">
                    <label for="password_confirmation" class="block text-sm font-medium text-[#F5F0E8]">Confirm New Password</label>
                    <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" class="abc-input">
                </div>
            </div>

            <div class="flex flex-col sm:flex-row sm:items-center gap-4">
                @if($staff->avatarUrl())
                    <img src="{{ $staff->avatarUrl() }}" alt="{{ $staff->full_name }}" class="w-16 h-16 rounded-full object-cover border border-[#C6A66B]/40">
                @endif
                <div class="flex-1 space-y-2">
                    <label for="avatar" class="block text-sm font-medium text-[#F5F0E8]">Replace Avatar</label>
                    <input id="avatar" name="avatar" type="file" accept="image/jpeg,image/png,image/webp" class="abc-input text-xs">
                    @if($staff->avatar)
                        <label class="inline-flex items-center gap-2 text-xs text-[#BDB5AA] cursor-pointer">
                            <input type="checkbox" name="remove_avatar" value="1" class="accent-[#C6A66B]">
                            <span>Remove current avatar</span>
                        </label>
                    @endif
                </div>
            </div>

            <div class="flex justify-end gap-3 pt-2">
                <a href="{{ route('staff.index') }}" class="abc-btn-secondary">Cancel</a>
                <button type="submit" class="abc-btn-primary">Save Staff Changes</button>
            </div>
        </form>
    </div>
</x-layouts.app>
