# Requirements Checklist & Source Mapping

> **Notice on Original PDF:** The original **IT0049 - Midterm Project - Complete Point-of-Sale System (CodeIgniter)** PDF was not present in this workspace (`c:\xampp\htdocs\Artisan Brew Co`). Consequently, this implementation follows the transcribed requirements, rubric weights, and SQL schema provided in the prompt, and documents **Laravel 12** as the user's explicit framework clarification/override rather than the wording of the original PDF.

---

## 1. Source-to-Implementation Mapping

| Source | Requirement to Preserve | Implementation Evidence | Status |
| --- | --- | --- | --- |
| Explicit framework clarification | Implement this project in Laravel | Laravel `12.74.0` MVC (`routes/web.php`, `app/Models/`, `app/Http/Controllers/`, `app/Http/Requests/`, `app/Services/`, `resources/views/`, `artisan` commands, `README.md`) | Verified |
| PDF pp. 4–5, Activity 1 | Required `products`, `customers`, `users`, and `sales` schema and foreign keys | `database/migrations/0001_01_01_000010`–`000090`, MariaDB `SHOW CREATE TABLE`, [docs/database-design.md](database-design.md) | Verified |
| PDF p. 5, Activity 2 | Product list/add/edit/delete or archive, with prepared uploaded images | `ProductController`, `ProductRequest`, `ImagePreparationService`, `MediaController`, `resources/views/products/*`, `CrudAndUploadTest` | Verified |
| PDF p. 5, Activities 3–4 | Full customer/staff CRUD, staff avatar preparation, hashed passwords | `CustomerController`, `StaffController`, `ImagePreparationService::prepareAvatar`, `Hash::make` + `hashed` cast, `CrudAndUploadTest` | Verified |
| PDF p. 5, Activity 5 | Only authenticated staff can access management pages | `auth` + `active` + `role:admin` middleware in `bootstrap/app.php` and `routes/web.php`, `AuthenticationAndAuthorizationTest` | Verified |
| PDF p. 5, Activity 6 | Record Sale page, optional customer, quantity, stock decrease, clear overselling rejection | Dedicated `/sales/record` (`RecordSaleController`, `resources/views/sales/record.blade.php`) backed by `CheckoutService`, `CheckoutAndPricingTest` | Verified |
| PDF p. 5, Activity 7 | Sales History with product, customer, staff, quantity, total price, and date | `SalesHistoryController@index`, `resources/views/sales/index.blade.php`, `HistoryReceiptAndDashboardTest` | Verified |
| PDF p. 5, Submission deliverables | GitHub repo with raw files + migrations/seeders; hosted working application link | Repo `https://github.com/kcyKael/Artisan-Brew-Co.git` prepared locally (push held pending user permission); Hosted URL pending external PHP/MariaDB host | Incomplete (external hosting & push permission) |
| User choices in prompt | Dark premium ABC branding, dashboard/charts, multi-item cart, receipts, admin/cashier roles, drink options/add-ons | `resources/css/app.css`, `DashboardController`, `PosController`, `orders/receipt.blade.php`, `MenuConfigController`, Policies & Role Middleware | Verified |

---

## 2. IT0049 Rubric Coverage

| Rubric Area (Points) | Implemented Controls & Verification |
| --- | --- |
| **Architecture / Design (20)** | Thin controllers, dedicated Form Requests (`app/Http/Requests/*`), service layer (`CheckoutService`, `ImagePreparationService`, `DashboardMetricsService`), Eloquent models & relationships, Blade components, dark luxury palette (`#111110`, `#1B1A18`, `#24211D`, `#C6A66B`, `#654737`), responsive layouts. |
| **Functionality (30)** | Full CRUD for Products/Customers/Staff, Menu Configuration (categories, variants, add-ons), Multi-Item POS (`/pos`), Dedicated Record Sale (`/sales/record`), Printable 80mm/A4 Receipts, Sales History with Manila date filters & search, Real Chart.js Dashboard. |
| **Validation / Security (20)** | Session auth with `username` + `RateLimiter`, CSRF protection, mass-assignment exclusions (`role`, `is_active` guarded), Policies & Role Middleware, security headers + CSP (`SecurityHeaders`), GD image re-encoding & MIME/magic-byte validation, pessimistic `lockForUpdate()` + atomic stock guard + idempotent checkout tokens. |
| **Code Quality (15)** | Shared centavo math (`Money`), Manila/UTC helpers (`BusinessTime`), single `CheckoutService` shared by POS and Record Sale, 19 PHPUnit tests (204 assertions) including genuine 2-process MariaDB concurrency tests. |
| **Documentation / Deployment (15)** | `README.md`, `docs/database-design.md`, `docs/requirements-checklist.md`, `docs/skills-used.md`, `docs/test-results.md`, `docs/deployment.md`, compiled `public/build/` assets, `composer audit` & `npm audit` (0 advisories). |
