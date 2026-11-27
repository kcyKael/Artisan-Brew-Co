# POS Enhancements Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Upgrade Artisan Brew Co.'s POS, checkout service, product creation, and order lifecycle with order/line notes, sticky no-scroll Order Summary, Card/GCash payments, Discounts & Gift Cards, Walk-in cup names, auto-generated SKUs, consistent image placeholders, Dine-in/Take-out order types, and atomic order voiding.

**Architecture:** Extend the MariaDB `orders`, `sales`, and `gift_cards` schema with backward-compatible additive migrations, centralize all pricing, discount/gift-card redemption, stock deduction, and void restoration inside `CheckoutService` using `DB::transaction` + `lockForUpdate()`, and restructure the POS Order Summary panel (`#pos-order-panel`) into a viewport-locked flex column so the checkout CTA is always reachable without scrolling.

**Tech Stack:** Laravel `12.74.0`, PHP `8.2.12`, MariaDB `10.4.32` (InnoDB, `utf8mb4_unicode_ci`), Blade, Tailwind CSS v4, Vanilla JS, PHPUnit `11.5`.

**Spec:** User requirements provided in prompt (`2026-11-27`), preserving all existing IT0049 schema, auth, RBAC, and concurrency guarantees.

## Global Constraints

- Preserve existing `products`, `customers`, `users`, and `sales` required columns and signed `INT` (`int(11)`) foreign keys.
- All monetary math must run in integer centavos (`App\Support\Money`) on the server; never trust browser-submitted totals, discounts, or prices.
- Keep `cash_received >= total_amount` invariant intact (`cash_received = total_amount`, `change_amount = 0.00` for Card and GCash).
- Voiding an order requires an active Admin's authority: Admins can void directly, while Cashiers may void their own orders only via a popup Manager Override modal verifying valid active Admin credentials (`admin_username` + `admin_password`, rate-limited, without replacing the cashier's session). `voided_by` records the approving Admin's user ID, and voiding must atomically restore product `stock_quantity` and redeemed gift-card balance.
- Voided orders must be excluded from Dashboard revenue, order counts, units sold, and best-seller rankings, while remaining visible with a `Voided` badge in Sales History and Order Detail.
- Zero authored comments in any modified/created PHP, Blade, CSS, or JS files; do not push to GitHub without explicit user permission.

## Review Focus

- **Over-discounting / negative total:** A fixed discount or gift card larger than `subtotal_amount` must cap at `subtotal_amount` so `total_amount` is `>= 0.00` and never negative.
- **Concurrent gift-card double-spend:** Two simultaneous checkouts using the same `ABC-GIFT-*` code must lock the `gift_cards` row (`lockForUpdate()`) so total redeemed never exceeds `remaining_balance`.
- **Double-void race condition:** Two concurrent void clicks on the same order must lock the `orders` row and allow only the first void to restore stock/gift-card balance.
- **Sticky Order Summary on short viewports (`1024×768` / `1440×900`):** The cart line list must scroll independently inside `#pos-order-panel` while the Payment Options, Totals, and `Complete Sale` CTA remain pinned and clickable without scrolling the page.
- **Stale/empty SKU collision:** Auto-generating a SKU when `sku` is left blank on product creation must guarantee uniqueness even if products are deleted or created with similar category prefixes.

---

### Task 1: Schema Extensions for Orders, Sales Notes & Gift Cards

**Files:**
- Create: `database/migrations/0001_01_01_000100_create_gift_cards_table.php`
- Create: `database/migrations/0001_01_01_000110_add_pos_enhancements_to_orders_and_sales.php`
- Modify: `app/Models/Order.php`
- Modify: `app/Models/Sale.php`
- Create: `app/Models/GiftCard.php`
- Test: `tests/Feature/CheckoutAndPricingTest.php`

