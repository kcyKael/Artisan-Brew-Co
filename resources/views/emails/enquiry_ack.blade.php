<x-emails.layout heading="We Received Your Message" preheader="Enquiry reference {{ $referenceCode }} at Artisan Brew Co.">
    <p style="margin:0 0 14px 0;">Hello {{ $visitorName }},</p>
    <p style="margin:0 0 16px 0;">
        Thank you for reaching out to <strong>Artisan Brew Co.</strong> Your message has been recorded and shared with our studio team.
    </p>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-bottom:16px;background-color:#F6F2EB;border:1px solid #E6DEC8;border-radius:8px;">
        <tr>
            <td style="padding:14px 16px;font-size:13px;line-height:20px;">
                <div><strong>Reference Code:</strong> {{ $referenceCode }}</div>
                <div><strong>Subject:</strong> {{ $enquirySubject }}</div>
                <div><strong>Received:</strong> {{ $submittedAt }}</div>
            </td>
        </tr>
    </table>

    <p style="margin:0;font-size:13px;color:#6E655A;">
        Please keep your reference code for follow-up. Our team typically responds within one to two business days during cafe hours.
    </p>
</x-emails.layout>
