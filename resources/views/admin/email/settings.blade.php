@php
    use App\Services\Email\EmailSettingsService;
    use App\Support\BusinessTime;

    $featureLabels = [
        'feature_receipts' => ['Customer Digital Receipts', 'Receipt email at checkout, order detail resend, and signed public receipt page.'],
        'feature_password_reset' => ['Staff Password Recovery', 'Forgot-password reset links routed via Brevo high-priority queue.'],
        'feature_invitations' => ['Staff Onboarding Invitations', 'Tokenized account activation links with 48-hour expiry.'],
        'feature_low_stock' => ['Low-Stock & Sold-Out Alerts', 'Atomic transition alerts (or hourly digest) sent to owner alert emails.'],
        'feature_daily_summary' => ['Daily Sales Summary', 'Automated Manila closing report with CSV sales breakdown attachment.'],
        'feature_security_alerts' => ['Account-Security Alerts', 'Notifications for password changes, role updates, deactivation, and email changes.'],
        'feature_enquiries' => ['Contact Enquiries & Acknowledgements', 'Forwards website contact submissions to the cafe inbox and acknowledges visitors.'],
        'feature_marketing' => ['Promotional Campaigns & Newsletter', 'Double opt-in newsletter signups, Brevo contact sync, and campaign dispatch.'],
    ];
@endphp

