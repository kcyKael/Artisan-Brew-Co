# Artisan Brew Co. (ABC) — Coffee Studio, POS & Brevo Email Platform

Full-stack **Laravel 12** cafe studio, atomic Point-of-Sale & inventory system, animated 80mm thermal receipt printer, and **Brevo HTTPS API** email platform — built by **RootKitCats** for **IT0049 Web System Technologies**.

**Live Application:** [https://artisanbrew.kcykae.dev](https://artisanbrew.kcykae.dev)

---

## 1. Tech Stack & Versions

| Component | Version / Details |
| --- | --- |
| **Framework** | Laravel Framework `12.74.0` (`laravel/framework ^12.0`) |
| **PHP** | `8.2.12` (local XAMPP) / `8.4.28` (production Droplet) with `gd`, `exif`, `fileinfo`, `pdo_mysql`, `mbstring`, `openssl`, `bcmath`, `intl`, `zip` |
| **Database** | MariaDB `10.4.32` / MySQL 8+ (InnoDB, `utf8mb4`, `utf8mb4_unicode_ci`) |
| **Frontend Toolchain** | Vite `6.4.6`, Tailwind CSS `v4`, GSAP, Chart.js, self-hosted `@fontsource/cormorant-garamond` & `@fontsource/plus-jakarta-sans` |
| **Email & Webhooks** | Brevo REST/HTTPS v3 API (`POST /v3/smtp/email`, Contacts, Campaigns, Bearer/Header-verified Webhooks) |

---

## 2. Key Features

### Public Coffee Studio (`/`, `/menu`, `/about`)
- **Cinematic Studio Landing Page (`/`)**: Warm-black ceramic palette (`rgb(22, 20, 18)`), animated cup & steam entrance, studio lighting, signature brews, contact concierge form (`POST /contact`), and double opt-in seasonal newsletter (`POST /newsletter/subscribe`).
- **Live Public Menu (`/menu`)**: Synced directly with active, non-archived catalog items, variants, and add-ons in PHP (`₱`).
- **About the Developers (`/about`)**: Showcases **RootKitCats** with team emblem and GitHub profiles.

### Counter POS, Inventory & Analytics
- **Multi-Item Cafe POS (`/pos`) & Record Sale (`/sales/record`)**:
  - Variants, paid add-ons, item-level quick notes (*Less Ice*, *No Ice*, *Less Sugar*, *Extra Hot*), cup name for walk-ins, and **Dine-in / Take-out** service types.
  - **Cash**, **Card**, and **GCash** payment methods (with approval/reference tracking) plus **Percent (`%`)**, **Fixed (`₱`)**, and **Gift Card** discounts.
  - **Animated 80mm Thermal Receipt Printer**: Live modal & receipt view featuring a charcoal thermal printer housing, recessed exit slot, foreground cutter lip, stepper-paced upward paper feed, serrated top/bottom receipt edges, cutter sweep & release animation, `80mm` / `A4` layout toggle, `prefers-reduced-motion` support, and clean `@media print` receipt-only output.
- **Manager-Authorized Order Voiding**: Admins can void directly; cashiers can void their own orders via a manager-credential override modal, atomically restoring product stock and gift card balances.
- **Catalog, Inventory & Reporting**: Auto-generated unique SKUs, consistent product placeholder SVG, prepared GD image uploads stored outside `public/`, and an executive Chart.js dashboard (`/dashboard`) scoped to `Asia/Manila`.

### Brevo HTTPS Email System (`/admin/email` & [docs/email-system.md](docs/email-system.md))
All transactional and marketing email flows run over **Brevo's HTTPS v3 API** through a durable `email_deliveries` outbox and prioritized queues (`emails-priority`, `emails`, `emails-webhooks`, `emails-marketing`):
1. **Customer Digital Receipts**: Optional checkout receipt email, confirmation-gated resend, and signed public receipt URL (`/receipts/signed/{order}`).
2. **Staff Password Recovery**: Throttled reset links via `BrevoApiChannel` on `emails-priority`, revoking active sessions on reset.
3. **Staff Onboarding Invitations**: 48-hour hashed invite tokens (`/invitations/{token}`), resend/revoke controls, and reset blocking until activation.
4. **Low-Stock & Sold-Out Alerts**: Atomic post-commit transition alerts (or hourly digest) with restock re-arming.
5. **Daily Sales Summaries**: Automated `Asia/Manila` closing report matching dashboard KPIs with CSV breakdown attachment.
6. **Account-Security Notifications**: Alerts on password changes/resets, role changes, deactivation, and pending email verification.
7. **Contact Enquiries & Acknowledgements**: Honeypot- and rate-limit-protected public form storing records before queuing `Reply-To` notifications.
8. **Newsletter & Promotional Campaigns**: Double opt-in confirmation, one-click unsubscribe, bidirectional Brevo suppression sync, and campaign builder/preview/test/dispatch with `BREVO_LIVE_SEND_ENABLED` safety guard.

---

## 3. Role Permission Matrix

| Capability | Admin | Cashier |
| --- | --- | --- |
| Executive Dashboard & Chart.js Reports (`/dashboard`) | Yes | No (`403`) |
| Multi-Item Cafe POS Terminal (`/pos`) & Record Sale (`/sales/record`) | Yes | Yes |
| Sales History, Receipts & Email Receipt Resend (`/sales`, `/orders/{id}/receipt`) | All orders | Own orders only |
| Void Order (`/orders/{id}/void`) | Direct | Own orders with Manager override |
| Products, Stock, Variants, Categories, Add-ons (`/products`, `/menu-config`) | Full CRUD / Archive | Read in POS only (`403` on admin routes) |
| Customers (`/customers`) | Full CRUD | Search, View, Create, Update (Delete blocked `403`) |
| Staff, Invitations & Email Center (`/staff`, `/admin/email`) | Full access | No (`403`) |
| Own Profile, Email Verification, Avatar & Password (`/profile`) | Yes | Yes (role & active status immutable) |

---

## 4. Local Setup & Start Instructions

1. **Install dependencies & build assets:**
   ```bash
   composer install
   npm install && npm run build
   ```
2. **Configure environment (`.env`):**
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```
   Key defaults: `APP_TIMEZONE=UTC`, `POS_DISPLAY_TIMEZONE=Asia/Manila`, `DB_CONNECTION=mariadb`, `DB_DATABASE=artisan_brew_co`, `QUEUE_CONNECTION=database`, and `BREVO_LIVE_SEND_ENABLED=false` (safe simulated mode locally).
3. **Create databases & migrate:**
   ```sql
   CREATE DATABASE IF NOT EXISTS artisan_brew_co CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   CREATE DATABASE IF NOT EXISTS artisan_brew_co_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   ```
   ```bash
   php artisan migrate
   ```
4. **Create an admin or load local demo data:**
   ```bash
   php artisan abc:create-admin
   ```
   *(Optional local demo dataset — never run in production:)*
   ```bash
   php artisan abc:seed-demo
   ```
   Demo accounts (`abc:seed-demo` only): `admin` (`Admin@1234`), `manager` (`Manager@1234`), `cashier` (`Cashier@1234`), `barista` (`Barista@1234`).
5. **Start the local server:**
   ```bash
   php artisan serve --host=127.0.0.1 --port=8000
   ```

---

## 5. Pricing, Shared Stock & Checkout Integrity

- **Monetary Precision:** Calculated internally in integer centavos (`App\Support\Money`) and persisted as `DECIMAL(10,2)` PHP (`₱`), recalculated server-side in `App\Services\CheckoutService`.
- **Shared Base Stock & Atomic Concurrency:** Executed inside `DB::transaction(..., 3)` with pessimistic `lockForUpdate()` in ascending `product_id` order, guarded atomic decrements (`WHERE stock_quantity >= ?`), optimistic `stock_version` checks, and `(sold_by, checkout_token)` idempotency.

---

## 6. Documentation & Team

- [docs/email-system.md](docs/email-system.md) — Brevo HTTPS v3 API architecture, 8-feature matrix, DNS, webhooks, and worker/scheduler setup.
- [docs/deployment.md](docs/deployment.md) — Production Laravel PHP hosting guide.
- [docs/database-design.md](docs/database-design.md) — ER diagram, schema foundation, deletion policy, timezone & money policies.
- [docs/requirements-checklist.md](docs/requirements-checklist.md) — Source-to-implementation mapping & IT0049 rubric checklist.
- [docs/test-results.md](docs/test-results.md) — Automated tests & concurrency verification notes.

### Developers — **RootKitCats**
- **Kyl Aldric Valencia** ([@kcyKael](https://github.com/kcyKael)) — Project Lead, Full-Stack Developer, and System Architect
- **Emran Ryan Maina** ([@ramenagiii](https://github.com/ramenagiii)) — Backend Developer and Database Developer
- **Jerahmeel Romero** ([@jdgr1](https://github.com/jdgr1)) — Frontend and Product Module Developer, UI/UX Lead Developer
- **Erin Gabrielle Maliñana** ([@O3r1n-Br13ll3](https://github.com/O3r1n-Br13ll3)) — Quality Assurance and Integration Developer, UI/UX Assistant Developer
