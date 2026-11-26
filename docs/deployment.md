# Production Deployment Guide — Artisan Brew Co. (ABC)

Target environment: **PHP 8.2+**, **MySQL 8.0+ or MariaDB 10.4+ (InnoDB, `utf8mb4`)**, Apache/Nginx/Laravel Forge/Cloudways/VPS/Render-compatible PHP host.

---

## 1. Web Server & Document Root

- Point the web server's document root strictly to **`public/`** (never the repository root, so `.env`, `storage/`, and `composer.json` are never web-accessible).
- Enable URL rewriting (`mod_rewrite` + `AllowOverride All` on Apache `public/.htaccess`, or `try_files $uri $uri/ /index.php?$query_string;` on Nginx).

---

## 2. Production Environment Configuration (`.env`)

Create `.env` on the host (never commit `.env` to Git):

```env
APP_NAME="Artisan Brew Co."
APP_ENV=production
APP_KEY=base64:... # generate once with: php artisan key:generate
APP_DEBUG=false
APP_URL=https://your-domain.example
APP_TIMEZONE=UTC
POS_DISPLAY_TIMEZONE=Asia/Manila

DB_CONNECTION=mariadb
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=artisan_brew_co
DB_USERNAME=abc_app_user
DB_PASSWORD=strong-database-password
DB_CHARSET=utf8mb4
DB_COLLATION=utf8mb4_unicode_ci

SESSION_DRIVER=database
CACHE_STORE=database
SESSION_HTTP_ONLY=true
SESSION_SAME_SITE=lax
SESSION_SECURE_COOKIE=true
```

---

## 3. Dependencies, Assets & Permissions

1. Install production PHP dependencies:
   ```bash
   composer install --no-dev --optimize-autoloader
   ```
2. Compiled Vite assets are committed in `public/build/` (no Node server needed at runtime). If rebuilding on a CI/build node:
   ```bash
   npm ci && npm run build
   ```
3. Ensure the web server user can write to `storage/` and `bootstrap/cache/`:
   ```bash
   chmod -R 775 storage bootstrap/cache
   ```

---

## 4. Database Setup & First Admin Creation

1. Run schema migrations:
   ```bash
   php artisan migrate --force
   ```
2. Create the initial production Administrator account (do **not** run `abc:seed-demo` in production):
   ```bash
   php artisan abc:create-admin
   ```
3. Cache Laravel configuration, routes, and views:
   ```bash
   php artisan config:cache
   php artisan route:cache
   php artisan view:cache
   ```

---

## 5. Prepared Media Access Security

Uploaded product thumbnails and staff avatars are decoded, cropped, and re-encoded via GD (`ImagePreparationService`) and stored **outside `public/`** in `storage/app/private/media/`. They are served exclusively to authenticated active staff through the `media.show` route (`/media/{type}/{filename}`) with strict filename regex validation and `X-Content-Type-Options: nosniff`. No `storage:link` symlink exposing raw uploads in `public/storage` is required.