**Interfaces:**
- Produces:
  - `gift_cards` table (`id` INT PK, `code` VARCHAR(40) UNIQUE, `initial_balance` DECIMAL(10,2), `remaining_balance` DECIMAL(10,2), `is_active` TINYINT(1), `expires_at` DATETIME NULL, `created_at`, `updated_at`)
  - `orders` columns: `order_type` VARCHAR(20) DEFAULT `'dine_in'`, `payment_method` VARCHAR(20) DEFAULT `'cash'`, `payment_reference` VARCHAR(80) NULL, `cup_name` VARCHAR(60) NULL, `order_note` VARCHAR(200) NULL, `discount_type` VARCHAR(20) DEFAULT `'none'`, `discount_label` VARCHAR(80) NULL, `discount_amount` DECIMAL(10,2) DEFAULT `0.00`, `gift_card_id` INT NULL FK -> `gift_cards(id)`, `status` VARCHAR(20) DEFAULT `'completed'`, `voided_at` DATETIME NULL, `voided_by` INT NULL FK -> `users(id)`, `void_reason` VARCHAR(255) NULL
  - `sales` column: `note` VARCHAR(120) NULL
  - `App\Models\GiftCard` model with `isUsable(): bool`

- [ ] **Step 1: Write failing migration/model schema test**

In `tests/Feature/CheckoutAndPricingTest.php`, add `test_orders_sales_and_gift_cards_schema_supports_new_pos_fields()` asserting `Schema::hasColumns('orders', ['order_type', 'payment_method', 'cup_name', 'discount_amount', 'gift_card_id', 'status', 'voided_at', 'voided_by'])`, `Schema::hasColumn('sales', 'note')`, and `Schema::hasTable('gift_cards')`.

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=test_orders_sales_and_gift_cards_schema_supports_new_pos_fields`
Expected: FAIL (`Failed asserting that false is true`).

- [ ] **Step 3: Create migrations and `GiftCard` model; update `Order` and `Sale` fillable/casts/relations**

Create `0001_01_01_000100_create_gift_cards_table.php` and `0001_01_01_000110_add_pos_enhancements_to_orders_and_sales.php` with `InnoDB`, `utf8mb4_unicode_ci`, signed `INT` FKs, and MariaDB `CHECK` constraints (`order_type IN ('dine_in', 'take_out')`, `payment_method IN ('cash', 'card', 'gcash')`, `status IN ('completed', 'voided')`, `discount_amount >= 0`, `remaining_balance >= 0`). Update `DemoSeeder` to seed 3 sample gift cards (`ABC-GIFT-500`, `ABC-GIFT-1000`, `ABC-GIFT-250`).

- [ ] **Step 4: Run migration and test to verify it passes**

Run: `php artisan migrate && php artisan test --filter=test_orders_sales_and_gift_cards_schema_supports_new_pos_fields`
Expected: PASS.

---

### Task 2: CheckoutService — Notes, Order Type, Card/GCash, Discounts & Gift Cards, Cup Name

**Files:**
- Modify: `app/Services/CheckoutService.php`
- Modify: `app/Http/Requests/PosCheckoutRequest.php`
- Modify: `app/Http/Requests/RecordSaleRequest.php`
- Modify: `app/Http/Controllers/PosController.php`
- Modify: `app/Http/Controllers/RecordSaleController.php`
- Test: `tests/Feature/CheckoutAndPricingTest.php`

**Interfaces:**
- Consumes: `GiftCard`, `Order`, `Sale`, `Money::toCentavos`, `Money::fromCentavos`
- Produces:
  - `CheckoutService::complete(User $staff, array $payload): CheckoutResult` accepting `order_type`, `payment_method`, `payment_reference`, `cup_name`, `order_note`, `discount_type`, `discount_value`, `gift_card_code`, and `items.*.note`.
  - `PosController::lookupGiftCard(Request $request): JsonResponse` (`GET /pos/gift-cards/lookup?code=...`) returning `{valid, code, remaining_balance, remaining_formatted}`.

- [ ] **Step 1: Write failing tests for Card/GCash, Discounts, Gift Card concurrency, Cup Name, Order Type, and Item Notes**

In `tests/Feature/CheckoutAndPricingTest.php`:
- `test_card_and_gcash_payments_require_no_cash_tender_and_record_reference()`
- `test_percent_fixed_and_gift_card_discounts_cap_at_subtotal_and_deduct_gift_card_balance()`
- `test_walk_in_cup_name_order_type_and_line_notes_persist_on_order_and_sales()`

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test tests/Feature/CheckoutAndPricingTest.php`
Expected: FAIL on missing fields/discount math.

