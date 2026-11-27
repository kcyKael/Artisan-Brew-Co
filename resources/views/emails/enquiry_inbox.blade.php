<x-emails.layout heading="New Contact Enquiry · {{ $referenceCode }}" preheader="{{ $enquirySubject }} from {{ $visitorName }}.">
    <p style="margin:0 0 14px 0;">A visitor submitted an enquiry on the Artisan Brew Co. landing page.</p>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-bottom:18px;background-color:#F6F2EB;border:1px solid #E6DEC8;border-radius:8px;">
        <tr>
            <td style="padding:14px 16px;font-size:13px;line-height:20px;">
                <div><strong>Reference:</strong> {{ $referenceCode }}</div>
                <div><strong>From:</strong> {{ $visitorName }} &lt;{{ $visitorEmail }}&gt;</div>
                <div><strong>Subject:</strong> {{ $enquirySubject }}</div>
                <div><strong>Submitted:</strong> {{ $submittedAt }}</div>
            </td>
        </tr>
    </table>

    <div style="padding:14px 16px;background-color:#FFFFFF;border:1px solid #E2D9CC;border-radius:8px;font-size:14px;line-height:22px;white-space:pre-line;margin-bottom:18px;">{{ $enquiryMessage }}</div>

    <table role="presentation" cellpadding="0" cellspacing="0" border="0">
        <tr>
            <td style="background-color:#161412;border-radius:8px;">
                <a href="{{ $adminUrl }}" style="display:inline-block;padding:11px 20px;color:#D2B077;text-decoration:none;font-size:13px;font-weight:600;">
                    Open Enquiries Inbox
                </a>
            </td>
        </tr>
    </table>
</x-emails.layout>
