<?php

namespace App\Services\Email;

class EmailTemplateRenderer
{
    public const TEMPLATES = [
        'receipt' => 'Customer Digital Receipt',
        'password_reset' => 'Staff Password Recovery',
        'staff_invitation' => 'Staff Account Invitation',
        'low_stock_alert' => 'Low Stock & Sold-Out Alert',
        'daily_summary' => 'Daily Sales Summary',
        'security_alert' => 'Account Security Notification',
        'email_verification' => 'Staff Email Verification',
        'enquiry_inbox' => 'Contact Enquiry (Cafe Inbox)',
        'enquiry_ack' => 'Contact Enquiry Acknowledgement',
        'newsletter_confirm' => 'Newsletter Double Opt-In Confirmation',
        'campaign' => 'Promotional Campaign',
    ];

    public function render(string $template, array $data): array
    {
        if (! array_key_exists($template, self::TEMPLATES)) {
            throw new \InvalidArgumentException("Unsupported email template: {$template}");
        }

        $html = view("emails.{$template}", $data)->render();

        return [
            'html' => $html,
            'text' => $this->toPlainText($html),
        ];
    }

    public function sampleContext(string $template): array
    {
        return match ($template) {
            'receipt' => [
                'orderNumber' => 'ABC-20261127-0014',
                'purchasedAt' => 'Nov 27, 2026 · 2:15 PM PHT',
                'orderTypeLabel' => 'Dine-in',
                'paymentLabel' => 'GCash',
                'customerName' => 'Walk-in · Sofia',
                'cupName' => 'Sofia',
                'orderNote' => 'Serve hot',
                'discountLabel' => null,
                'discountFormatted' => '₱0.00',
                'subtotalFormatted' => '₱345.00',
                'totalFormatted' => '₱345.00',
                'cashFormatted' => '₱345.00',
                'changeFormatted' => '₱0.00',
                'signedReceiptUrl' => url('/'),
                'items' => [
                    [
                        'name' => 'Spanish Latte',
                        'variant' => 'Iced 16oz',
                        'addons' => 'Oat Milk',
                        'note' => 'Less Ice',
                        'quantity' => 1,
                        'unitPriceFormatted' => '₱195.00',
                        'lineTotalFormatted' => '₱195.00',
                    ],
                    [
                        'name' => 'Butter Croissant',
                        'variant' => null,
                        'addons' => null,
                        'note' => 'Warmed',
                        'quantity' => 1,
                        'unitPriceFormatted' => '₱150.00',
                        'lineTotalFormatted' => '₱150.00',
                    ],
                ],
            ],
            'password_reset' => [
                'fullName' => 'Sofia Mendoza',
                'resetUrl' => url('/reset-password/sample-token-preview'),
                'expireMinutes' => 60,
            ],
            'staff_invitation' => [
                'fullName' => 'Mateo Cruz',
                'username' => 'mateo',
                'roleLabel' => 'Cashier',
                'inviterName' => 'Elena Vance',
                'acceptUrl' => url('/invitations/sample-token-preview'),
                'expireHours' => 48,
            ],
            'low_stock_alert' => [
                'mode' => 'immediate',
                'alertTime' => 'Nov 27, 2026 · 3:40 PM PHT',
                'productsUrl' => route('products.index'),
                'items' => [
                    [
                        'name' => 'Single-Origin Cortado',
                        'sku' => 'ABC-ESP-0005',
                        'stock' => 2,
                        'threshold' => 5,
                        'state' => 'Low stock',
                    ],
                    [
                        'name' => 'Sea Salt Dark Chocolate Cookie',
                        'sku' => 'ABC-PAS-0004',
                        'stock' => 0,
                        'threshold' => 4,
                        'state' => 'Sold out',
                    ],
                ],
            ],
            'daily_summary' => [
                'businessDate' => 'Nov 27, 2026',
                'periodLabel' => 'Nov 26, 2026 10:00 PM – Nov 27, 2026 10:00 PM PHT',
                'revenueFormatted' => '₱14,820.00',
                'orderCount' => 52,
                'unitsSold' => 79,
                'averageOrderFormatted' => '₱285.00',
                'bestSellers' => [
                    ['name' => 'Spanish Latte', 'units' => 22, 'revenueFormatted' => '₱4,070.00'],
                    ['name' => 'Matcha Latte', 'units' => 14, 'revenueFormatted' => '₱2,590.00'],
                ],
                'stockAlerts' => [
                    ['name' => 'Almond Croissant', 'stock' => 3, 'threshold' => 5, 'state' => 'Low stock'],
                    ['name' => 'Double Espresso Brownie', 'stock' => 0, 'threshold' => 4, 'state' => 'Sold out'],
                ],
                'dashboardUrl' => route('dashboard'),
            ],
            'security_alert' => [
                'fullName' => 'Elena Vance',
                'eventTitle' => 'Password Changed',
                'eventSummary' => 'Your Artisan Brew Co. staff account password was updated.',
                'occurredAt' => 'Nov 27, 2026 · 4:05 PM PHT',
                'actionUrl' => route('login'),
                'actionLabel' => 'Review Account Sign In',
            ],
            'email_verification' => [
                'fullName' => 'Elena Vance',
                'pendingEmail' => 'elena.new@artisanbrewco.local',
                'verifyUrl' => url('/profile/verify-email/sample-token'),
                'expireHours' => 24,
            ],
            'enquiry_inbox' => [
                'referenceCode' => 'ABC-ENQ-20261127-01',
                'visitorName' => 'Clara Santos',
                'visitorEmail' => 'clara@example.com',
                'enquirySubject' => 'Private coffee bar tasting inquiry',
                'enquiryMessage' => 'Hello Artisan Brew Co., do you offer weekend pour-over tastings for small groups?',
                'submittedAt' => 'Nov 27, 2026 · 1:20 PM PHT',
                'adminUrl' => route('admin.email.enquiries'),
            ],
            'enquiry_ack' => [
                'referenceCode' => 'ABC-ENQ-20261127-01',
                'visitorName' => 'Clara Santos',
                'enquirySubject' => 'Private coffee bar tasting inquiry',
                'submittedAt' => 'Nov 27, 2026 · 1:20 PM PHT',
            ],
            'newsletter_confirm' => [
                'subscriberName' => 'Marco',
                'confirmUrl' => url('/newsletter/confirm/sample-token'),
                'expireHours' => 24,
                'noticeVersion' => config('email.consent_notice_version', '2026-11-v1'),
            ],
            'campaign' => [
                'subject' => 'Seasonal Single-Origin Pour Now Brewing',
                'previewText' => 'Small-batch seasonal beans now on bar at Artisan Brew Co.',
                'bodyHtml' => '<p>We have added our latest single-origin micro-lot to the espresso bar. Drop by the studio or browse the live menu anytime.</p>',
                'menuUrl' => route('menu.public'),
                'unsubscribePlaceholder' => '{{ unsubscribe }}',
                'preferencesUrl' => '{{ update_profile }}',
            ],
        };
    }

    private function toPlainText(string $html): string
    {
        $withoutStyle = preg_replace('/<style\b[^>]*>.*?<\/style>/is', '', $html) ?? $html;
        $withBreaks = preg_replace('/<\/(p|div|h1|h2|h3|tr|li)>/i', "\n", $withoutStyle) ?? $withoutStyle;
        $plain = html_entity_decode(strip_tags($withBreaks), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $lines = array_values(array_filter(array_map('trim', explode("\n", $plain)), static fn (string $line) => $line !== ''));

        return implode("\n", $lines);
    }
}
