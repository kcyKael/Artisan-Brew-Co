# Artisan Brew Co. (ABC) — Cafe Point-of-Sale System

A full-stack Laravel cafe Point-of-Sale and operations application built for **IT0049 Web System Technologies (Midterm Project)**.

> **Framework Clarification:** Per explicit project instruction, this project is implemented in **Laravel 12** (overriding the CodeIgniter activity wording in the original IT0049 assignment brief while preserving all required tables, foreign keys, CRUD workflows, authentication, Record Sale, Sales History, and rubric deliverables). Note: The original PDF file was not present in the workspace, so the transcribed requirements and SQL schema from the project brief were used.

---

## 1. Detected Stack & Installed Versions

| Component | Version / Details |
| --- | --- |
| **Framework** | Laravel Framework `12.74.0` (`laravel/framework ^12.0`) |
| **PHP** | `8.2.12` (CLI / XAMPP) with `gd`, `exif`, `fileinfo`, `pdo_mysql`, `mbstring`, `openssl`, `bcmath`, `intl`, `zip` |
| **Database** | MariaDB `10.4.32` (InnoDB, `utf8mb4`, `utf8mb4_unicode_ci`) |
| **Dependency Managers** | Composer `2.10.3`, Node.js `v24.15.0`, npm `11.12.1` |
| **Frontend Toolchain** | Vite `6.4.6`, Tailwind CSS `v4`, Chart.js, self-hosted `@fontsource/cormorant-garamond` & `@fontsource/plus-jakarta-sans` |

---

## 2. Local Setup & Start Instructions

### Prerequisites
- PHP 8.2+ with `pdo_mysql`, `gd`, `exif`, `fileinfo`, `mbstring`, `openssl`
- Composer 2.x
- MySQL 8+ or MariaDB 10.4+ (XAMPP MariaDB `10.4.32` used locally)
- Node.js / npm (only needed if rebuilding frontend assets; compiled `public/build/` assets are already included)

### Step-by-Step Local Setup

1. **Install PHP & frontend dependencies:**
   ```bash
   composer install
   npm install
   npm run build
   ```