<x-layouts.app title="Email Center Settings">
    <div class="space-y-6">
        @include('admin.email.tabs')

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div class="abc-panel p-4 space-y-1">
                <div class="text-xs uppercase tracking-wider text-[#BFB4A5]">Brevo HTTPS API Key</div>
                <div class="pt-1">
                    <x-status-badge :tone="$apiKeyConfigured ? 'success' : 'danger'">
                        {{ $apiKeyConfigured ? 'API Key Configured' : 'API Key Missing' }}
                    </x-status-badge>
                </div>
            </div>
            <div class="abc-panel p-4 space-y-1">
                <div class="text-xs uppercase tracking-wider text-[#BFB4A5]">Live Outbound Mode</div>
                <div class="pt-1">
                    <x-status-badge :tone="$liveSendEnabled ? 'success' : 'accent'">
                        {{ $liveSendEnabled ? 'Live Brevo HTTPS Send Active' : 'Safe Simulated Preview Mode' }}
                    </x-status-badge>
                </div>
            </div>
            <div class="abc-panel p-4 space-y-1">
                <div class="text-xs uppercase tracking-wider text-[#BFB4A5]">Webhook Token Protection</div>
                <div class="pt-1">
                    <x-status-badge :tone="$webhookConfigured ? 'success' : 'danger'">
                        {{ $webhookConfigured ? 'Bearer / Header Secret Set' : 'Webhook Secret Unset' }}
                    </x-status-badge>
                </div>
            </div>
        </div>

        <form method="POST" action="{{ route('admin.email.settings.update') }}" class="abc-panel p-6 space-y-6">
            @csrf
            @method('PUT')

            <div>
                <h2 class="font-display text-2xl font-semibold text-[#F4EEE5]">Sender Identity, Recipients &amp; Schedules</h2>
                <p class="text-xs text-[#BFB4A5] mt-0.5">
                    Configure verified Brevo sender headers, internal alert lists, daily Manila closing time, and stock alert mode.
                </p>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div class="space-y-1.5">
                    <label for="sender_email" class="block text-sm font-medium text-[#F4EEE5]">Sender Email</label>
                    <input id="sender_email" name="sender_email" type="email" value="{{ old('sender_email', $settings['sender_email']) }}" class="abc-input" placeholder="no-reply@artisanbrewco.ph">
                </div>
                <div class="space-y-1.5">
                    <label for="sender_name" class="block text-sm font-medium text-[#F4EEE5]">Sender Name</label>
                    <input id="sender_name" name="sender_name" type="text" value="{{ old('sender_name', $settings['sender_name']) }}" class="abc-input" placeholder="Artisan Brew Co.">
                </div>
                <div class="space-y-1.5">
                    <label for="reply_to_email" class="block text-sm font-medium text-[#F4EEE5]">Reply-To Email</label>
                    <input id="reply_to_email" name="reply_to_email" type="email" value="{{ old('reply_to_email', $settings['reply_to_email']) }}" class="abc-input" placeholder="hello@artisanbrewco.ph">
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="space-y-1.5">
                    <label for="owner_alert_emails" class="block text-sm font-medium text-[#F4EEE5]">Owner / Manager Alert Emails (comma-separated)</label>
                    <input id="owner_alert_emails" name="owner_alert_emails" type="text" value="{{ old('owner_alert_emails', $settings['owner_alert_emails']) }}" class="abc-input" placeholder="owner@artisanbrewco.ph, manager@artisanbrewco.ph">
                </div>
                <div class="space-y-1.5">
                    <label for="cafe_inbox_email" class="block text-sm font-medium text-[#F4EEE5]">Cafe Contact Inbox Email</label>
                    <input id="cafe_inbox_email" name="cafe_inbox_email" type="email" value="{{ old('cafe_inbox_email', $settings['cafe_inbox_email']) }}" class="abc-input" placeholder="hello@artisanbrewco.ph">
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-4 gap-4">
                <div class="space-y-1.5">
                    <label for="daily_summary_time" class="block text-sm font-medium text-[#F4EEE5]">Daily Summary Time (PHT)</label>
                    <input id="daily_summary_time" name="daily_summary_time" type="text" pattern="^(?:[01]\d|2[0-3]):[0-5]\d$" required value="{{ old('daily_summary_time', $settings['daily_summary_time']) }}" class="abc-input tabular-nums" placeholder="22:00">
                </div>
                <div class="space-y-1.5">
                    <label for="stock_alert_mode" class="block text-sm font-medium text-[#F4EEE5]">Stock Alert Mode</label>
                    <select id="stock_alert_mode" name="stock_alert_mode" class="abc-input">
                        <option value="immediate" @selected(old('stock_alert_mode', $settings['stock_alert_mode']) === 'immediate')>Immediate per transition</option>
                        <option value="digest" @selected(old('stock_alert_mode', $settings['stock_alert_mode']) === 'digest')>Hourly digest</option>
                    </select>
                </div>
                <div class="space-y-1.5">
                    <label for="marketing_list_id" class="block text-sm font-medium text-[#F4EEE5]">Brevo Marketing List ID</label>
                    <input id="marketing_list_id" name="marketing_list_id" type="number" min="1" value="{{ old('marketing_list_id', $settings['marketing_list_id']) }}" class="abc-input tabular-nums" placeholder="e.g. 4">
                </div>
                <div class="space-y-1.5">
                    <label for="test_recipient_email" class="block text-sm font-medium text-[#F4EEE5]">Default Test Recipient</label>
                    <input id="test_recipient_email" name="test_recipient_email" type="email" value="{{ old('test_recipient_email', $settings['test_recipient_email']) }}" class="abc-input" placeholder="qa@artisanbrewco.ph">
                </div>
            </div>

            <div class="space-y-3 pt-2 border-t border-white/10">
                <div class="text-xs font-semibold uppercase tracking-[0.16em] text-[#D2B077]">Feature Toggles</div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    @foreach(EmailSettingsService::FEATURE_KEYS as $featureKey)
                        @php [$title, $desc] = $featureLabels[$featureKey]; @endphp
                        <label class="abc-surface p-3.5 flex items-start gap-3 cursor-pointer">
                            <input type="checkbox" name="{{ $featureKey }}" value="1" @checked(!empty($settings[$featureKey])) class="mt-1 accent-[#D2B077]">
                            <span>
                                <span class="block text-sm font-medium text-[#F4EEE5]">{{ $title }}</span>
                                <span class="block text-xs text-[#BFB4A5] mt-0.5">{{ $desc }}</span>
                            </span>
                        </label>
                    @endforeach
                </div>
            </div>

            <div class="flex justify-end">
                <button type="submit" class="abc-btn-primary">Save Email Settings</button>
            </div>
        </form>

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
            <div class="lg:col-span-6 abc-panel p-6 space-y-5">
                <div>
                    <h2 class="font-display text-2xl font-semibold text-[#F4EEE5]">Template Previews &amp; Test Send</h2>
                    <p class="text-xs text-[#BFB4A5] mt-0.5">Preview rendered HTML templates or queue a sample email through the outbox.</p>
                </div>

                <form method="POST" action="{{ route('admin.email.test') }}" class="space-y-4">
                    @csrf
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div class="space-y-1.5">
                            <label for="test-template" class="block text-xs font-medium text-[#BFB4A5]">Template</label>
                            <select id="test-template" name="template" class="abc-input text-sm py-2">
                                @foreach($templates as $slug => $label)
                                    <option value="{{ $slug }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="space-y-1.5">
                            <label for="test-recipient" class="block text-xs font-medium text-[#BFB4A5]">Recipient Email</label>
                            <input id="test-recipient" name="recipient_email" type="email" value="{{ $settings['test_recipient_email'] }}" class="abc-input text-sm py-2" placeholder="owner@artisanbrewco.ph">
                        </div>
                    </div>
                    <button type="submit" class="abc-btn-primary py-2 px-4 text-xs">Send Test Email</button>
                </form>

                <div class="pt-3 border-t border-white/10 space-y-2">
                    <div class="text-xs font-medium text-[#BFB4A5]">Open Sample Template Preview</div>
                    <div class="flex flex-wrap gap-2">
                        @foreach($templates as $slug => $label)
                            <a href="{{ route('admin.email.templates.preview', $slug) }}" target="_blank" rel="noopener" class="abc-btn-secondary py-1.5 px-3 text-xs">
                                {{ $label }}
                            </a>
                        @endforeach
                    </div>
                </div>
            </div>

            <div class="lg:col-span-6 abc-panel p-6 space-y-5">
                <div>
                    <h2 class="font-display text-2xl font-semibold text-[#F4EEE5]">Operational Reports &amp; Stock Digest</h2>
                    <p class="text-xs text-[#BFB4A5] mt-0.5">
                        Live preview window: {{ $dailyPreview['periodLabel'] }}
                    </p>
                </div>

                <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                    <div class="abc-surface p-3">
                        <div class="text-[11px] text-[#BFB4A5]">Net Sales</div>
                        <div class="text-sm font-semibold text-[#D2B077] tabular-nums mt-0.5">{{ $dailyPreview['revenueFormatted'] }}</div>
                    </div>
                    <div class="abc-surface p-3">
                        <div class="text-[11px] text-[#BFB4A5]">Orders</div>
                        <div class="text-sm font-semibold text-[#F4EEE5] tabular-nums mt-0.5">{{ $dailyPreview['orderCount'] }}</div>
                    </div>
                    <div class="abc-surface p-3">
                        <div class="text-[11px] text-[#BFB4A5]">Items Sold</div>
                        <div class="text-sm font-semibold text-[#F4EEE5] tabular-nums mt-0.5">{{ $dailyPreview['unitsSold'] }}</div>
                    </div>
                    <div class="abc-surface p-3">
                        <div class="text-[11px] text-[#BFB4A5]">Stock Alerts</div>
                        <div class="text-sm font-semibold text-[#E07A7A] tabular-nums mt-0.5">{{ count($dailyPreview['stockAlerts']) }}</div>
                    </div>
                </div>

                <div class="flex flex-wrap items-end gap-3 pt-2 border-t border-white/10">
                    <form method="POST" action="{{ route('admin.email.daily-summary') }}" class="flex flex-wrap items-end gap-2">
                        @csrf
                        <div class="space-y-1">
                            <label for="summary-test-email" class="block text-xs text-[#BFB4A5]">Test Recipient (optional)</label>
                            <input id="summary-test-email" name="test_email" type="email" class="abc-input text-xs py-1.5 w-52" placeholder="Leave blank for owner list">
                        </div>
                        <button type="submit" class="abc-btn-primary py-2 px-3.5 text-xs">Send Daily Summary</button>
                    </form>

                    <form method="POST" action="{{ route('admin.email.stock-digest') }}">
                        @csrf
                        <button type="submit" class="abc-btn-secondary py-2 px-3.5 text-xs">Send Stock Digest Now</button>
                    </form>
                </div>

                @if($recentReports->isNotEmpty())
                    <div class="pt-2 border-t border-white/10 space-y-2">
                        <div class="text-xs font-medium text-[#BFB4A5]">Recent Dispatched Daily Reports</div>
                        <div class="space-y-1.5 text-xs text-[#BFB4A5]">
                            @foreach($recentReports as $rep)
                                <div class="flex items-center justify-between gap-2">
                                    <span class="font-mono text-[#F4EEE5]">{{ $rep->business_date?->format('M d, Y') }}</span>
                                    <span>{{ $rep->sent_at ? 'Dispatched '.BusinessTime::format($rep->sent_at) : 'Pending dispatch' }}</span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-layouts.app>
