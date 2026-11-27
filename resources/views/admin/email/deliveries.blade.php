@php
    use App\Support\BusinessTime;
@endphp

<x-layouts.app title="Email Deliveries &amp; Webhooks">
    <div class="space-y-6">
        @include('admin.email.tabs')

        <div class="abc-panel p-4 flex flex-wrap items-end justify-between gap-4">
            <form method="GET" action="{{ route('admin.email.deliveries') }}" class="flex flex-wrap items-end gap-3 flex-1">
                <div class="min-w-[160px] space-y-1">
                    <label for="del-status" class="block text-xs font-medium text-[#BFB4A5]">Delivery Status</label>
                    <select id="del-status" name="status" class="abc-input text-sm py-2">
                        <option value="">All statuses</option>
                        @foreach(['queued', 'processing', 'accepted', 'delivered', 'simulated', 'failed', 'bounced', 'blocked', 'uncertain', 'expired'] as $st)
                            <option value="{{ $st }}" @selected($statusFilter === $st)>{{ ucfirst($st) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="min-w-[180px] space-y-1">
                    <label for="del-purpose" class="block text-xs font-medium text-[#BFB4A5]">Purpose</label>
                    <input id="del-purpose" name="purpose" type="text" value="{{ $purposeFilter }}" class="abc-input text-sm py-2" placeholder="receipt, low_stock, reset...">
                </div>
                <button type="submit" class="abc-btn-primary py-2 px-4 text-xs">Filter</button>
                <a href="{{ route('admin.email.deliveries') }}" class="abc-btn-secondary py-2 px-3 text-xs">Reset</a>
            </form>

            <form method="POST" action="{{ route('admin.email.deliveries.recover') }}">
                @csrf
                <button type="submit" class="abc-btn-secondary py-2 px-3.5 text-xs">Recover Pending Outbox</button>
            </form>
        </div>

        <div class="abc-panel overflow-hidden">
            <div class="px-5 py-3.5 border-b border-white/10">
                <h2 class="font-display text-xl font-semibold text-[#F4EEE5]">Transactional Outbox</h2>
            </div>
            @if($deliveries->isEmpty())
                <div class="p-6 text-sm text-[#BFB4A5]">No email deliveries recorded matching the current filter.</div>
            @else
                <div class="overflow-x-auto">
                    <table class="abc-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Purpose &amp; Recipient</th>
                                <th>Subject</th>
                                <th>Status</th>
                                <th>Attempts</th>
                                <th>Updated (Manila)</th>
                                <th class="text-right">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($deliveries as $delivery)
                                @php
                                    $tone = match ($delivery->status) {
                                        'delivered', 'accepted' => 'success',
                                        'simulated', 'queued', 'processing' => 'accent',
                                        default => 'danger',
                                    };
                                @endphp
                                <tr>
                                    <td class="text-xs tabular-nums text-[#BFB4A5]">{{ $delivery->id }}</td>
                                    <td>
                                        <div class="text-sm font-medium text-[#F4EEE5]">{{ $delivery->recipient_email }}</div>
                                        <div class="text-xs text-[#D2B077]">{{ $delivery->purpose }} · {{ $delivery->queue_name }}</div>
                                    </td>
                                    <td class="text-sm text-[#BFB4A5]">
                                        <div class="max-w-xs truncate">{{ $delivery->subject }}</div>
                                        @if($delivery->last_error)
                                            <div class="text-xs text-[#E07A7A] mt-0.5 max-w-xs truncate" title="{{ $delivery->last_error }}">{{ $delivery->last_error }}</div>
                                        @endif
                                    </td>
                                    <td>
                                        <x-status-badge :tone="$tone">{{ ucfirst($delivery->status) }}</x-status-badge>
                                    </td>
                                    <td class="text-xs tabular-nums text-[#BFB4A5]">{{ $delivery->attempts }} / {{ (int) config('services.brevo.max_retries', 4) }}</td>
                                    <td class="text-xs tabular-nums text-[#BFB4A5]">{{ BusinessTime::format($delivery->updated_at) }}</td>
                                    <td class="text-right">
                                        @if(in_array($delivery->status, ['failed', 'bounced', 'blocked'], true) && $delivery->payload_pruned_at === null)
                                            <form method="POST" action="{{ route('admin.email.deliveries.retry', $delivery) }}">
                                                @csrf
                                                <button type="submit" class="abc-btn-secondary py-1 px-2.5 text-xs">Retry</button>
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
                <div class="px-4 pb-4">{{ $deliveries->links() }}</div>
            @endif
        </div>

        <div class="abc-panel overflow-hidden">
            <div class="px-5 py-3.5 border-b border-white/10">
                <h2 class="font-display text-xl font-semibold text-[#F4EEE5]">Recent Brevo Webhook Events</h2>
            </div>
            @if($webhookEvents->isEmpty())
                <div class="p-6 text-sm text-[#BFB4A5]">No webhook callbacks received yet.</div>
            @else
                <div class="overflow-x-auto">
                    <table class="abc-table">
                        <thead>
                            <tr>
                                <th>Channel</th>
                                <th>Event</th>
                                <th>Email</th>
                                <th>Message ID</th>
                                <th>Processed</th>
                                <th>Received (Manila)</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($webhookEvents as $event)
                                <tr>
                                    <td class="text-xs uppercase text-[#D2B077]">{{ $event->scope }}</td>
                                    <td class="text-sm text-[#F4EEE5]">{{ $event->event_type }}</td>
                                    <td class="text-sm text-[#BFB4A5]">{{ $event->email ?: '—' }}</td>
                                    <td class="text-xs font-mono text-[#BFB4A5]">{{ $event->provider_message_id ?: '—' }}</td>
                                    <td>
                                        <x-status-badge :tone="$event->status === 'processed' ? 'success' : 'accent'">
                                            {{ ucfirst($event->status) }}
                                        </x-status-badge>
                                    </td>
                                    <td class="text-xs tabular-nums text-[#BFB4A5]">{{ BusinessTime::format($event->created_at) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
</x-layouts.app>
