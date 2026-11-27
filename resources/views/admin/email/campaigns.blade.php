@php
    use App\Support\BusinessTime;
@endphp

<x-layouts.app title="Promotional Campaigns">
    <div class="space-y-6">
        @include('admin.email.tabs')

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div class="abc-panel p-4">
                <div class="text-xs uppercase tracking-wider text-[#BFB4A5]">Eligible Confirmed Subscribers</div>
                <div class="text-2xl font-semibold text-[#D2B077] tabular-nums mt-1">{{ $audienceCount }}</div>
            </div>
            <div class="abc-panel p-4">
                <div class="text-xs uppercase tracking-wider text-[#BFB4A5]">Brevo Marketing List</div>
                <div class="text-base font-semibold text-[#F4EEE5] mt-1">
                    {{ $marketingListConfigured ? 'Configured' : 'Not configured in Email Settings' }}
                </div>
            </div>
            <div class="abc-panel p-4">
                <div class="text-xs uppercase tracking-wider text-[#BFB4A5]">Delivery Guard</div>
                <div class="mt-1">
                    <x-status-badge :tone="$liveSendEnabled ? 'success' : 'accent'">
                        {{ $liveSendEnabled ? 'Live Send Enabled' : 'Preview / Safe Mode Only' }}
                    </x-status-badge>
                </div>
            </div>
        </div>

        <form method="POST" action="{{ route('admin.email.campaigns.store') }}" class="abc-panel p-6 space-y-4">
            @csrf
            <div>
                <h2 class="font-display text-2xl font-semibold text-[#F4EEE5]">New Campaign Draft</h2>
                <p class="text-xs text-[#BFB4A5] mt-0.5">
                    Always sends only to confirmed opt-in subscribers with an automatic one-click unsubscribe footer.
                </p>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="space-y-1.5">
                    <label for="camp-name" class="block text-sm font-medium text-[#F4EEE5]">Internal Campaign Name</label>
                    <input id="camp-name" name="name" type="text" required value="{{ old('name') }}" class="abc-input" placeholder="e.g. Rainy Season Single-Origin Launch">
                </div>
                <div class="space-y-1.5">
                    <label for="camp-subject" class="block text-sm font-medium text-[#F4EEE5]">Email Subject Line</label>
                    <input id="camp-subject" name="subject" type="text" required value="{{ old('subject') }}" class="abc-input" placeholder="e.g. Meet our Bukidnon Honey-Process Pour">
                </div>
            </div>

            <div class="space-y-1.5">
                <label for="camp-preview" class="block text-sm font-medium text-[#F4EEE5]">Preview Text (Optional)</label>
                <input id="camp-preview" name="preview_text" type="text" value="{{ old('preview_text') }}" class="abc-input" placeholder="Short inbox preheader summary">
            </div>

            <div class="space-y-1.5">
                <label for="camp-body" class="block text-sm font-medium text-[#F4EEE5]">Announcement Content (Plain text paragraphs or safe HTML)</label>
                <textarea id="camp-body" name="body_html" rows="5" required class="abc-input" placeholder="Write your seasonal drink announcement...">{{ old('body_html') }}</textarea>
            </div>

            <div class="flex justify-end">
                <button type="submit" class="abc-btn-primary">Save Campaign Draft</button>
            </div>
        </form>

        <div class="abc-panel overflow-hidden">
            <div class="px-5 py-3.5 border-b border-white/10">
                <h2 class="font-display text-xl font-semibold text-[#F4EEE5]">Campaigns</h2>
            </div>
            @if($campaigns->isEmpty())
                <div class="p-6 text-sm text-[#BFB4A5]">No promotional campaigns drafted yet.</div>
            @else
                <div class="divide-y divide-white/10">
                    @foreach($campaigns as $campaign)
                        <div class="p-5 space-y-4">
                            <div class="flex flex-col md:flex-row md:items-center justify-between gap-3">
                                <div>
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span class="font-display text-xl font-semibold text-[#F4EEE5]">{{ $campaign->name }}</span>
                                        <x-status-badge :tone="$campaign->status === 'sent' ? 'success' : 'accent'">{{ ucfirst($campaign->status) }}</x-status-badge>
                                    </div>
                                    <div class="text-xs text-[#BFB4A5] mt-0.5">
                                        Subject: <span class="text-[#F4EEE5]">{{ $campaign->subject }}</span>
                                        @if($campaign->sent_at)
                                            · Dispatched {{ BusinessTime::format($campaign->sent_at) }}
                                        @endif
                                    </div>
                                </div>
                                <div class="flex flex-wrap items-center gap-2">
                                    <a href="{{ route('admin.email.campaigns.preview', $campaign) }}" target="_blank" rel="noopener" class="abc-btn-secondary py-1.5 px-3 text-xs">Preview HTML</a>
                                    @if($campaign->provider_campaign_id)
                                        <form method="POST" action="{{ route('admin.email.campaigns.sync', $campaign) }}">
                                            @csrf
                                            <button type="submit" class="abc-btn-secondary py-1.5 px-3 text-xs">Sync Stats</button>
                                        </form>
                                    @endif
                                </div>
                            </div>

                            <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-3 pt-2 border-t border-white/10">
                                <form method="POST" action="{{ route('admin.email.campaigns.send-test', $campaign) }}" class="flex flex-wrap items-center gap-2">
                                    @csrf
                                    <input name="test_email" type="email" required value="{{ $defaultTestEmail }}" class="abc-input text-xs py-1.5 w-60" placeholder="test@artisanbrewco.ph">
                                    <button type="submit" class="abc-btn-secondary py-1.5 px-3 text-xs">Send Test</button>
                                </form>

                                @if($campaign->status !== 'sent')
                                    <form method="POST"
                                          action="{{ route('admin.email.campaigns.send', $campaign) }}"
                                          data-confirm-title="Dispatch Promotional Campaign"
                                          data-confirm="Send '{{ $campaign->name }}' to eligible confirmed subscribers via Brevo?">
                                        @csrf
                                        <button type="submit" class="abc-btn-primary py-1.5 px-4 text-xs" @disabled(!$marketingEnabled || !$marketingListConfigured || !$liveSendEnabled)>
                                            Send Campaign via Brevo
                                        </button>
                                    </form>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
                <div class="px-4 pb-4">{{ $campaigns->links() }}</div>
            @endif
        </div>
    </div>
</x-layouts.app>
