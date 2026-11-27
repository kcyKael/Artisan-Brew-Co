# Artisan Brew Co. — Brevo HTTPS Email System

This document covers the architecture, operational workflows, environment configuration, domain verification, webhooks, queue/scheduler setup, and testing boundaries for the **Artisan Brew Co. (ABC)** email system powered exclusively by **Brevo's REST/HTTPS v3 API** (`https://api.brevo.com/v3`).

---

## 1. Architecture Summary

- **No SMTP or PHP `mail()`**: All transactional emails, subscriber list syncs, and promotional campaigns communicate solely over HTTPS using `App\Services\Email\BrevoClient` (Laravel HTTP Client + `api-key` header + `idempotencyKey` UUID header for transactional retries).
- **Database Outbox & Queued Pipeline**:
  - `email_deliveries` acts as the transactional outbox, recording every message's unique `message_key`, recipient, subject, template, encrypted payload (`payload_encrypted`), lifecycle status (`queued`, `processing`, `accepted`, `delivered`, `bounced`, `blocked`, `failed`, `uncertain`, `expired`, `simulated`), Brevo `provider_message_id`, and attempt count.
  - `SendTransactionalEmailJob` processes deliveries asynchronously on Redis/Database queues (`database` configured with `after_commit => true`), honoring Brevo's `429` (`x-sib-ratelimit-reset`) backoff headers.
  - When `BREVO_LIVE_SEND_ENABLED=false` (the safe local default), emails are rendered cleanly, stored in `email_deliveries` with `status = 'simulated'`, and never leave the local environment unless explicitly enabled.
- **Laravel Notification Integration**: `StaffPasswordResetNotification` routes Laravel's built-in password reset flow through `BrevoApiChannel` on the `emails-high` priority queue.
- **Dedicated Admin UI**: **Email Center** (`/admin/email`) provides Settings & Template Previews, Deliveries & Webhook Logs, Customer Enquiries, Subscribers, and Promotional Campaigns.

---

## 2. Eight-Feature Verification Matrix

| Feature | Trigger / Flow | Queue / Delivery | Safeguards & Key Files |
|---|---|---|---|
| **1. Customer Digital Receipts** | Optional checkout email (POS / Record Sale) or manual resend from Order Detail & Thermal Receipt pages + signed public receipt URL | `TransactionalEmailService` -> `emails-default` | Order total/subtotal/discount/line items preserved; resend confirmation checkbox; separate unchecked marketing opt-in; `ReceiptEmailController`, `OrderReceiptEmailService` |
| **2. Staff Password Recovery** | `GET/POST /forgot-password` & `/reset-password/{token}` | `StaffPasswordResetNotification` via `BrevoApiChannel` on `emails-high` | Throttled requests, single-use hashed token (60m TTL), blocked for inactive or unactivated pending-invite accounts, revokes active sessions on reset |
| **3. Staff Invitations** | Staff creation (`invite_mode`) or resend/revoke on Staff Edit | `StaffInvitationService` -> `emails-high` | Hashed random token (`staff_invitations`), 48h expiry, password set & activation at `/invitations/{token}` |
| **4. Low-Stock & Sold-Out Alerts** | Post-commit stock decrement (`CheckoutService`, `ProductController::adjustStock` / `update`) or scheduled digest | `StockAlertService` -> `emails-default` | Atomic state transition (`ok` -> `low_stock` -> `out_of_stock`) prevents alert floods; archived/inactive products excluded; restock rearms alerts |
| **5. Daily Sales Summaries** | Artisan `email:daily-summary` (scheduler at configured Manila closing time, default `22:00`) or manual trigger in Email Center | `DailySalesSummaryService` -> `emails-default` | Half-open Manila day window (`[closing - 24h, closing)`), excludes voided orders, matches `DashboardMetricsService`, attaches CSV breakdown |
| **6. Account-Security Notifications** | Password change/reset, role change, deactivation, profile email change verification | `AccountSecurityNotificationService` -> `emails-high` | Email updates store `pending_email` with verification token until confirmed; notifies previous verified address; invalidates sessions when deactivated/reset |
| **7. Contact-Form Enquiries & Acknowledgements** | Public landing form `POST /contact` | `ContactEnquiryService` -> `emails-default` | Stores `contact_enquiries` record first; honeypot (`website`) + IP/email rate limiting; escaped HTML; `Reply-To` set to enquirer |
| **8. Promotional Campaigns & Newsletter** | Landing opt-in / checkout opt-in -> double opt-in confirmation -> Brevo Contact sync & Email Campaign dispatch | `SyncBrevoContactJob` (`emails-marketing`) + `MarketingCampaignService` | Never silently subscribes buyers/enquirers; bidirectional unsubscribe + suppression webhooks; never overwrites `emailBlacklisted`; `BREVO_LIVE_SEND_ENABLED` guard |

---

## 3. Environment Configuration (`.env`)

Configure these keys in `.env` (see `.env.example` for template defaults):

```dotenv
QUEUE_CONNECTION=database

BREVO_API_KEY=
BREVO_BASE_URL=https://api.brevo.com/v3
BREVO_SENDER_EMAIL=no-reply@your-verified-domain.com
BREVO_SENDER_NAME="Artisan Brew Co."
BREVO_REPLY_TO_EMAIL=hello@your-verified-domain.com
BREVO_WEBHOOK_TOKEN=replace-with-long-random-secret
BREVO_MARKETING_LIST_ID=
BREVO_LIVE_SEND_ENABLED=false
BREVO_TIMEOUT=10
BREVO_CONNECT_TIMEOUT=5
BREVO_MAX_RETRIES=3

ABC_OWNER_ALERT_EMAILS=owner@your-verified-domain.com
ABC_CAFE_INBOX_EMAIL=hello@your-verified-domain.com
ABC_DAILY_SUMMARY_TIME=22:00
ABC_STOCK_ALERT_MODE=immediate
ABC_STAFF_INVITE_TTL_HOURS=48
```

