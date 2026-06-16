# TRETECH Backend Deployment on Hostinger Premium Shared Hosting

This backend can run on Hostinger Premium shared hosting, but the deployment needs to respect two constraints:

1. This project requires PHP `^8.3` and Laravel `^13.0`.
2. Shared hosting does not give you a persistent process manager, so queues and scheduled tasks must be handled with cron-friendly commands.

Target domain for this deployment:

- `https://tretech-be.mysztechnology.com`

Mobile base URL after deployment:

- `API_BASE_URL=https://tretech-be.mysztechnology.com`

Do not append `/api/v1` to `API_BASE_URL`, because the Flutter app already prefixes every endpoint with `/api/v1`.

## 1. Hostinger-side prerequisites

Before uploading the project:

1. Create the subdomain `tretech-be.mysztechnology.com` in Hostinger hPanel.
2. Create a MySQL database and database user for this backend.
3. Ensure the hosting plan is using PHP `8.3` or newer.
4. Enable SSH access for the hosting account.

Preferred document-root setup:

- Point the subdomain document root directly to the Laravel `public` directory.

Fallback if Hostinger keeps the document root at the project root:

- Use the rewrite file in `deploy/hostinger/root.htaccess.example` as the root `.htaccess`.

## 2. Upload layout

Recommended layout on the server:

```text
/home/USERNAME/.../tretech-backend/
  app/
  bootstrap/
  config/
  database/
  public/
  storage/
  vendor/
  artisan
  .env
```

Upload the full Laravel project, not only the `public` directory.

## 3. Production environment file

Use `deploy/hostinger/.env.hostinger.example` as the starting point for the server `.env`.

Minimum values you must replace:

- `APP_KEY`
- `DB_DATABASE`
- `DB_USERNAME`
- `DB_PASSWORD`
- `CORS_ALLOWED_ORIGINS`

Recommended production values:

- `APP_ENV=production`
- `APP_DEBUG=false`
- `APP_URL=https://tretech-be.mysztechnology.com`

## 4. Composer install

This project requires PHP 8.3+. On Hostinger shared hosting, the PHP version used by SSH/Composer can differ from the PHP version used by the website, so call Composer with an explicit PHP binary when needed.

From the project root on the server:

```bash
/opt/alt/php84/usr/bin/php /usr/local/bin/composer2 install --no-dev --optimize-autoloader
```

If your hosting plan default CLI PHP is already 8.3+, plain `composer2 install --no-dev --optimize-autoloader` may also work.

## 5. Laravel bootstrap commands

Run these from the project root on the server:

```bash
php artisan key:generate --force
php artisan migrate --force
php artisan db:seed --force
php artisan storage:link
php artisan config:cache
php artisan route:cache
php artisan event:cache
```

Notes:

- `db:seed --force` is required because production mode blocks unforced seeding.
- `DatabaseSeeder` always creates permissions, admin user, and sample master data.
- `UatSeeder` only runs in `local`, `staging`, or `uat`, so production will not load the UAT scenario data.

## 6. Queue strategy on shared hosting

This backend dispatches queued work, including `PushUsageSummaryJob`.

Shared hosting is not a good place for a long-running `queue:work` process. Use one of these two options:

### Option A: safest for shared hosting

Set this in `.env`:

```env
QUEUE_CONNECTION=sync
```

Effect:

- queued jobs run during the web request
- simplest deployment
- less background reliability, but no worker management needed

### Option B: database queue with cron-triggered worker

Set this in `.env`:

```env
QUEUE_CONNECTION=database
```

Then add a cron job like this:

```bash
*/5 * * * * /usr/bin/php /home/USERNAME/PATH/TO/tretech-backend/artisan queue:work --stop-when-empty --tries=1 >> /dev/null 2>&1
```

Use this only if you really need background job processing.

## 7. Scheduler cron

This project defines scheduled commands in `routes/console.php`, including:

- daily expiry check
- retry failed ERP pushes every 15 minutes

Add this cron job in Hostinger:

```bash
* * * * * /usr/bin/php /home/USERNAME/PATH/TO/tretech-backend/artisan schedule:run >> /dev/null 2>&1
```

## 8. Public routing

If the subdomain document root is already the Laravel `public` folder, use the normal Laravel `public/.htaccess`.

If the subdomain document root is the project root instead, place this file as root `.htaccess`:

- `deploy/hostinger/root.htaccess.example`

That forwards all requests into the `public/` directory, matching Hostinger's manual Laravel deployment pattern.

## 9. Health-check and API test URLs

After deployment, test these URLs first:

- `https://tretech-be.mysztechnology.com/api/health`
- `https://tretech-be.mysztechnology.com/api/v1/health`

Then test login:

- `POST https://tretech-be.mysztechnology.com/api/v1/auth/login`

If health works but login fails, the problem is usually one of:

1. wrong database credentials
2. migrations not run
3. seeders not run
4. PHP CLI version mismatch during Composer install

## 10. Flutter mobile configuration

In `tretech-mobile`, the Dio base URL comes from:

- `lib/core/network/dio_client.dart`

And the app endpoints already include:

- `/api/v1`

So the correct mobile value is:

```bash
flutter run --dart-define=API_BASE_URL=https://tretech-be.mysztechnology.com
```

Release build example:

```bash
flutter build apk --release --dart-define=API_BASE_URL=https://tretech-be.mysztechnology.com
```

Do not use:

```text
https://tretech-be.mysztechnology.com/api/v1
```

That would duplicate the version prefix in requests.

## 11. Recommended first production login

The seeded admin defaults come from environment-backed config:

- `ADMIN_EMAIL`
- `ADMIN_NAME`
- `ADMIN_PASSWORD`

Set these explicitly in production `.env` before running `php artisan db:seed --force`.

## 12. Troubleshooting checklist

If the site shows 500 errors:

1. Check `.env` values.
2. Confirm PHP version is 8.3+ in both browser runtime and CLI runtime.
3. Confirm `vendor/` exists after Composer install.
4. Confirm `storage/` and `bootstrap/cache/` are writable.
5. Run:

```bash
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan event:cache
```

6. Inspect `storage/logs/laravel.log`.
