<x-layouts.app title="My Profile">
    <div class="max-w-2xl mx-auto space-y-6">
        <div>
            <h1 class="font-display text-3xl sm:text-4xl font-semibold text-[#F5F0E8]">My Profile</h1>
            <p class="text-sm text-[#BDB5AA] mt-1">
                Signed in as <strong class="text-[#C6A66B]">{{ $user->username }}</strong> · Role: {{ ucfirst($user->role) }}
            </p>
        </div>

        <form method="POST" action="{{ route('profile.update') }}" enctype="multipart/form-data" class="abc-panel p-6 space-y-5">
            @csrf
            @method('PUT')

            <div class="space-y-1.5">
                <label for="full_name" class="block text-sm font-medium text-[#F5F0E8]">Display Full Name</label>
                <input id="full_name" name="full_name" type="text" required value="{{ old('full_name', $user->full_name) }}" class="abc-input">
            </div>

            <div class="flex flex-col sm:flex-row sm:items-center gap-4">
                @if($user->avatarUrl())
                    <img src="{{ $user->avatarUrl() }}" alt="{{ $user->full_name }}" class="w-16 h-16 rounded-full object-cover border border-[#C6A66B]/40">
                @else
                    <div class="w-16 h-16 rounded-full bg-[#24211D] border border-[#C6A66B]/40 flex items-center justify-center text-base font-semibold text-[#C6A66B]">
                        {{ $user->initials() }}
                    </div>
                @endif
                <div class="flex-1 space-y-2">
                    <label for="avatar" class="block text-sm font-medium text-[#F5F0E8]">Profile Avatar</label>
                    <input id="avatar" name="avatar" type="file" accept="image/jpeg,image/png,image/webp" class="abc-input text-xs">
                    @if($user->avatar)
                        <label class="inline-flex items-center gap-2 text-xs text-[#BDB5AA] cursor-pointer">
                            <input type="checkbox" name="remove_avatar" value="1" class="accent-[#C6A66B]">
                            <span>Remove current avatar</span>
                        </label>
                    @endif
                </div>
            </div>

            <div class="border-t border-white/10 pt-4 space-y-4">
                <h2 class="font-display text-2xl font-semibold text-[#F5F0E8]">Change Password</h2>
                <div class="space-y-1.5">
                    <label for="current_password" class="block text-sm font-medium text-[#F5F0E8]">Current Password (required when changing password)</label>
                    <input id="current_password" name="current_password" type="password" autocomplete="current-password" class="abc-input">
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div class="space-y-1.5">
                        <label for="password" class="block text-sm font-medium text-[#F5F0E8]">New Password</label>
                        <input id="password" name="password" type="password" autocomplete="new-password" class="abc-input">
                    </div>

                    <div class="space-y-1.5">
                        <label for="password_confirmation" class="block text-sm font-medium text-[#F5F0E8]">Confirm New Password</label>
                        <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" class="abc-input">
                    </div>
                </div>
            </div>

            <div class="flex justify-end pt-2">
                <button type="submit" class="abc-btn-primary">Update Profile</button>
            </div>
        </form>
    </div>
</x-layouts.app>
