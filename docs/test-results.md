# Verification & Test Results — Artisan Brew Co.

All automated checks below were executed after final application code and UI redesign edits against local **MariaDB 10.4.32** (`artisan_brew_co_test` for PHPUnit; `artisan_brew_co` for live HTTP smoke verification).

---

## 1. PHPUnit Automated Suite (`php artisan test --colors=never`)

**Result:** **19 passed, 0 failed, 0 skipped (209 assertions)**

| Suite / Test Case | What Was Verified | Result |
| --- | --- | --- |
| `Tests\Unit\MoneyAndBusinessTimeTest` (2 tests) | Integer centavo conversions/formatting and `Asia/Manila` date bounds to UTC conversion | Passed |
| `Tests\Feature\AuthenticationAndAuthorizationTest` (6 tests) | Guest redirects on all protected routes; admin -> `/dashboard`, cashier -> `/pos` landing; inactive user rejection & mid-session revocation; login rate limiting (5/min); cashier `403` on admin routes & profile privilege-escalation rejection; cashier history/receipt isolation vs admin global visibility | Passed |
| `Tests\Feature\CrudAndUploadTest` (4 tests) | Product CRUD, GD image preparation & disguised PHP script rejection, hard-delete vs archive/restore, stale `stock_version` edit rejected after checkout, Customer CRUD & safe hard/soft delete, Staff CRUD, password hashing, blank password preservation on edit, final active admin protection | Passed |
| `Tests\Feature\CheckoutAndPricingTest` (3 tests) | Dedicated Record Sale single-item checkout (walk-in + overselling + 0/-1/1.5 qty rejections), POS multi-item variants/add-ons, server-side recalculation ignoring tampered client prices, ineligible/duplicate add-on rejection, insufficient cash rejection, exact-stock & split-variant stock aggregation, idempotent checkout token replay & mismatched payload `409`, full rollback on mid-cart failure and simulated `sale_addons` DB write exception | Passed |
| `Tests\Feature\ConcurrencyCheckoutTest` (2 tests) | **Genuine parallel two-process MariaDB execution** (`tests/bin/concurrent-checkout.php` synchronized by barrier on `artisan_brew_co_test`): (1) 2 workers competing for last 2 units -> exactly 1 succeeds, 1 fails, stock = 0, 1 order; (2) 2 workers submitting identical `(sold_by, checkout_token)` simultaneously -> 1 order created, stock decremented once | Passed |
| `Tests\Feature\HistoryReceiptAndDashboardTest` (2 tests) | Historical snapshot preservation after live menu price/name edits and customer/staff soft-deletes; receipt reprint idempotency; dashboard KPI, `inventory_status`, and `/dashboard/metrics?preset=all` & `preset=7d` accuracy without join-multiplication inflation across multi-line/add-on orders | Passed |

---

## 2. Build & Security Audits

| Command | Output |
| --- | --- |
| `npm run build` | `vite v6.4.6 building for production...` -> `✓ built in 1.12s`; generated `public/build/manifest.json`, CSS (`65.99 kB`), and JS bundles (`app`, `pos`, `record-sale`, `dashboard`) + self-hosted Fontsource `.woff`/`.woff2` files. |
| `composer audit --no-interaction` | `No security vulnerability advisories found.` |
| `npm audit` | `found 0 vulnerabilities` |

---

## 3. Live HTTP Smoke Check (`http://127.0.0.1:8000`)

Executed via PHP cURL against `php artisan serve --host=127.0.0.1 --port=8000` backed by `artisan_brew_co`:
- `GET /images/cafe-interior-bar.jpg` -> `200`, `GET /images/cafe-interior-lounge.jpg` -> `200`
- `GET /login` -> `200`, compiled Vite CSS & JS assets -> `200`
- `POST /login` (admin) -> `302`
- `GET /dashboard`, `GET /dashboard/metrics?preset=all`, `GET /dashboard/metrics?preset=7d`, `GET /pos`, `GET /sales/record`, `GET /sales`, `GET /products`, `GET /customers`, `GET /staff`, `GET /menu-config`, `GET /profile` -> all `200`
- `POST /logout` (admin) -> `302`
- `POST /login` (cashier) -> `302`, `GET /pos` (cashier) -> `200`, `GET /dashboard` (cashier) -> `403`

---

## 4. Unverified Visual / Browser Checks & Reproducible Steps

Automated headless browser screenshots, live DOM layout checks across desktop/tablet/mobile (`1440px`, `1024px`, `768px`, `390px`), keyboard tab-order checks, and live Chart.js canvas pixel inspection **did not run** because:
1. Chrome DevTools MCP could not attach (`DevToolsActivePort` was not active on the running Chrome instance).
2. Python `playwright` is not installed (`ModuleNotFoundError: No module named 'playwright'`).

**Reproducible manual / browser verification steps:**
1. Start MariaDB and run `php artisan serve --host=127.0.0.1 --port=8000`.
2. Open Chrome (or launch Chrome with `--remote-debugging-port=9222` to enable automated DevTools inspection) at `http://127.0.0.1:8000/login`.
3. Inspect at `1440×900`, `1024×768`, `768×1024`, and `390×844` (DevTools device toolbar) with Console and Network tabs open:
   - Verify the split cafe-interior login page, then sign in as `admin` / `Admin@1234` -> verify `Sales Overview`, date-range `<select>` (`All Time`, `Last 30 Days`, `Last 14 Days`, `Last 7 Days`, `Today`), Revenue bronze bar chart, Inventory Status pillars (`Sold out`, `Low stock`, `Available`), Recent Orders, and Best Sellers.
   - Verify Keyboard Tab navigation and visible `#D2B077` bronze focus outlines across sidebar, header `New Sale` button, POS catalog/cart, Record Sale, Products (`Menu & Stock`), Customers, Staff, Menu Configuration, and Profile.
   - Verify 80mm / A4 white-paper receipt print preview and cashier RBAC (`cashier` / `Cashier@1234`).