- [ ] **Step 3: Implement checkout rules in `CheckoutService`, requests, and controllers**

- Validate `order_type` (`dine_in` / `take_out`), `payment_method` (`cash` / `card` / `gcash`), `cup_name` (max 60), `order_note` (max 200), `items.*.note` (max 120).
- Compute `subtotalCentavos` from line totals, apply `discount_type`:
  - `percent`: `intdiv($subtotalCentavos * $pct, 100)` (`1..100`)
  - `fixed`: `min($subtotalCentavos, Money::toCentavos($discountValue))`
  - `giftcard`: lock `gift_cards` row with `lockForUpdate()`, validate active & unexpired & `remaining_balance > 0`, deduct `min($subtotalCentavos, $remainingCentavos)`, update `remaining_balance`.
- `totalCentavos = max(0, $subtotalCentavos - $discountCentavos)`.
- For `card` / `gcash`: set `$cashReceivedCentavos = $totalCentavos`, `$changeCentavos = 0`.
- For `walk-in` with `cup_name`: set `cup_name = $cupName` and `customer_name_snapshot = "Walk-in · {$cupName}"`.
- Include all new fields in `computeRequestHash(...)` so checkout-token idempotency covers payment method, discount, cup name, and notes.
- Add `GET /pos/gift-cards/lookup` route and `PosController::lookupGiftCard()`.

- [ ] **Step 4: Run `CheckoutAndPricingTest` to verify it passes**

Run: `php artisan test tests/Feature/CheckoutAndPricingTest.php`
Expected: PASS.

---

### Task 3: Order Voiding (Direct Admin or Cashier Manager-Override Modal + Atomic Stock & Gift-Card Restoration)

**Files:**
- Modify: `app/Services/CheckoutService.php` (add `voidOrder(Order $order, User $approver, string $reason): Order`)
- Modify: `app/Policies/OrderPolicy.php` (keep `view` scoping; add `void(User $user, Order $order): bool` allowing admin on any order or cashier on own order subject to manager-override credential verification in controller)
- Modify: `app/Http/Controllers/SalesHistoryController.php` (add `voidOrder(Request $request, Order $order, CheckoutService $checkout)`)
- Modify: `app/Services/DashboardMetricsService.php` (exclude `status = 'voided'` from revenue, order count, units sold, and best sellers)
- Modify: `routes/web.php` (add `POST /orders/{order}/void` under `['auth', 'active']`)
- Test: `tests/Feature/OrderVoidTest.php`

**Interfaces:**
- Produces:
  - `CheckoutService::voidOrder(Order $order, User $approver, string $reason): Order`
  - Named route `orders.void` (`POST /orders/{order}/void`)

- [ ] **Step 1: Write failing tests in `tests/Feature/OrderVoidTest.php`**

