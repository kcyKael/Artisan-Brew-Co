<x-layouts.app title="Staff Management">
    <div class="space-y-6">
        <div class="flex flex-col sm:flex-row sm:items-end justify-between gap-4">
            <div>
                <h1 class="font-display text-3xl sm:text-4xl font-semibold text-[#F4EEE5]">Staff Accounts</h1>
                <p class="text-sm text-[#BFB4A5] mt-1">Manage administrator and cashier credentials, avatars, roles, and account status.</p>
            </div>
            <a href="{{ route('staff.create') }}" class="abc-btn-primary self-start sm:self-auto">Add Staff Member</a>
        </div>

        <form method="GET" action="{{ route('staff.index') }}" class="abc-panel p-4 flex flex-wrap gap-3 items-end">
            <div class="flex-1 min-w-[220px] space-y-1">
                <label for="staff-search" class="block text-xs font-medium text-[#BFB4A5]">Search Staff</label>
                <input id="staff-search" name="search" type="search" value="{{ $search }}" class="abc-input text-sm py-2" placeholder="Username or full name...">
            </div>
            <button type="submit" class="abc-btn-primary py-2 px-4 text-xs">Search</button>
            <a href="{{ route('staff.index') }}" class="abc-btn-secondary py-2 px-3 text-xs">Reset</a>
        </form>

        @if($staffMembers->isEmpty())
            <x-empty-state title="No staff members found" description="Try another search query or add a new staff account." />
        @else
            <div class="abc-panel overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="abc-table">
                        <thead>
                            <tr>
                                <th>Staff Member</th>
                                <th>Username &amp; Email</th>
                                <th>Role</th>
                                <th>Status</th>
                                <th class="text-right">Orders</th>
                                <th class="text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($staffMembers as $member)
                                <tr>
                                    <td>
                                        <div class="flex items-center gap-3">
                                            @if($member->avatarUrl())
                                                <img src="{{ $member->avatarUrl() }}" alt="{{ $member->full_name }}" class="w-9 h-9 rounded-full object-cover border border-[#D2B077]/40">
                                            @else
                                                <div class="w-9 h-9 rounded-full bg-[#28241F] border border-[#D2B077]/40 flex items-center justify-center text-xs font-semibold text-[#D2B077]">
                                                    {{ $member->initials() }}
                                                </div>
                                            @endif
                                            <span class="font-medium text-[#F4EEE5]">{{ $member->full_name }}</span>
                                        </div>
                                    </td>
                                    <td class="text-sm text-[#BFB4A5]">
                                        <div class="text-[#F4EEE5]">{{ $member->username }}</div>
                                        @if(filled($member->email))
                                            <div class="text-xs text-[#BFB4A5]">{{ $member->email }}</div>
                                        @endif
                                    </td>
                                    <td>
                                        <x-status-badge :tone="$member->isAdmin() ? 'accent' : 'neutral'">
                                            {{ ucfirst($member->role) }}
                                        </x-status-badge>
                                    </td>
                                    <td>
                                        @php
                                            $latestInvite = $member->latestInvitation;
                                            $inviteState = $latestInvite?->effectiveStatus();
                                        @endphp
                                        <div class="flex flex-wrap items-center gap-1.5">
                                            <x-status-badge :tone="$member->is_active ? 'success' : 'danger'">
                                                {{ $member->is_active ? 'Active' : 'Inactive' }}
                                            </x-status-badge>
                                            @if($inviteState === 'pending')
                                                <x-status-badge tone="accent">Invite Pending</x-status-badge>
                                            @elseif($inviteState === 'expired')
                                                <x-status-badge tone="danger">Invite Expired</x-status-badge>
                                            @endif
                                        </div>
                                    </td>
                                    <td class="text-right tabular-nums">{{ $member->orders_count }}</td>
                                    <td class="text-right whitespace-nowrap">
                                        <div class="inline-flex items-center gap-2">
                                            <a href="{{ route('staff.edit', $member) }}" class="abc-btn-secondary py-1 px-2.5 text-xs">Edit</a>
                                            @if((int) auth()->id() !== (int) $member->id)
                                                <form method="POST"
                                                      action="{{ route('staff.destroy', $member) }}"
                                                      data-confirm-title="Remove Staff Member"
                                                      data-confirm="{{ $member->orders_count > 0 ? 'This staff member has completed sales and will be removed from active use while preserving historical receipts.' : 'Permanently delete this unreferenced staff account?' }}">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="abc-btn-danger py-1 px-2.5 text-xs">Delete</button>
                                                </form>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="px-4 pb-4">{{ $staffMembers->links() }}</div>
            </div>
        @endif
    </div>
</x-layouts.app>
