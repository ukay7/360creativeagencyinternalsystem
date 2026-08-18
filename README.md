# 360 Creative Agency Internal System

Laravel and MySQL edition of the 360 Creative Agency operating system.

## Technology

- Laravel 13
- PHP 8.4
- MySQL 8 on Laragon port 3307
- Blade, Bootstrap/SmartAdmin, Chart.js

## Local database

Database: `360_creative_agency_internal_new`

```powershell
php artisan migrate:fresh --seed
```

## Start

From this project folder, with Laragon MySQL running:

```powershell
php artisan serve --host=127.0.0.1 --port=8097
```

Open `http://127.0.0.1:8097`.

## Demo administrator

- Email: `admin@agencyos.local`
- Password: `Admin@360!`

This fallback login is available only outside production. For production, set
`SEED_ADMIN_NAME`, `SEED_ADMIN_EMAIL`, and a strong `SEED_ADMIN_PASSWORD` in
the server's `.env` before running the database seeder.

## Production deployment

Requirements: PHP 8.3 or newer, Composer, MySQL, and the PHP MySQL extension.

```bash
git clone https://github.com/ukay7/360creativeagencyinternalsystem.git
cd 360creativeagencyinternalsystem
composer install --no-dev --optimize-autoloader
cp .env.example .env
php artisan key:generate
```

Edit `.env` with the production URL, MySQL connection, and seed administrator
credentials. Then initialize and optimize the application:

```bash
php artisan migrate --seed --force
php artisan storage:link
php artisan optimize
```

Point the website's document root to the project's `public` directory. The web
server must be able to write to `storage` and `bootstrap/cache`. Never upload or
commit the production `.env` file.

## Tests

```powershell
php artisan test
```

Feature tests cover authentication, every system module, and a transactional lead-to-opportunity workflow against MySQL.
