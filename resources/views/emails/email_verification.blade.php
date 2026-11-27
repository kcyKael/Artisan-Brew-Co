<x-emails.layout heading="Confirm Your Staff Email" preheader="Verify your new Artisan Brew Co. staff email address.">
    <p style="margin:0 0 14px 0;">Hello {{ $fullName }},</p>
    <p style="margin:0 0 18px 0;">
        Please verify <strong>{{ $pendingEmail }}</strong> as your new Artisan Brew Co. staff email address before it is used for account recovery and notifications.
    </p>

    <table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin-bottom:18px;">
        <tr>
            <td style="background-color:#161412;border-radius:8px;border:1px solid #D2B077;">
                <a href="{{ $verifyUrl }}" style="display:inline-block;padding:12px 24px;color:#D2B077;text-decoration:none;font-size:14px;font-weight:600;">
                    Confirm Email Address
                </a>
            </td>
        </tr>
    </table>

    <p style="margin:0;font-size:13px;color:#6E655A;">
        This link expires in {{ $expireHours }} hours. Your username login continues to work normally.
    </p>
</x-emails.layout>
