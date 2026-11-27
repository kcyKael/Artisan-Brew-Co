<x-emails.layout heading="Order Receipt #{{ $orderNumber }}" preheader="Your Artisan Brew Co. receipt for {{ $totalFormatted }}.">
    <p style="margin:0 0 14px 0;">Thank you for visiting <strong>Artisan Brew Co.</strong> Here is your receipt summary.</p>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-bottom:18px;background-color:#F6F2EB;border:1px solid #E6DEC8;border-radius:8px;">
        <tr>
            <td style="padding:14px 16px;font-size:13px;line-height:20px;color:#3A332B;">
                <div><strong>Order:</strong> {{ $orderNumber }}</div>
                <div><strong>Date:</strong> {{ $purchasedAt }}</div>
                <div><strong>Service:</strong> {{ $orderTypeLabel }} · <strong>Payment:</strong> {{ $paymentLabel }}</div>
                <div><strong>Customer:</strong> {{ $customerName }}</div>
                @if(!empty($cupName))
                    <div><strong>Cup Name:</strong> {{ $cupName }}</div>
                @endif
                @if(!empty($orderNote))
                    <div><strong>Order Note:</strong> {{ $orderNote }}</div>
                @endif
            </td>
        </tr>
    </table>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="border-collapse:collapse;margin-bottom:18px;">
        <tr>
            <th align="left" style="padding:8px 0;border-bottom:1px solid #D9CFC0;font-size:12px;text-transform:uppercase;color:#6E655A;">Item</th>
            <th align="right" style="padding:8px 0;border-bottom:1px solid #D9CFC0;font-size:12px;text-transform:uppercase;color:#6E655A;">Qty</th>
            <th align="right" style="padding:8px 0;border-bottom:1px solid #D9CFC0;font-size:12px;text-transform:uppercase;color:#6E655A;">Total</th>
        </tr>
        @foreach($items as $item)
            <tr>
                <td style="padding:10px 0;border-bottom:1px solid #EDE6D9;font-size:13px;color:#1D1B18;">
                    <strong>{{ $item['name'] }}</strong>
                    @if(!empty($item['variant']))
                        <div style="font-size:12px;color:#6E655A;">{{ $item['variant'] }}</div>
                    @endif
                    @if(!empty($item['addons']))
                        <div style="font-size:12px;color:#6E655A;">Add-ons: {{ $item['addons'] }}</div>
                    @endif
                    @if(!empty($item['note']))
                        <div style="font-size:12px;color:#7C5D33;">Note: {{ $item['note'] }}</div>
                    @endif
                    <div style="font-size:11px;color:#6E655A;">Unit: {{ $item['unitPriceFormatted'] }}</div>
                </td>
                <td align="right" valign="top" style="padding:10px 8px;border-bottom:1px solid #EDE6D9;font-size:13px;">{{ $item['quantity'] }}</td>
                <td align="right" valign="top" style="padding:10px 0;border-bottom:1px solid #EDE6D9;font-size:13px;font-weight:600;">{{ $item['lineTotalFormatted'] }}</td>
            </tr>
        @endforeach
    </table>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-bottom:20px;font-size:13px;">
        <tr>
            <td style="padding:4px 0;color:#6E655A;">Subtotal</td>
            <td align="right" style="padding:4px 0;">{{ $subtotalFormatted }}</td>
        </tr>
        @if(!empty($discountLabel))
            <tr>
                <td style="padding:4px 0;color:#4B6B46;">Discount ({{ $discountLabel }})</td>
                <td align="right" style="padding:4px 0;color:#4B6B46;">-{{ $discountFormatted }}</td>
            </tr>
        @endif
        <tr>
            <td style="padding:8px 0 4px 0;border-top:1px solid #D9CFC0;font-size:16px;font-weight:700;">Total Paid</td>
            <td align="right" style="padding:8px 0 4px 0;border-top:1px solid #D9CFC0;font-size:16px;font-weight:700;">{{ $totalFormatted }}</td>
        </tr>
        <tr>
            <td style="padding:3px 0;color:#6E655A;">Tendered / Change</td>
            <td align="right" style="padding:3px 0;color:#6E655A;">{{ $cashFormatted }} / {{ $changeFormatted }}</td>
        </tr>
    </table>

    @if(!empty($signedReceiptUrl))
        <table role="presentation" cellpadding="0" cellspacing="0" border="0">
            <tr>
                <td style="background-color:#161412;border-radius:8px;">
                    <a href="{{ $signedReceiptUrl }}" style="display:inline-block;padding:12px 22px;color:#D2B077;text-decoration:none;font-size:13px;font-weight:600;">
                        View Web Receipt
                    </a>
                </td>
            </tr>
        </table>
        <p style="margin:10px 0 0 0;font-size:12px;color:#6E655A;">This secure link shows only this receipt and expires automatically.</p>
    @endif
</x-emails.layout>
