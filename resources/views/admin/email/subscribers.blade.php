@php
    use App\Models\MarketingSubscriber;
    use App\Support\BusinessTime;
@endphp

<x-layouts.app title="Newsletter Subscribers">
    <div class="space-y-6">
        @include('admin.email.tabs')

        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
            <div class="abc-panel p-4">
                <div class="text-xs uppercase tracking-wider text-[#BFB4A5]">Subscribed</div>
                <div class="text-2xl font-semibold text-[#9CAF94] tabular-nums mt-1">{{ $counts['subscribed'] }}</div>
            </div>
            <div class="abc-panel p-4">
                <div class="text-xs uppercase tracking-wider text-[#BFB4A5]">Pending Confirmation</div>
                <div class="text-2xl font-semibold text-[#D2B077] tabular-nums mt-1">{{ $counts['pending'] }}</div>
            </div>
            <div class="abc-panel p-4">
                <div class="text-xs uppercase tracking-wider text-[#BFB4A5]">Unsubscribed</div>
                <div class="text-2xl font-semibold text-[#F4EEE5] tabular-nums mt-1">{{ $counts['unsubscribed'] }}</div>
            </div>
            <div class="abc-panel p-4">
                <div class="text-xs uppercase tracking-wider text-[#BFB4A5]">Suppressed (Bounced / Blocked)</div>
                <div class="text-2xl font-semibold text-[#E07A7A] tabular-nums mt-1">{{ $counts['suppressed'] }}</div>
            </div>
        </div>

        <form method="GET" action="{{ route('admin.email.subscribers') }}" class="abc-panel p-4 flex flex-wrap items-end gap-3">
            <div class="min-w-[180px] space-y-1">
                <label for="sub-status" class="block text-xs font-medium text-[#BFB4A5]">Subscriber Status</label>
                <select id="sub-status" name="status" class="abc-input text-sm py-2">
                    <option value="">All subscribers</option>
                    <option value="{{ MarketingSubscriber::STATUS_SUBSCRIBED }}" @selected($statusFilter === MarketingSubscriber::STATUS_SUBSCRIBED)>Subscribed</option>
                    <option value="{{ MarketingSubscriber::STATUS_PENDING }}" @selected($statusFilter === MarketingSubscriber::STATUS_PENDING)>Pending</option>
                    <option value="{{ MarketingSubscriber::STATUS_UNSUBSCRIBED }}" @selected($statusFilter === MarketingSubscriber::STATUS_UNSUBSCRIBED)>Unsubscribed</option>
                    <option value="{{ MarketingSubscriber::STATUS_SUPPRESSED }}" @selected($statusFilter === MarketingSubscriber::STATUS_SUPPRESSED)>Suppressed</option>
                </select>
            </div>
            <button type="submit" class="abc-btn-primary py-2 px-4 text-xs">Filter</button>
            <a href="{{ route('admin.email.subscribers') }}" class="abc-btn-secondary py-2 px-3 text-xs">Reset</a>
        </form>

        <div class="abc-panel overflow-hidden">
            @if($subscribers->isEmpty())
                <div class="p-6 text-sm text-[#BFB4A5]">No newsletter subscribers recorded yet.</div>
            @else
                <div class="overflow-x-auto">
                    <table class="abc-table">
                        <thead>
                            <tr>
                                <th>Subscriber</th>
                                <th>Status</th>
                                <th>Consent Source</th>
                                <th>Confirmed (Manila)</th>
                                <th>Brevo Sync</th>
                                <th class="text-right">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($subscribers as $subscriber)
                                @php
                                    $tone = match ($subscriber->status) {
                                        MarketingSubscriber::STATUS_SUBSCRIBED => 'success',
                                        MarketingSubscriber::STATUS_PENDING => 'accent',
                                        MarketingSubscriber::STATUS_SUPPRESSED => 'danger',
                                        default => 'neutral',
                                    };
                                @endphp
                                <tr>
                                    <td>
                                        <div class="text-sm font-medium text-[#F4EEE5]">{{ $subscriber->email }}</div>
                                        @if($subscriber->name || $subscriber->customer)
                                            <div class="text-xs text-[#BFB4A5]">{{ $subscriber->name ?: $subscriber->customer?->full_name }}</div>
                                        @endif
                                    </td>
                                    <td><x-status-badge :tone="$tone">{{ ucfirst($subscriber->status) }}</x-status-badge></td>
                                    <td class="text-xs text-[#BFB4A5]">{{ $subscriber->consent_source ?: '—' }}</td>
                                    <td class="text-xs tabular-nums text-[#BFB4A5]">{{ $subscriber->confirmed_at ? BusinessTime::format($subscriber->confirmed_at) : '—' }}</td>
                                    <td class="text-xs text-[#BFB4A5]">{{ $subscriber->brevo_synced_at ? BusinessTime::format($subscriber->brevo_synced_at) : 'Pending' }}</td>
                                    <td class="text-right">
                                        @if(in_array($subscriber->status, [MarketingSubscriber::STATUS_SUBSCRIBED, MarketingSubscriber::STATUS_PENDING], true))
                                            <form method="POST" action="{{ route('admin.email.subscribers.unsubscribe', $subscriber) }}">
                                                @csrf
                                                <button type="submit" class="abc-btn-danger py-1 px-2.5 text-xs">Unsubscribe</button>
                                            </form>
                                        @else
                                            <span class="text-xs text-[#BFB4A5]/60">—</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="px-4 pb-4">{{ $subscribers->links() }}</div>
            @endif
        </div>
    </div>
</x-layouts.app>