2. **Configure environment (`.env`):**
   Copy `.env.example` to `.env` if setting up a fresh clone, generate an application key, and point to MariaDB/MySQL:
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```
   Key defaults in `.env`:
   - `APP_TIMEZONE=UTC` (database storage) and `POS_DISPLAY_TIMEZONE=Asia/Manila` (business display & day boundaries)
   - `DB_CONNECTION=mariadb`, `DB_HOST=127.0.0.1`, `DB_PORT=3306`, `DB_DATABASE=artisan_brew_co`, `DB_COLLATION=utf8mb4_unicode_ci`
   - `SESSION_DRIVER=database`, `CACHE_STORE=database`

3. **Start MariaDB and create databases:**
   ```sql
   CREATE DATABASE IF NOT EXISTS artisan_brew_co CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   CREATE DATABASE IF NOT EXISTS artisan_brew_co_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   ```
   (On XAMPP Windows, start MariaDB with `C:\xampp\mysql\bin\mysqld.exe --defaults-file="C:\xampp\mysql\bin\my.ini" --standalone`.)

4. **Run migrations:**
   ```bash
   php artisan migrate
   ```

5. **Create an account or load demo data:**
   - **Production / clean start (first admin only):**
     ```bash
     php artisan abc:create-admin
     ```
   - **Local demonstration dataset (opt-in, non-destructive):**
     ```bash
     php artisan abc:seed-demo
     ```
     Seeds 4 demo staff accounts, 8 customers, 5 categories, 20 cafe products (with healthy, low, and zero stock + prepared images from `resources/assets/seed-images/`), drink variants, 6 paid add-ons, and 18 historical orders spanning the past 14 days.

6. **Start the local server:**
   ```bash
   php artisan serve --host=127.0.0.1 --port=8000
   ```
   Visit **http://127.0.0.1:8000**.

---

## 3. Local Demo Credentials (Demo Seeder Only)

> **Warning:** These credentials are created **only** by the opt-in `php artisan abc:seed-demo` command for local evaluation. Never seed demo accounts into production; use `php artisan abc:create-admin` instead.

| Role | Username | Password | Landing Page |
| --- | --- | --- | --- |
| **Admin** | `admin` | `Admin@1234` | `/dashboard` |
| **Admin** | `manager` | `Manager@1234` | `/dashboard` |
| **Cashier** | `cashier` | `Cashier@1234` | `/pos` |
| **Cashier** | `barista` | `Barista@1234` | `/pos` |

---

## 4. Features & Role Permission Matrix

| Capability | Admin | Cashier |
| --- | --- | --- |
| Executive Dashboard & Chart.js Reports (`/dashboard`) | Yes | No (`403`) |
| Multi-Item Cafe POS Terminal (`/pos`) | Yes | Yes |
| Dedicated Record Sale Page (`/sales/record`) | Yes | Yes |
| Sales History & Printable Receipts (`/sales`, `/orders/{id}/receipt`) | All orders | Own completed sales only |
| Products, Stock, Variants, Categories, Add-ons (`/products`, `/menu-config`) | Full CRUD / Archive | Read in POS only (`403` on admin routes) |
| Customers (`/customers`) | Full CRUD | Search, View, Create, Update (Delete blocked `403`) |
| Staff & Roles (`/staff`) | Full CRUD (protects final active admin) | No (`403`) |
| Own Profile, Avatar & Password (`/profile`) | Yes | Yes (role & active status immutable) |

---

## 5. Pricing, Shared Stock & Checkout Integrity

- **Monetary Precision:** Calculated internally in integer centavos (`App\Support\Money`) and persisted as `DECIMAL(10,2)` PHP (`₱`).
  - `unit_price = base_price + variant_adjustment + sum(selected_addon_prices)`
  - `line_total = unit_price × quantity`
  - `order_total = sum(line_totals)`
  - `change = cash_received - order_total`
  - All totals and prices are recalculated on the server inside `App\Services\CheckoutService`.
- **Shared Base Stock:** `products.stock_quantity` tracks sellable servings per base product across its variants. `CheckoutService` aggregates quantities across all cart lines sharing a `product_id` (e.g. Hot + Iced variants) before checking stock.
- **Atomic Checkout & Concurrency:** Executed inside `DB::transaction(..., 3)` with pessimistic `lockForUpdate()` in ascending `product_id` order, guarded atomic decrements (`WHERE stock_quantity >= ?`), optimistic `stock_version` protection on admin edits, and `(sold_by, checkout_token)` uniqueness with `request_hash` verification to make double-clicks and retries idempotent.

---

## 6. Verification & Audit Summary

Run the full automated suite against the isolated MariaDB test database (`artisan_brew_co_test`):

```bash
php artisan test
```

- **PHPUnit Results:** `19 passed (204 assertions)` in `13.92s` (0 failures, 0 skipped), including 2-process parallel MariaDB concurrency tests (`Tests\Feature\ConcurrencyCheckoutTest`).
- **Security Audits:** `composer audit` (`No security vulnerability advisories found`) and `npm audit` (`found 0 vulnerabilities`).
- **Live HTTP Smoke Check:** Verified `200`/`302`/`403` responses across login, dashboard, metrics JSON, POS, Record Sale, Sales History, Products, Customers, Staff, Menu Options, Profile, compiled Vite CSS/JS bundles, and prepared `/media/products/*.jpg` responses against `http://127.0.0.1:8000`.
- **Browser Visual Check Limitation:** Automated Chrome DevTools MCP could not attach (`DevToolsActivePort` not active on the running Chrome browser) and Python `playwright` is not installed. See [docs/test-results.md](docs/test-results.md) for manual responsive verification steps.

---

## 7. Documentation & Submission Status

- [docs/database-design.md](docs/database-design.md) — ER diagram, schema foundation, additive extensions, deletion policy, timezone & money policies.
- [docs/requirements-checklist.md](docs/requirements-checklist.md) — Source-to-implementation mapping & IT0049 rubric checklist.
- [docs/skills-used.md](docs/skills-used.md) — Detected environment and installed Antigravity skills applied.
- [docs/test-results.md](docs/test-results.md) — Automated tests, concurrency proof, HTTP checks, and browser limitation notes.
- [docs/deployment.md](docs/deployment.md) — Production Laravel PHP hosting guide.

**Submission Link Status:**
1. **GitHub Repository (`https://github.com/kcyKael/Artisan-Brew-Co.git`):** Local implementation is complete and ready; push is intentionally held awaiting explicit user permission.
2. **Hosted Live Application URL:** **Incomplete** — requires an external PHP 8.2+ / MariaDB-compatible host and deployment credentials.