- **Local / Safe Testing Mode**: Keep `BREVO_LIVE_SEND_ENABLED=false` during development so no real external emails leave your machine.
- **Production Mode**: Populate `BREVO_API_KEY`, a verified `BREVO_SENDER_EMAIL`, `BREVO_WEBHOOK_TOKEN`, `BREVO_MARKETING_LIST_ID`, and set `BREVO_LIVE_SEND_ENABLED=true`.

---

## 4. Brevo Account, Domain Authentication & DNS Setup

Before turning on `BREVO_LIVE_SEND_ENABLED=true`:

1. **Create / Verify Sender & Domain in Brevo**:
   - Sign in to your Brevo dashboard -> **Senders, Domains & Dedicated IPs** -> **Domains**.
   - Add your sending domain (e.g., `artisanbrewco.ph`) and publish the exact **DKIM**, **SPF**, and **DMARC** TXT/CNAME records generated by Brevo in your DNS provider.
   - Verify all records show green **Authenticated** inside Brevo.
2. **Create Marketing Contact List**:
   - In Brevo -> **Contacts** -> **Lists**, create a dedicated list for confirmed Artisan Brew Co. subscribers and copy its numeric List ID into `BREVO_MARKETING_LIST_ID` (or Email Center Settings).
3. **Generate API v3 Key**:
   - In Brevo -> **SMTP & API** -> **API Keys**, generate an API v3 key and place it in `BREVO_API_KEY`. Never commit `.env` or API keys to Git.

---

## 5. Brevo Webhooks Configuration

Register two HTTPS webhook endpoints in Brevo pointing to your publicly reachable HTTPS domain:

1. **Transactional Webhook**:
   - URL: `https://your-domain.com/webhooks/brevo/transactional`
   - Authentication Header: `X-Brevo-Webhook-Token: <BREVO_WEBHOOK_TOKEN>` (or `Authorization: Bearer <BREVO_WEBHOOK_TOKEN>`)
   - Events to enable: `request` / `sent`, `delivered`, `opened`, `click`, `soft_bounce`, `hard_bounce`, `blocked`, `spam`, `invalid_email`, `error`.
2. **Marketing Webhook**:
   - URL: `https://your-domain.com/webhooks/brevo/marketing`
   - Authentication Header: `X-Brevo-Webhook-Token: <BREVO_WEBHOOK_TOKEN>` (or `Authorization: Bearer <BREVO_WEBHOOK_TOKEN>`)
   - Events to enable: `unsubscribe`, `hard_bounce`, `soft_bounce`, `spam`, `blocked`, `list_addition` / `contact_updated`.

All incoming events are validated via constant-time comparison (`hash_equals`), deduplicated by a composite SHA-256 `event_key` in `email_webhook_events`, and processed asynchronously on the `webhooks` queue (`ProcessBrevoWebhookEventJob`).

---

## 6. Queue Workers, Scheduler & Maintenance Commands

Run queue workers across the prioritized queues in production (via Supervisor on Linux or Windows Task Scheduler / service wrapper):

```bash
php artisan queue:work database --queue=emails-high,emails-default,webhooks,emails-marketing --sleep=3 --tries=3 --timeout=60
```

Run the Laravel scheduler every minute via cron / Task Scheduler:

```bash
* * * * * cd /path/to/artisan-brew-co && php artisan schedule:run >> /dev/null 2>&1
```

Registered schedules (`routes/console.php`):
- `abc:emails-daily-summary` — runs every 15 minutes and dispatches only once per Manila business day once the configured closing time (`ABC_DAILY_SUMMARY_TIME` / Email Settings) is reached.
- `abc:emails-stock-digest` — runs hourly when `stock_alert_mode = digest`.
- `abc:emails-recover-outbox` — runs every 5 minutes to re-queue stale outbox rows that missed a worker pickup.
- `abc:emails-prune-payloads` — runs daily (`02:30`) to scrub sensitive encrypted HTML/link payloads (`payload_encrypted`) after 30 days while retaining audit metadata.

Useful Artisan commands for operations & diagnostics:

```bash
php artisan abc:emails-send-test recipient@example.com --template=receipt
php artisan abc:emails-daily-summary --date=2026-11-27
php artisan abc:emails-stock-digest --force
php artisan abc:emails-recover-outbox --older-than=60
php artisan abc:emails-prune-payloads
```

---

## 7. Automated Verification & Known External Boundaries

### Automated Local Verification
Run the full PHPUnit test suite (configured with `Http::preventStrayRequests()` and `Http::fake()` so no real network calls escape tests):

```bash
php artisan test --colors=never
```

### Live Send Boundary
Live end-to-end delivery to external email inboxes (`api.brevo.com`, DNS propagation, public HTTPS webhooks) cannot be executed from a sandbox without real Brevo credentials, a verified domain, and a public HTTPS URL. All code paths, idempotency headers, `429` retry handling, webhook signature verification, suppression rules, double opt-in tokens, daily summary math parity, and security flows are verified locally via HTTP fakes and feature tests.
