# Developer Local Setup

This guide is for a developer who needs to run the SPU Laravel website locally and make changes safely.

## What To Install

Install these before running the project:

- Git
- PHP 8.2 or newer
- Composer
- Node.js 20 or newer
- npm, installed with Node.js
- MySQL 8, or a local stack that includes MySQL such as Laragon, XAMPP, MAMP, or WAMP

Redis is not required for local development when `CACHE_STORE=array` is used.

Required PHP extensions should be enabled:

- `pdo_mysql`
- `mbstring`
- `openssl`
- `tokenizer`
- `xml`
- `ctype`
- `curl`
- `fileinfo`
- `zip`
- `intl`

Check installed versions:

```bash
git --version
php -v
composer -V
node -v
npm -v
mysql --version
```

If `mysql --version` is not recognized, MySQL may still work through Laragon/XAMPP/phpMyAdmin. The important part is that a MySQL server is running.

## First Setup

Clone the repository:

```bash
git clone <repo-url> SPU_Website
cd SPU_Website
```

Install backend dependencies:

```bash
composer install
```

Install frontend dependencies:

```bash
npm install
```

Create the Laravel environment file.

Windows PowerShell:

```powershell
Copy-Item .env.example .env
```

macOS/Linux/Git Bash:

```bash
cp .env.example .env
```

Generate the application key:

```bash
php artisan key:generate
```

## Required `.env` Values

Open `.env` and set these values for local development:

```env
APP_NAME="SPU Website"
APP_ENV=local
APP_DEBUG=true
APP_URL=http://127.0.0.1:8000

APP_LOCALE=ar
APP_FALLBACK_LOCALE=ar

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=spu_website
DB_USERNAME=root
DB_PASSWORD=
DB_CHARSET=utf8mb4
DB_COLLATION=utf8mb4_unicode_ci

ADMIN_NAME="SPU Super Admin"
ADMIN_EMAIL=admin@spu.edu.sy
ADMIN_PASSWORD=local-development-password

CACHE_STORE=array
SESSION_DRIVER=database
QUEUE_CONNECTION=database
FILESYSTEM_DISK=local
MAIL_MAILER=log
SENTRY_LARAVEL_DSN=

VITE_APP_NAME="SPU Website"
```

If MySQL uses a password, set it in `DB_PASSWORD`.

Use `CACHE_STORE=array` locally. The default `database` cache store can run, but it does not support cache tags and causes launch validation warnings.

## Create The Database

Start MySQL first.

If the MySQL CLI works and root has a password:

```bash
mysql -u root -p -e "CREATE DATABASE IF NOT EXISTS spu_website CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
```

If root has no password:

```bash
mysql -u root -e "CREATE DATABASE IF NOT EXISTS spu_website CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
```

If the MySQL CLI is not available, create a database named `spu_website` manually in phpMyAdmin.

## Build The Local Site

For a fresh local setup that matches the seeded repository version, run:

```bash
php artisan migrate:fresh --seed
php artisan storage:link
php artisan optimize:clear
npm run build
```

If `php artisan storage:link` says the link already exists, that is fine.

## Run The Website

Start Laravel:

```bash
php artisan serve --host=127.0.0.1 --port=8000
```

Open these URLs:

```text
http://127.0.0.1:8000/ar
http://127.0.0.1:8000/en
http://127.0.0.1:8000/admin
```

Admin login:

```text
Email: admin@spu.edu.sy
Password: local-development-password
```

## Run While Editing Frontend Assets

Use two terminals.

Terminal 1:

```bash
php artisan serve --host=127.0.0.1 --port=8000
```

Terminal 2:

```bash
npm run dev
```

Open:

```text
http://127.0.0.1:8000/en
```

Alternative single command:

```bash
composer dev
```

`composer dev` starts Laravel, the queue listener, log tailing, and Vite together.

## After Pulling New Code

Run these after `git pull`:

```bash
composer install
npm install
php artisan migrate --seed
php artisan optimize:clear
npm run build
```

