<x-emails.layout heading="{{ $subject }}" preheader="{{ $previewText ?? '' }}">
    <div style="font-size:15px;line-height:24px;color:#24201C;margin-bottom:22px;">
        {!! $bodyHtml !!}
    </div>

    <table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin-bottom:24px;">
        <tr>
            <td style="background-color:#161412;border-radius:8px;border:1px solid #D2B077;">
                <a href="{{ $menuUrl }}" style="display:inline-block;padding:12px 24px;color:#D2B077;text-decoration:none;font-size:14px;font-weight:600;">
                    Explore the Full Menu
                </a>
            </td>
        </tr>
    </table>

    <div style="border-top:1px solid #E6DEC8;padding-top:14px;font-size:12px;color:#6E655A;">
        You are receiving this promotional announcement because you confirmed your subscription with Artisan Brew Co.
        <div style="margin-top:6px;">
            <a href="{{ $unsubscribePlaceholder ?? ('{' . '{ unsubscribe }' . '}') }}" style="color:#7C5D33;text-decoration:underline;">Unsubscribe from promotional emails</a>
            @if(!empty($preferencesUrl))
                &nbsp;·&nbsp;
                <a href="{{ $preferencesUrl }}" style="color:#7C5D33;text-decoration:underline;">Manage preferences</a>
            @endif
        </div>
    </div>
</x-emails.layout>
