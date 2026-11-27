<x-layouts.app title="Edit {{ $staff->full_name }}">
    <div class="max-w-2xl mx-auto space-y-6">
        <div class="flex items-center justify-between gap-4">
            <h1 class="font-display text-3xl sm:text-4xl font-semibold text-[#F4EEE5]">Edit {{ $staff->full_name }}</h1>
            <a href="{{ route('staff.index') }}" class="abc-btn-secondary">Back to Staff</a>
        </div>

        <form method="POST" action="{{ route('staff.update', $staff) }}" enctype="multipart/form-data" class="abc-panel p-6 space-y-5">
            @csrf
            @method('PUT')

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="space-y-1.5">
                    <label for="username" class="block text-sm font-medium text-[#F4EEE5]">Username</label>
                    <input id="username" name="username" type="text" required value="{{ old('username', $staff->username) }}" class="abc-input">
                </div>

                <div class="space-y-1.5">
                    <label for="full_name" class="block text-sm font-medium text-[#F4EEE5]">Full Name</label>
                    <input id="full_name" name="full_name" type="text" required value="{{ old('full_name', $staff->full_name) }}" class="abc-input">
                </div>
            </div>

            <div class="space-y-1.5">
                <label for="email" class="block text-sm font-medium text-[#F4EEE5]">Staff Email</label>
                <input id="email" name="email" type="email" value="{{ old('email', $staff->email) }}" class="abc-input" placeholder="barista@artisanbrewco.ph">
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="space-y-1.5">
                    <label for="role" class="block text-sm font-medium text-[#F4EEE5]">Role</label>
                    <select id="role" name="role" class="abc-input">
                        <option value="cashier" @selected(old('role', $staff->role) === 'cashier')>Cashier</option>
                        <option value="admin" @selected(old('role', $staff->role) === 'admin')>Administrator</option>
                    </select>
                </div>

                <div class="flex items-end pb-2">
                    <label class="inline-flex items-center gap-2 text-sm text-[#F4EEE5] cursor-pointer">
                        <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $staff->is_active)) class="accent-[#D2B077]">
                        <span>Account is active</span>
                    </label>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="space-y-1.5">
                    <label for="password" class="block text-sm font-medium text-[#F4EEE5]">New Password (leave blank to keep current)</label>
                    <input id="password" name="password" type="password" autocomplete="new-password" class="abc-input">
                </div>

                <div class="space-y-1.5">
                    <label for="password_confirmation" class="block text-sm font-medium text-[#F4EEE5]">Confirm New Password</label>
                    <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" class="abc-input">
                </div>
            </div>

            <div class="flex flex-col sm:flex-row sm:items-center gap-4">
                @if($staff->avatarUrl())
                    <img src="{{ $staff->avatarUrl() }}" alt="{{ $staff->full_name }}" class="w-16 h-16 rounded-full object-cover border border-[#D2B077]/40">
                @endif
                <div class="flex-1 space-y-2">
                    <label for="avatar" class="block text-sm font-medium text-[#F4EEE5]">Replace Avatar</label>
                    <input id="avatar" name="avatar" type="file" accept="image/jpeg,image/png,image/webp" class="abc-input text-xs">
                    @if($staff->avatar)
                        <label class="inline-flex items-center gap-2 text-xs text-[#BFB4A5] cursor-pointer">
                            <input type="checkbox" name="remove_avatar" value="1" class="accent-[#D2B077]">
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

        <div class="abc-panel p-6 space-y-4">
            @php
                $latestInvite = $staff->latestInvitation;
                $inviteStatus = $latestInvite?->effectiveStatus() ?? 'none';
            @endphp
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="font-display text-2xl font-semibold text-[#F4EEE5]">Staff Onboarding Invitation</h2>
                    <p class="text-xs text-[#BFB4A5] mt-0.5">
                        Status: <strong class="text-[#D2B077]">{{ ucfirst($inviteStatus) }}</strong>
                        @if($latestInvite && $latestInvite->expires_at && $inviteStatus === 'pending')
                            · Expires {{ $latestInvite->expires_at->timezone('Asia/Manila')->format('M d, Y h:i A') }}
                        @endif
                    </p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    @if(filled($staff->email) && ! $staff->is_active)
                        <form method="POST" action="{{ route('staff.invitations.resend', $staff) }}">
                            @csrf
                            <button type="submit" class="abc-btn-primary py-2 px-3 text-xs">Send / Resend Invitation</button>
                        </form>
                    @endif
                    @if($inviteStatus === 'pending')
                        <form method="POST" action="{{ route('staff.invitations.revoke', $staff) }}">
                            @csrf
                            <button type="submit" class="abc-btn-danger py-2 px-3 text-xs">Revoke Invite</button>
                        </form>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-layouts.app>
