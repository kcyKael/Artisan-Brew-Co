@props([
    'preheader' => '',
    'heading' => 'Artisan Brew Co.',
])
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $heading }} — Artisan Brew Co.</title>
</head>
<body style="margin:0;padding:0;background-color:#EFEAE2;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;color:#1D1B18;-webkit-font-smoothing:antialiased;">
    @if(filled($preheader))
        <div style="display:none;max-height:0;overflow:hidden;opacity:0;color:transparent;font-size:1px;line-height:1px;">
            {{ $preheader }}
        </div>
    @endif
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#EFEAE2;padding:24px 12px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="max-width:600px;background-color:#FBF9F6;border:1px solid #E2D9CC;border-radius:12px;overflow:hidden;">
                    <tr>
                        <td style="background-color:#161412;padding:22px 28px;border-bottom:2px solid #D2B077;">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                                <tr>
                                    <td valign="middle" style="width:44px;">
                                        <img src="{{ asset('images/abc-logo.png') }}" alt="ABC" width="38" height="38" style="display:block;border:0;outline:none;text-decoration:none;border-radius:6px;">
                                    </td>
                                    <td valign="middle" style="padding-left:12px;">
                                        <div style="font-size:10px;letter-spacing:2px;text-transform:uppercase;color:#D2B077;font-weight:700;">Artisan Brew Co.</div>
                                        <div style="font-family:Georgia,'Times New Roman',serif;font-size:20px;line-height:24px;color:#F4EEE5;font-weight:600;">{{ $heading }}</div>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:28px;font-size:14px;line-height:22px;color:#24201C;">
                            {{ $slot }}
                        </td>
                    </tr>
                    <tr>
                        <td style="background-color:#F5F0E8;padding:18px 28px;border-top:1px solid #E6DEC8;font-size:12px;line-height:18px;color:#6E655A;">
                            <div style="font-weight:600;color:#2C2620;">{{ config('pos.brand_name', 'Artisan Brew Co.') }}</div>
                            <div>{{ config('pos.receipt_footer', 'Thank you for brewing with Artisan Brew Co.') }} · Asia/Manila</div>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
