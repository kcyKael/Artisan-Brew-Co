<x-emails.layout heading="Welcome to the Team" preheader="Activate your Artisan Brew Co. staff account.">
    <p style="margin:0 0 14px 0;">Hello {{ $fullName }},</p>
    <p style="margin:0 0 14px 0;">
        {{ $inviterName }} has invited you to join the <strong>Artisan Brew Co.</strong> counter team as <strong>{{ $roleLabel }}</strong> (username: <code>{{ $username }}</code>).
    </p>
    <p style="margin:0 0 20px 0;">Choose your password below to activate your account and sign in.</p>

    <table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin-bottom:20px;">
        <tr>
            <td style="background-color:#161412;border-radius:8px;border:1px solid #D2B077;">
                <a href="{{ $acceptUrl }}" style="display:inline-block;padding:12px 24px;color:#D2B077;text-decoration:none;font-size:14px;font-weight:600;">
                    Set Password &amp; Activate Account
                </a>
            </td>
        </tr>
    </table>

    <p style="margin:0;font-size:13px;color:#6E655A;">
        This invitation link is single-use and expires in {{ $expireHours }} hours.
    </p>
</x-emails.layout>