If navigation or seeded pages look outdated, run:

```bash
php artisan db:seed --class=NavigationSeeder
php artisan db:seed --class=LandingPageSeeder
php artisan optimize:clear
```

## Exact Copy Of Another Developer's Local Site

The seeded setup is enough for normal development. If the site must match another developer's local CMS/admin content exactly, use a database dump.

On the source machine:

Windows PowerShell:

```powershell
cmd /c "mysqldump -u root -p spu_website > spu_website.sql"
```

macOS/Linux/Git Bash:

```bash
mysqldump -u root -p spu_website > spu_website.sql
```

On the receiving machine, create the database first:

```bash
mysql -u root -p -e "CREATE DATABASE IF NOT EXISTS spu_website CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
```

Import the dump:

Windows PowerShell:

```powershell
cmd /c "mysql -u root -p spu_website < spu_website.sql"
```

macOS/Linux/Git Bash:

```bash
mysql -u root -p spu_website < spu_website.sql
```

Then run:

```bash
composer install
npm install
php artisan migrate
php artisan storage:link
php artisan optimize:clear
npm run build
```

Do not run `php artisan migrate:fresh --seed` after importing a dump unless you want to delete the imported data.

## Useful Verification Commands

Run these if something looks wrong:

```bash
php artisan migrate:status
php artisan route:list --path=forms
php artisan route:list --path=research/conferences/register
php artisan route:list --path=campus-life/career-development/jobs
php artisan view:cache
php artisan continuity:validate-seo
php artisan continuity:validate-redirects
php artisan launch:validate
npm run build
```

Run focused tests for the imported frontend pages/forms/header:

```bash
php artisan test "tests\Feature\MissingFrontendPagesTest.php" "tests\Feature\HeaderNavigationRenderingTest.php" "tests\Feature\DynamicFormPageRenderingTest.php" "tests\Feature\DynamicFormSubmissionTest.php"
```

Run the full test suite when needed:

```bash
php artisan test
```

## Important URLs To Check

Check these after setup:

```text
http://127.0.0.1:8000/ar
http://127.0.0.1:8000/en
http://127.0.0.1:8000/admin
http://127.0.0.1:8000/en/about/accreditation
http://127.0.0.1:8000/en/about/why-spu
http://127.0.0.1:8000/en/campus-life/career-development/jobs
http://127.0.0.1:8000/en/campus-life/career-development/jobs/apply
http://127.0.0.1:8000/en/e-services/suggestions-complaints
http://127.0.0.1:8000/en/research/conferences/register?event=conf-001
http://127.0.0.1:8000/en/research/conferences/register?event=conf-002
```

## Common Problems

If the site says the application key is missing:

```bash
php artisan key:generate
php artisan optimize:clear
```

If database tables are missing:

```bash
php artisan migrate --seed
php artisan optimize:clear
```

If `/admin/dynamic-form-submissions` says `dynamic_form_submissions` does not exist:

```bash
php artisan migrate
```

If header links or dropdowns are missing:

```bash
php artisan db:seed --class=NavigationSeeder
php artisan optimize:clear
```

If SEO validation reports missing OG images on seeded pages:

```bash
php artisan db:seed --class=LandingPageSeeder
php artisan continuity:validate-seo
```

If CSS or JavaScript changes do not appear:

```bash
npm run build
php artisan optimize:clear
```

If Vite dev mode is running, refresh the browser after:

```bash
npm run dev
```

If cache tag warnings appear during local validation, confirm this is in `.env`:

```env
CACHE_STORE=array
```

Then run:

```bash
php artisan optimize:clear
```

If MySQL connection fails, confirm MySQL is running and the `.env` database values match the local MySQL username/password.

## Development Notes

The app is bilingual. Check Arabic and English pages after changing public views.

The admin panel is Filament at `/admin`.

CMS-managed content needs database seed data or a database dump.

Do not use `migrate:fresh --seed` on a database that contains work you want to keep.

Use `npm run dev` while editing frontend assets, and `npm run build` before handing work back.
