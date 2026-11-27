@php
    $tabs = [
        ['route' => 'admin.email.settings', 'label' => 'Settings & Previews', 'match' => 'admin.email.settings'],
        ['route' => 'admin.email.deliveries', 'label' => 'Deliveries & Webhooks', 'match' => 'admin.email.deliveries'],
        ['route' => 'admin.email.enquiries', 'label' => 'Enquiries', 'match' => 'admin.email.enquiries'],
        ['route' => 'admin.email.subscribers', 'label' => 'Subscribers', 'match' => 'admin.email.subscribers'],
        ['route' => 'admin.email.campaigns', 'label' => 'Campaigns', 'match' => 'admin.email.campaigns'],
    ];
@endphp

<div class="space-y-4">
    <div class="flex flex-col sm:flex-row sm:items-end justify-between gap-4">
        <div>
            <div class="text-xs uppercase tracking-[0.2em] text-[#D2B077]">Brevo HTTPS Email Platform</div>
            <h1 class="font-display text-3xl sm:text-4xl font-semibold text-[#F4EEE5]">Email Center</h1>
            <p class="text-sm text-[#BFB4A5] mt-1">
                Manage sender settings, queue outbox deliveries, customer enquiries, double opt-in subscribers, and seasonal campaigns.
            </p>
        </div>
    </div>

    <nav aria-label="Email Center sections" class="flex flex-wrap gap-2 border-b border-white/10 pb-3">
        @foreach($tabs as $tab)
            @php $active = request()->routeIs($tab['match']); @endphp
            <a href="{{ route($tab['route']) }}"
               class="{{ $active ? 'abc-btn-primary' : 'abc-btn-secondary' }} py-2 px-3.5 text-xs">
                {{ $tab['label'] }}
            </a>
        @endforeach
    </nav>
</div>
