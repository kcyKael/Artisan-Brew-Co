@php
    use App\Support\BusinessTime;
    use App\Support\Money;

    $orderTypeLabel = ($order->order_type ?? 'dine_in') === 'take_out' ? 'Take-out' : 'Dine-in';
    $paymentLabel = match ($order->payment_method ?? 'cash') {
        'card' => 'Card',
        'gcash' => 'GCash',
        default => 'Cash',
    };
    $hasVoidErrors = $errors->hasAny(['admin_username', 'admin_password', 'void_reason', 'status']);
@endphp

<x-layouts.app title="Order {{ $order->order_number }}">
    <div class="max-w-4xl mx-auto space-y-6">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <div class="flex flex-wrap items-center gap-2.5">
                    <h1 class="font-display text-3xl sm:text-4xl font-semibold text-[#F4EEE5] tabular-nums">Order {{ $order->order_number }}</h1>
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-white/5 text-[#BFB4A5] border border-white/10">{{ $orderTypeLabel }}</span>
                    @if($order->status === 'voided')
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-[#E07A7A]/15 text-[#E07A7A] border border-[#E07A7A]/30">Voided</span>
                    @endif
                </div>
                <p class="text-sm text-[#BFB4A5] mt-1">
                    Completed {{ BusinessTime::format($order->created_at) }} (Asia/Manila) · Channel: {{ $order->source === 'record_sale' ? 'Record Sale' : 'POS Terminal' }}
                </p>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('sales.index') }}" class="abc-btn-secondary">Back to Sales History</a>
                <a href="{{ route('orders.receipt', $order) }}" class="abc-btn-primary">Print / View Receipt</a>
                @if($order->status === 'completed')
                    <button type="button" data-open-void-dialog class="abc-btn-danger">
                        {{ auth()->user()->isAdmin() ? 'Void Order' : 'Void Order (Manager Approval)' }}
                    </button>
                @endif
            </div>
        </div>

        @if($order->status === 'voided')
            <div class="abc-panel p-4 border-[#E07A7A]/40 bg-[#E07A7A]/10 space-y-1.5">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <span class="text-sm font-semibold uppercase tracking-wider text-[#E07A7A]">Order Voided</span>
                    @if($order->voided_at)
                        <span class="text-xs text-[#BFB4A5]">{{ BusinessTime::format($order->voided_at) }} (Asia/Manila)</span>
                    @endif
                </div>
                <div class="text-sm text-[#F4EEE5]">
                    Approved by: <span class="font-medium">{{ $order->voidedBy?->full_name ?? 'Admin' }}</span>
                </div>
                @if(filled($order->void_reason))
                    <div class="text-sm text-[#BFB4A5]">
                        Reason: <span class="text-[#F4EEE5]">{{ $order->void_reason }}</span>
                    </div>
                @endif
            </div>
        @endif

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div class="abc-panel p-4 space-y-1">
                <div class="text-xs uppercase tracking-wider text-[#BFB4A5]">Customer &amp; Service</div>
                <div class="text-base font-semibold text-[#F4EEE5]">{{ $order->customer_name_snapshot }}</div>
                @if($order->customer)
                    <div class="text-xs text-[#BFB4A5]">{{ $order->customer->displayIdentifier() }}</div>
                @endif
                <div class="text-xs text-[#BFB4A5] pt-1">
                    Order Type: <span class="text-[#F4EEE5] font-medium">{{ $orderTypeLabel }}</span>
                </div>
                @if(filled($order->cup_name))
                    <div class="text-xs text-[#BFB4A5]">
                        Cup Name: <span class="text-[#D2B077] font-medium">{{ $order->cup_name }}</span>
                    </div>
                @endif
                @if(filled($order->order_note))
                    <div class="text-xs text-[#BFB4A5]">
                        Order Note: <span class="text-[#F4EEE5]">{{ $order->order_note }}</span>
                    </div>
                @endif
            </div>

            <div class="abc-panel p-4 space-y-1">
                <div class="text-xs uppercase tracking-wider text-[#BFB4A5]">Processed By</div>
                <div class="text-base font-semibold text-[#F4EEE5]">{{ $order->staff_name_snapshot }}</div>
                @if($order->staff)
                    <div class="text-xs text-[#BFB4A5]">{{ ucfirst($order->staff->role) }} · {{ $order->staff->username }}</div>
                @endif
            </div>

            <div class="abc-panel p-4 space-y-1">
                <div class="text-xs uppercase tracking-wider text-[#BFB4A5]">{{ $paymentLabel }} Payment</div>
                <div class="text-xl font-semibold text-[#D2B077] tabular-nums">{{ Money::formatPhp($order->total_amount) }}</div>
                <div class="text-xs text-[#BFB4A5] tabular-nums">
                    Subtotal: {{ Money::formatPhp($order->subtotal_amount) }}
                </div>
                @if((float) $order->discount_amount > 0)
                    <div class="text-xs text-[#9CAF94] tabular-nums">
                        {{ $order->discount_label ?: 'Discount' }}: -{{ Money::formatPhp($order->discount_amount) }}
                    </div>
                @endif
                @if(filled($order->payment_reference))
                    <div class="text-xs text-[#BFB4A5]">
                        Ref: <span class="text-[#F4EEE5]">{{ $order->payment_reference }}</span>
                    </div>
                @endif
                @if(($order->payment_method ?? 'cash') === 'cash')
                    <div class="text-xs text-[#BFB4A5] tabular-nums">
                        Cash: {{ Money::formatPhp($order->cash_received) }} · Change: {{ Money::formatPhp($order->change_amount) }}
                    </div>
                @endif
            </div>
        </div>

        <div class="abc-panel overflow-hidden">
            <div class="px-5 py-4 border-b border-white/10">
                <h2 class="font-display text-2xl font-semibold text-[#F4EEE5]">Line Items (Historical Snapshot)</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="abc-table">
                    <thead>
                        <tr>
                            <th>Product &amp; Options</th>
                            <th class="text-right">Unit Price</th>
                            <th class="text-right">Qty</th>
                            <th class="text-right">Line Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($order->sales as $sale)
                            <tr>
                                <td>
                                    <div class="font-medium text-[#F4EEE5]">{{ $sale->product_name_snapshot }}</div>
                                    @if($sale->variant_label_snapshot)
                                        <div class="text-xs text-[#D2B077]">{{ $sale->variant_label_snapshot }}</div>
                                    @endif
                                    @if($sale->saleAddons->isNotEmpty())
                                        <div class="text-xs text-[#BFB4A5]">
                                            Add-ons:
                                            @foreach($sale->saleAddons as $addon)
                                                {{ $addon->addon_name_snapshot }} ({{ Money::formatPhp($addon->addon_price_snapshot) }})@unless($loop->last), @endunless
                                            @endforeach
                                        </div>
                                    @endif
                                    @if(filled($sale->note))
                                        <div class="text-xs text-[#BFB4A5] italic mt-0.5">Note: {{ $sale->note }}</div>
                                    @endif
                                </td>
                                <td class="text-right tabular-nums">{{ Money::formatPhp($sale->unit_price) }}</td>
                                <td class="text-right tabular-nums">{{ $sale->quantity }}</td>
                                <td class="text-right font-semibold tabular-nums">{{ Money::formatPhp($sale->total_price) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    @if($order->status === 'completed')
        <dialog id="void-order-dialog" data-auto-open="{{ ($hasVoidErrors || request()->boolean('void')) ? 'true' : 'false' }}" class="abc-panel p-0 w-full max-w-md m-auto text-[#F4EEE5] backdrop:bg-black/75">
            <form method="POST" action="{{ route('orders.void', $order) }}" class="p-6 space-y-4">
                @csrf
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h2 class="font-display text-2xl font-semibold text-[#F4EEE5]">
                            {{ auth()->user()->isAdmin() ? 'Void Order' : 'Manager Approval Required' }}
                        </h2>
                        <p class="text-xs text-[#BFB4A5] mt-1">
                            Voiding Order {{ $order->order_number }} restores item stock and any gift card balance.
                        </p>
                    </div>
                    <button type="button" data-close-void-dialog class="text-[#BFB4A5] hover:text-[#F4EEE5] text-lg leading-none" aria-label="Close">&times;</button>
                </div>

                @error('status')
                    <div class="p-3 rounded-lg bg-[#E07A7A]/15 border border-[#E07A7A]/35 text-xs text-[#E07A7A]">
                        {{ $message }}
                    </div>
                @enderror

                @if(! auth()->user()->isAdmin())
                    <div class="space-y-1">
                        <label for="void-admin-username" class="block text-xs font-medium text-[#BFB4A5]">Manager Username</label>
                        <input id="void-admin-username" name="admin_username" type="text" value="{{ old('admin_username') }}" required autocomplete="off" class="abc-input text-sm py-2">
                        @error('admin_username')
                            <p class="text-xs text-[#E07A7A]">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="space-y-1">
                        <label for="void-admin-password" class="block text-xs font-medium text-[#BFB4A5]">Manager Password</label>
                        <input id="void-admin-password" name="admin_password" type="password" required autocomplete="new-password" class="abc-input text-sm py-2">
                        @error('admin_password')
                            <p class="text-xs text-[#E07A7A]">{{ $message }}</p>
                        @enderror
                    </div>
                @endif

                <div class="space-y-1">
                    <label for="void-reason" class="block text-xs font-medium text-[#BFB4A5]">Reason for Void</label>
                    <input id="void-reason" name="void_reason" type="text" value="{{ old('void_reason') }}" required maxlength="255" placeholder="e.g. Customer cancelled order" class="abc-input text-sm py-2">
                    @error('void_reason')
                        <p class="text-xs text-[#E07A7A]">{{ $message }}</p>
                    @enderror
                </div>

                <div class="flex items-center justify-end gap-2 pt-2">
                    <button type="button" data-close-void-dialog class="abc-btn-secondary">Cancel</button>
                    <button type="submit" class="abc-btn-danger">
                        {{ auth()->user()->isAdmin() ? 'Confirm Void Order' : 'Authorize & Void Order' }}
                    </button>
                </div>
            </form>
        </dialog>
    @endif
</x-layouts.app>
