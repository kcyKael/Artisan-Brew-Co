<x-emails.layout heading="Staff Password Recovery" preheader="Reset your Artisan Brew Co. staff password.">
    <p style="margin:0 0 14px 0;">Hello {{ $fullName }},</p>
    <p style="margin:0 0 18px 0;">A password reset was requested for your Artisan Brew Co. staff account. Use the button below to choose a new password.</p>

    <table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin-bottom:20px;">
        <tr>
            <td style="background-color:#161412;border-radius:8px;border:1px solid #D2B077;">
                <a href="{{ $resetUrl }}" style="display:inline-block;padding:12px 24px;color:#D2B077;text-decoration:none;font-size:14px;font-weight:600;">
                    Reset Staff Password
                </a>
            </td>
        </tr>
    </table>

    <p style="margin:0 0 10px 0;font-size:13px;color:#6E655A;">
        This link is single-use and expires in {{ $expireMinutes }} minutes.
    </p>
    <p style="margin:0;font-size:13px;color:#6E655A;">
        If you did not request a reset, you can safely ignore this message; your password will remain unchanged.
    </p>
</x-emails.layout>
