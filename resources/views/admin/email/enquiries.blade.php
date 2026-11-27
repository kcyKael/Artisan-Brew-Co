@php
    use App\Models\ContactEnquiry;
    use App\Support\BusinessTime;
@endphp

<x-layouts.app title="Customer Enquiries">
    <div class="space-y-6">
        @include('admin.email.tabs')

        <form method="GET" action="{{ route('admin.email.enquiries') }}" class="abc-panel p-4 flex flex-wrap items-end gap-3">
            <div class="min-w-[180px] space-y-1">
                <label for="enq-status" class="block text-xs font-medium text-[#BFB4A5]">Status Filter</label>
                <select id="enq-status" name="status" class="abc-input text-sm py-2">
                    <option value="">All enquiries</option>
                    <option value="{{ ContactEnquiry::STATUS_RECEIVED }}" @selected($statusFilter === ContactEnquiry::STATUS_RECEIVED)>Received</option>
                    <option value="{{ ContactEnquiry::STATUS_IN_PROGRESS }}" @selected($statusFilter === ContactEnquiry::STATUS_IN_PROGRESS)>In Progress</option>
                    <option value="{{ ContactEnquiry::STATUS_RESOLVED }}" @selected($statusFilter === ContactEnquiry::STATUS_RESOLVED)>Resolved</option>
                </select>
            </div>
            <button type="submit" class="abc-btn-primary py-2 px-4 text-xs">Filter</button>
            <a href="{{ route('admin.email.enquiries') }}" class="abc-btn-secondary py-2 px-3 text-xs">Reset</a>
        </form>

        @if($enquiries->isEmpty())
            <x-empty-state title="No enquiries found" description="Submitted contact form enquiries appear here automatically." />
        @else
            <div class="space-y-4">
                @foreach($enquiries as $enquiry)
                    @php
                        $tone = match ($enquiry->status) {
                            ContactEnquiry::STATUS_RESOLVED => 'success',
                            ContactEnquiry::STATUS_IN_PROGRESS => 'accent',
                            default => 'neutral',
                        };
                    @endphp
                    <div class="abc-panel p-5 space-y-4">
                        <div class="flex flex-col sm:flex-row sm:items-start justify-between gap-3">
                            <div class="space-y-1">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="font-mono text-xs text-[#D2B077]">{{ $enquiry->reference_code }}</span>
                                    <x-status-badge :tone="$tone">{{ str_replace('_', ' ', ucfirst($enquiry->status)) }}</x-status-badge>
                                </div>
                                <h2 class="font-display text-2xl font-semibold text-[#F4EEE5]">{{ $enquiry->subject }}</h2>
                                <p class="text-xs text-[#BFB4A5]">
                                    From <strong class="text-[#F4EEE5]">{{ $enquiry->name }}</strong> ({{ $enquiry->email }}) · {{ BusinessTime::format($enquiry->created_at) }}
                                </p>
                            </div>
                        </div>

                        <div class="abc-surface p-4 text-sm text-[#F4EEE5] whitespace-pre-line break-words">{{ $enquiry->message }}</div>

                        <form method="POST" action="{{ route('admin.email.enquiries.update', $enquiry) }}" class="grid grid-cols-1 md:grid-cols-12 gap-3 items-end pt-2 border-t border-white/10">
                            @csrf
                            @method('PATCH')
                            <div class="md:col-span-3 space-y-1">
                                <label class="block text-xs font-medium text-[#BFB4A5]">Status</label>
                                <select name="status" class="abc-input text-xs py-2">
                                    <option value="{{ ContactEnquiry::STATUS_RECEIVED }}" @selected($enquiry->status === ContactEnquiry::STATUS_RECEIVED)>Received</option>
                                    <option value="{{ ContactEnquiry::STATUS_IN_PROGRESS }}" @selected($enquiry->status === ContactEnquiry::STATUS_IN_PROGRESS)>In Progress</option>
                                    <option value="{{ ContactEnquiry::STATUS_RESOLVED }}" @selected($enquiry->status === ContactEnquiry::STATUS_RESOLVED)>Resolved</option>
                                </select>
                            </div>
                            <div class="md:col-span-7 space-y-1">
                                <label class="block text-xs font-medium text-[#BFB4A5]">Internal Follow-up Notes</label>
                                <input name="admin_notes" type="text" value="{{ $enquiry->admin_notes }}" class="abc-input text-xs py-2" placeholder="Staff response notes or booking confirmation details...">
                            </div>
                            <div class="md:col-span-2">
                                <button type="submit" class="abc-btn-primary w-full py-2 text-xs">Update</button>
                            </div>
                        </form>
                    </div>
                @endforeach
            </div>

            <div>{{ $enquiries->links() }}</div>
        @endif
    </div>
</x-layouts.app>
