<x-emails.layout heading="Daily Sales Summary · {{ $businessDate }}" preheader="Completed sales report: {{ $revenueFormatted }} across {{ $orderCount }} orders.">
    <p style="margin:0 0 14px 0;">
        Automated closing summary covering <strong>{{ $periodLabel }}</strong>. Only completed orders are included.
    </p>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-bottom:20px;background-color:#F6F2EB;border:1px solid #E6DEC8;border-radius:8px;">
        <tr>
            <td width="50%" style="padding:14px 16px;border-bottom:1px solid #E6DEC8;border-right:1px solid #E6DEC8;">
                <div style="font-size:11px;text-transform:uppercase;color:#6E655A;">Completed Revenue</div>
                <div style="font-size:20px;font-weight:700;color:#1D1B18;margin-top:4px;">{{ $revenueFormatted }}</div>
            </td>
            <td width="50%" style="padding:14px 16px;border-bottom:1px solid #E6DEC8;">
                <div style="font-size:11px;text-transform:uppercase;color:#6E655A;">Completed Orders</div>
                <div style="font-size:20px;font-weight:700;color:#1D1B18;margin-top:4px;">{{ $orderCount }}</div>
            </td>
        </tr>
        <tr>
            <td width="50%" style="padding:14px 16px;border-right:1px solid #E6DEC8;">
                <div style="font-size:11px;text-transform:uppercase;color:#6E655A;">Units Sold</div>
                <div style="font-size:18px;font-weight:700;color:#1D1B18;margin-top:4px;">{{ $unitsSold }}</div>
            </td>
            <td width="50%" style="padding:14px 16px;">
                <div style="font-size:11px;text-transform:uppercase;color:#6E655A;">Average Order</div>
                <div style="font-size:18px;font-weight:700;color:#1D1B18;margin-top:4px;">{{ $averageOrderFormatted }}</div>
            </td>
        </tr>
    </table>

    <div style="font-size:13px;font-weight:700;text-transform:uppercase;letter-spacing:1px;color:#6E655A;margin-bottom:8px;">Top Sellers (Period)</div>
    @if(!empty($bestSellers))
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="border-collapse:collapse;margin-bottom:20px;">
            @foreach($bestSellers as $item)
                <tr>
                    <td style="padding:7px 0;border-bottom:1px solid #EDE6D9;font-size:13px;">{{ $item['name'] }}</td>
                    <td align="right" style="padding:7px 8px;border-bottom:1px solid #EDE6D9;font-size:13px;color:#6E655A;">{{ $item['units'] }} sold</td>
                    <td align="right" style="padding:7px 0;border-bottom:1px solid #EDE6D9;font-size:13px;font-weight:600;">{{ $item['revenueFormatted'] }}</td>
                </tr>
            @endforeach
        </table>
    @else
        <p style="margin:0 0 18px 0;font-size:13px;color:#6E655A;">No completed sales recorded in this reporting window.</p>
    @endif

    <div style="font-size:13px;font-weight:700;text-transform:uppercase;letter-spacing:1px;color:#6E655A;margin-bottom:8px;">Current Stock Alerts Snapshot</div>
    @if(!empty($stockAlerts))
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="border-collapse:collapse;margin-bottom:20px;">
            @foreach($stockAlerts as $alert)
                <tr>
                    <td style="padding:7px 0;border-bottom:1px solid #EDE6D9;font-size:13px;">{{ $alert['name'] }}</td>
                    <td align="right" style="padding:7px 8px;border-bottom:1px solid #EDE6D9;font-size:13px;">{{ $alert['stock'] }} left (min {{ $alert['threshold'] }})</td>
                    <td align="right" style="padding:7px 0;border-bottom:1px solid #EDE6D9;font-size:12px;font-weight:700;color:{{ $alert['state'] === 'Sold out' ? '#B33B3B' : '#9E621C' }};">{{ $alert['state'] }}</td>
                </tr>
            @endforeach
        </table>
    @else
        <p style="margin:0 0 18px 0;font-size:13px;color:#4B6B46;">All active products are currently above their low-stock thresholds.</p>
    @endif

    <table role="presentation" cellpadding="0" cellspacing="0" border="0">
        <tr>
            <td style="background-color:#161412;border-radius:8px;">
                <a href="{{ $dashboardUrl }}" style="display:inline-block;padding:11px 20px;color:#D2B077;text-decoration:none;font-size:13px;font-weight:600;">
                    Open Sales Dashboard
                </a>
            </td>
        </tr>
    </table>
</x-emails.layout>
