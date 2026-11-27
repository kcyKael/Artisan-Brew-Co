<x-emails.layout heading="Confirm Your Subscription" preheader="Confirm your Artisan Brew Co. updates & new-drink notes.">
    <p style="margin:0 0 14px 0;">Hello{{ !empty($subscriberName) ? ' ' . $subscriberName : '' }},</p>
    <p style="margin:0 0 18px 0;">
        Please confirm that you would like to receive seasonal pours, new drink announcements, and occasional updates from <strong>Artisan Brew Co.</strong>
    </p>

    <table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin-bottom:18px;">
        <tr>
            <td style="background-color:#161412;border-radius:8px;border:1px solid #D2B077;">
                <a href="{{ $confirmUrl }}" style="display:inline-block;padding:12px 24px;color:#D2B077;text-decoration:none;font-size:14px;font-weight:600;">
                    Confirm Newsletter Subscription
                </a>
            </td>
        </tr>
    </table>

    <p style="margin:0;font-size:12px;color:#6E655A;">
        This confirmation link expires in {{ $expireHours }} hours (consent notice {{ $noticeVersion }}). If you did not request this, no further emails will be sent.
    </p>
</x-emails.layout>