- `test_admin_can_void_completed_order_restoring_product_stock_and_gift_card_balance()`
- `test_cashier_can_void_own_order_with_valid_admin_override_credentials_recording_approving_admin()`
- `test_cashier_void_fails_with_invalid_or_non_admin_credentials_and_already_voided_order_is_rejected()`
- `test_voided_orders_are_excluded_from_dashboard_revenue_and_unit_counts()`

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test tests/Feature/OrderVoidTest.php`
Expected: FAIL (route/method undefined).

- [ ] **Step 3: Implement `voidOrder`, `SalesHistoryController::voidOrder`, and Dashboard void exclusion**

- In `SalesHistoryController::voidOrder`, authorize `view` on `$order`. If `$request->user()->isAdmin()`, `$approver = $request->user()`. Otherwise (cashier), validate `admin_username` and `admin_password`, apply `RateLimiter` (`void-override`), verify active `admin` user and `Hash::check($adminPassword, $approver->password)` (returning 422 validation error on failure without altering the cashier's session).
- In `CheckoutService::voidOrder($order, $approver, $reason)`, lock `orders` row with `lockForUpdate()`, verify `status === 'completed'`, lock `products` in ascending `id` order, restore `stock_quantity += $qty` & `stock_version += 1`, restore `gift_cards.remaining_balance += discount_amount` if `gift_card_id` is set, mark `status = 'voided'`, `voided_at`, `voided_by = $approver->id`, `void_reason = $reason`.
- Filter `orders.status = 'completed'` in `DashboardMetricsService` queries (and join `orders` on `sales` queries to exclude voided sale lines).

- [ ] **Step 4: Run `OrderVoidTest` and `HistoryReceiptAndDashboardTest`**

Run: `php artisan test tests/Feature/OrderVoidTest.php tests/Feature/HistoryReceiptAndDashboardTest.php`
Expected: PASS.

---

### Task 4: Auto-Generated Product SKU & Consistent Image Placeholder Component

**Files:**
- Modify: `app/Http/Controllers/ProductController.php`
- Create: `resources/views/components/product-image.blade.php`
- Modify: `resources/views/products/index.blade.php`
- Modify: `resources/views/products/edit.blade.php`
- Modify: `resources/views/pos/index.blade.php`
- Test: `tests/Feature/CrudAndUploadTest.php`

**Interfaces:**
- Produces:
  - Automatic unique SKU generation (`ABC-{CAT3}-{XXXX}`) in `ProductController::store()` when `sku` is blank.
  - `<x-product-image :product="$product" size="card|thumb|preview" />` rendering either the prepared `imageUrl()` (`loading="lazy"`) or a consistent dark-bronze branded cup placeholder.

- [ ] **Step 1: Write failing test in `tests/Feature/CrudAndUploadTest.php`**

- `test_creating_product_without_sku_automatically_generates_unique_sku()`

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=test_creating_product_without_sku_automatically_generates_unique_sku`
Expected: FAIL (`sku` is `null`).

- [ ] **Step 3: Implement auto-SKU generation and `<x-product-image />`**

- In `ProductController::store()`, if `trim((string) ($data['sku'] ?? '')) === ''`, generate `ABC-{CAT3}-{4-digit-seq}` from the category name (or `MNU` if uncategorized) and loop on collision check until unique.
- Create `resources/views/components/product-image.blade.php` and replace ad-hoc `No Image` / `ABC` placeholder `<div>`s in `pos/index.blade.php`, `products/index.blade.php`, and `products/edit.blade.php`.

- [ ] **Step 4: Run `CrudAndUploadTest` to verify it passes**

Run: `php artisan test tests/Feature/CrudAndUploadTest.php`
Expected: PASS.

---

### Task 5: POS UI Upgrade — No-Scroll Sticky Order Summary, Notes, Dine-In/Take-Out, Cup Name, Card/GCash, Discounts/Giftcards

**Files:**
- Modify: `resources/views/pos/index.blade.php`
- Modify: `resources/js/pos.js`
- Modify: `resources/views/sales/record.blade.php`

**Interfaces:**
- Consumes: `/pos/checkout`, `/pos/gift-cards/lookup`, `catalogPayload`

- [ ] **Step 1: Restructure `#pos-order-panel` in `resources/views/pos/index.blade.php`**

