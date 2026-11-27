<x-emails.layout heading="Account Security Notice" preheader="{{ $eventTitle }} on your Artisan Brew Co. account.">
    <p style="margin:0 0 14px 0;">Hello {{ $fullName }},</p>
    <p style="margin:0 0 16px 0;">{{ $eventSummary }}</p>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-bottom:20px;background-color:#F6F2EB;border:1px solid #E6DEC8;border-radius:8px;">
        <tr>
            <td style="padding:14px 16px;font-size:13px;line-height:20px;">
                <div><strong>Event:</strong> {{ $eventTitle }}</div>
                <div><strong>Time:</strong> {{ $occurredAt }}</div>
            </td>
        </tr>
    </table>

    @if(!empty($actionUrl))
        <table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin-bottom:16px;">
            <tr>
                <td style="background-color:#161412;border-radius:8px;">
                    <a href="{{ $actionUrl }}" style="display:inline-block;padding:11px 20px;color:#D2B077;text-decoration:none;font-size:13px;font-weight:600;">
                        {{ $actionLabel ?? 'Open Staff Sign In' }}
                    </a>
                </td>
            </tr>
        </table>
    @endif

    <p style="margin:0;font-size:12px;color:#6E655A;">
        If you did not authorize this change, contact an Artisan Brew Co. administrator immediately.
    </p>
</x-emails.layout>
