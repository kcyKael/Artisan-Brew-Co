<?php

namespace App\Services\Email;

use App\Models\ContactEnquiry;
use App\Models\User;
use App\Support\BusinessTime;
use Illuminate\Support\Str;

class ContactEnquiryService
{
    public function __construct(
        private readonly TransactionalEmailService $transactional,
        private readonly EmailSettingsService $settings,
    ) {
    }

    public function submit(array $data, ?string $ipAddress = null): ContactEnquiry
    {
        $reference = 'ABC-ENQ-'.BusinessTime::nowManila()->format('Ymd').'-'.strtoupper(Str::random(5));

        $enquiry = ContactEnquiry::query()->create([
            'reference_code' => $reference,
            'name' => trim((string) $data['name']),
            'email' => mb_strtolower(trim((string) $data['email'])),
            'subject' => trim((string) $data['subject']),
            'message' => trim((string) $data['message']),
            'status' => ContactEnquiry::STATUS_RECEIVED,
            'ip_address' => $ipAddress,
        ]);

        if (! $this->settings->featureEnabled('feature_enquiries')) {
            return $enquiry;
        }

        $submittedAt = BusinessTime::format($enquiry->created_at).' PHT';
        $inboxEmail = $this->settings->cafeInboxEmail();

        if (filter_var($inboxEmail, FILTER_VALIDATE_EMAIL)) {
            $this->transactional->queue(
                messageKey: 'enquiry-inbox:'.$enquiry->id,
                purpose: 'enquiry_inbox',
                recipientEmail: $inboxEmail,
                recipientName: 'Artisan Brew Co. Inbox',
                subject: "[{$enquiry->reference_code}] {$enquiry->subject}",
                templateName: 'enquiry_inbox',
                templateData: [
                    'referenceCode' => $enquiry->reference_code,
                    'visitorName' => $enquiry->name,
                    'visitorEmail' => $enquiry->email,
                    'enquirySubject' => $enquiry->subject,
                    'enquiryMessage' => $enquiry->message,
                    'submittedAt' => $submittedAt,
                    'adminUrl' => route('admin.email.enquiries'),
                ],
                options: [
                    'reply_to' => $enquiry->email,
                    'reply_to_name' => $enquiry->name,
                    'related_type' => 'contact_enquiry',
                    'related_id' => $enquiry->id,
                ]
            );
        }

        $this->transactional->queue(
            messageKey: 'enquiry-ack:'.$enquiry->id,
            purpose: 'enquiry_ack',
            recipientEmail: $enquiry->email,
            recipientName: $enquiry->name,
            subject: "We received your message ({$enquiry->reference_code}) — Artisan Brew Co.",
            templateName: 'enquiry_ack',
            templateData: [
                'referenceCode' => $enquiry->reference_code,
                'visitorName' => $enquiry->name,
                'enquirySubject' => $enquiry->subject,
                'submittedAt' => $submittedAt,
            ],
            options: [
                'related_type' => 'contact_enquiry',
                'related_id' => $enquiry->id,
            ]
        );

        return $enquiry;
    }

    public function updateStatus(ContactEnquiry $enquiry, string $status, ?string $notes, User $actor): ContactEnquiry
    {
        $enquiry->status = $status;
        $enquiry->admin_notes = $notes !== null ? trim($notes) : $enquiry->admin_notes;
        $enquiry->handled_by = $actor->id;
        $enquiry->save();

        return $enquiry;
    }
}