- Set desktop panel to `lg:sticky lg:top-20 lg:max-h-[calc(100vh-6rem)] lg:flex lg:flex-col` with:
  - Fixed header + **Dine-in / Take-out** pill segmented toggle (`data-order-type`).
  - Scrollable middle section (`lg:flex-1 lg:min-h-0 lg:overflow-y-auto`) containing:
    - Cart lines with `48×48px` `<x-product-image>`-matching thumbnail, item note input/chip trigger (`Less Ice`, `Less Sugar`, `No Ice`, custom note), stepper, and price.
    - Customer picker + **Cup Name** input (visible for walk-ins, optional for any order) + **Order Note** input.
    - **Discount / Gift Card** collapsible/compact row (None / % / ₱ / Gift Card lookup button).
  - Fixed bottom footer (`shrink-0 pt-3 border-t border-white/10`) containing:
    - Compact **Payment Method** selector (`Cash`, `Card`, `GCash`) + conditional Cash Quick Pills (`Exact`, `₱200`, `₱500`, Custom input) or Reference # input for Card/GCash.
    - `Subtotal`, `Discount` (when > 0), `Change` (when Cash), `TOTAL`, inline error box, and dominant `Complete Sale · ₱X.XX` button — always visible without page scrolling!
- Add quick-note chips (`Less Ice`, `No Ice`, `Less Sugar`, `Extra Hot`) + custom note input inside `#pos-config-dialog`.

- [ ] **Step 2: Update `resources/js/pos.js` for all interactive state, discount/gift-card preview, payment methods, notes, and cup name**

- Update `buildLiveReceiptElement()` and `#pos-receipt-dialog` to show `Dine-in`/`Take-out`, `Cup Name`, `Order Note`, line notes, `Discount`, and `Payment Method`.

- [ ] **Step 3: Add `order_type`, `payment_method`, `cup_name`, and `note` controls to `resources/views/sales/record.blade.php`**

- [ ] **Step 4: Build Vite assets and run full PHPUnit suite**

Run: `npm run build && php artisan test --colors=never`
Expected: Clean Vite build and 0 test failures.

---

### Task 6: Sales History, Order Detail & Printable Receipt Void UI + Snapshot Display

**Files:**
- Modify: `resources/views/sales/index.blade.php`
- Modify: `resources/views/orders/show.blade.php`
- Modify: `resources/views/orders/receipt.blade.php`
- Modify: `resources/views/components/thermal-receipt.blade.php`

- [ ] **Step 1: Update Sales History, Order Detail, and Thermal Receipt views**

- Display `Dine-in` / `Take-out` badge, `Payment Method`, `Discount`, `Cup Name` / `Notes`, and `Voided` status badge across `sales/index.blade.php`, `orders/show.blade.php`, `orders/receipt.blade.php`, and `thermal-receipt.blade.php`.
- On `orders/show.blade.php` (when `$order->status === 'completed'`), show a **Void Order** action: Admins submit `void_reason` directly; Cashiers click **Void Order (Manager Approval)** to open a popup `<dialog>` modal requiring `admin_username`, `admin_password`, and `void_reason`, posting to `route('orders.void', $order)` without signing out the cashier.

- [ ] **Step 2: Run full verification (comment check, `npm run build`, `php artisan test`, live HTTP check)**

Run: `npm run build` and `php artisan test --colors=never`
Expected: All tests pass with 0 failures and 0 authored comments.

---

## Execution Handoff

Plan complete and saved to `docs/superpowers/plans/2026-11-27-pos-enhancements.md`. Please review the plan. Which execution approach would you prefer?

- **Subagent-driven** — A fresh subagent implements each task and a fresh reviewer checks it before the next one starts, then a whole-branch review at the end. Most thorough; costs a fresh context per task and per review.
- **Native** — I implement every task myself in this session, the way this harness runs work, then one fresh reviewer on the most capable model checks the whole branch. Cheapest and fastest; no independent review until the end. Runs well with a mid-tier session model, since the plan carries the design.

For this plan I recommend **Native**, because the 6 tasks share tightly coupled `CheckoutService` and `pos.js` interfaces and can be implemented and verified sequentially in one clean pass. Does the plan capture what you want, and which approach should we use?
