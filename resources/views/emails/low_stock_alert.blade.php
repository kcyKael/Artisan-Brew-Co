<x-emails.layout heading="Inventory Attention Required" preheader="Low stock or sold-out items detected at Artisan Brew Co.">
    <p style="margin:0 0 12px 0;">
        @if(($mode ?? 'immediate') === 'digest')
            Here is your scheduled inventory digest of active items at or below threshold ({{ $alertTime }}).
        @else
            The following active menu {{ count($items) === 1 ? 'item crossed' : 'items crossed' }} a stock alert threshold ({{ $alertTime }}).
        @endif
    </p>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="border-collapse:collapse;margin-bottom:20px;">
        <tr>
            <th align="left" style="padding:8px 0;border-bottom:1px solid #D9CFC0;font-size:12px;text-transform:uppercase;color:#6E655A;">Product</th>
            <th align="right" style="padding:8px 0;border-bottom:1px solid #D9CFC0;font-size:12px;text-transform:uppercase;color:#6E655A;">Stock</th>
            <th align="right" style="padding:8px 0;border-bottom:1px solid #D9CFC0;font-size:12px;text-transform:uppercase;color:#6E655A;">Threshold</th>
            <th align="right" style="padding:8px 0;border-bottom:1px solid #D9CFC0;font-size:12px;text-transform:uppercase;color:#6E655A;">Status</th>
        </tr>
        @foreach($items as $item)
            <tr>
                <td style="padding:10px 0;border-bottom:1px solid #EDE6D9;font-size:13px;">
                    <strong>{{ $item['name'] }}</strong>
                    @if(!empty($item['sku']))
                        <div style="font-size:11px;color:#6E655A;">{{ $item['sku'] }}</div>
                    @endif
                </td>
                <td align="right" style="padding:10px 6px;border-bottom:1px solid #EDE6D9;font-size:13px;font-weight:600;">{{ $item['stock'] }}</td>
                <td align="right" style="padding:10px 6px;border-bottom:1px solid #EDE6D9;font-size:13px;color:#6E655A;">{{ $item['threshold'] }}</td>
                <td align="right" style="padding:10px 0;border-bottom:1px solid #EDE6D9;font-size:12px;font-weight:700;color:{{ $item['state'] === 'Sold out' ? '#B33B3B' : '#9E621C' }};">
                    {{ $item['state'] }}
                </td>
            </tr>
        @endforeach
    </table>

    <table role="presentation" cellpadding="0" cellspacing="0" border="0">
        <tr>
            <td style="background-color:#161412;border-radius:8px;">
                <a href="{{ $productsUrl }}" style="display:inline-block;padding:11px 20px;color:#D2B077;text-decoration:none;font-size:13px;font-weight:600;">
                    Open Menu &amp; Stock Console
                </a>
            </td>
        </tr>
    </table>
</x-emails.layout>
