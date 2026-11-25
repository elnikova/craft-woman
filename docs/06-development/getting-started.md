# Getting started

Local setup on Windows with [Laravel Herd](https://herd.laravel.com). The project folder is `C:\Users\Admin\Herd\craft-woman` and the site is served at `http://craft-woman.test`.

## Requirements

| Tool | Version |
|---|---|
| PHP | 8.2+ (comes with Herd) |
| Composer | 2.x (comes with Herd) |
| Node.js | 20+ |
| PostgreSQL | 16+ (tested on 16) |
| PHP extension | `pdo_pgsql` |

## PostgreSQL

1. Install PostgreSQL in one of these ways:
   - **Herd Pro**: Services → add PostgreSQL;
   - **The official Windows installer** from postgresql.org (EDB). It includes pgAdmin. Remember the password you set for the `postgres` user.
2. Check that PHP can talk to it: `php -m | findstr pgsql` should list `pdo_pgsql` and `pgsql`. If it doesn't, enable `extension=pdo_pgsql` in Herd's `php.ini` (Herd → PHP → the version → open php.ini) and restart Herd.
3. Create two databases, one for the site and one for tests. Use pgAdmin, or run this in PowerShell:
   ```powershell
   psql -U postgres -c "create database craft_woman"
   psql -U postgres -c "create database craft_woman_test"
   ```
   `psql` lives in `C:\Program Files\PostgreSQL\<version>\bin`. Add that folder to `PATH`, or run it from there.

Tests use `craft_woman_test` (set in `phpunit.xml`) and take the host, user and password from `.env`. Each test wipes and rebuilds that database, so never point it at real data.

Without PostgreSQL, tests can still run on in-memory SQLite: `php artisan test --configuration=phpunit.sqlite.xml`. One test that needs PostgreSQL (case-insensitive Cyrillic search) is skipped there.

## First run

PowerShell 5 doesn't understand `&&`. Run each command on its own line, or join them with `;`.

```powershell
composer install
npm install
copy .env.example .env      # then set DB_PASSWORD
php artisan key:generate
php artisan migrate:fresh --seed
php artisan storage:link
npm run dev
```

`.env` values to check:

```env
APP_URL=http://craft-woman.test
APP_LOCALE=uk
SANCTUM_STATEFUL_DOMAINS=craft-woman.test
SESSION_DOMAIN=null

DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=craft_woman
DB_USERNAME=postgres
DB_PASSWORD=your-password
```

`APP_LOCALE=uk` makes Ukrainian the API's default when a request has no `Accept-Language` header, matching the SPA. Older `.env` files have `en`.

Open `http://craft-woman.test`. Keep `npm run dev` running in its own terminal.

## Demo data

`php artisan migrate:fresh --seed` wipes the database and fills it with demo data.

| Who | E-mail | Password |
|---|---|---|
| Administrator | `admin@craftwoman.local` | `admin123` |
| Makers (approved) | `oksana@`, `marko@`, `iryna@`, `luka@craftwoman.local` | `password` |
| Maker (in review) | `sofia@craftwoman.local` | `password` |

Luka accepts prepayment only (no cash on delivery), and Iryna doesn't ship abroad — handy for testing checkout rules. Seeders also create categories, products, two courses (Oksana, Iryna) and three upcoming workshops (Marko, Oksana online, Iryna). Workshop dates are relative to the day you seed, so re-seed if they have passed.

There's no real payment yet. An unpaid order has a **«Оплатити (тестова оплата)»** button that marks it paid. It works only in the `local` and `testing` environments.

## Daily commands

| Task | Command |
|---|---|
| Dev server for the SPA | `npm run dev` |
| Production build (also type-checks) | `npm run build` |
| Type-check only | `npx vue-tsc --noEmit` |
| Check UI translations | `npm run i18n:check` |
| Back-end tests (PostgreSQL) | `php artisan test` |
| Back-end tests without PostgreSQL | `php artisan test --configuration=phpunit.sqlite.xml` |
| One test file or group | `php artisan test --filter=Workshops` |
| Code style (PHP) | `vendor/bin/pint` |
| Reset the database | `php artisan migrate:fresh --seed` |
| List API routes | `php artisan route:list --path=api` |

## Git workflow

- `main` holds what has been reviewed. Each feature is a branch (`feature/…`) with a pull request in English.
- Features are stacked: each branch starts from the previous one. Merge pull requests in order, oldest first.
- After pulling a feature branch, run `composer install`, `npm install` and `php artisan migrate:fresh --seed`. Migrations are still edited in place before the first release, so `migrate` alone may not be enough.

## Troubleshooting

| Symptom | Fix |
|---|---|
| `The token '&&' is not a valid statement separator` | PowerShell 5 — use `;` or separate lines |
| 419 / "CSRF token mismatch" after login | Check `SANCTUM_STATEFUL_DOMAINS` and `APP_URL`, then `php artisan config:clear` |
| Images or covers return 404 | `php artisan storage:link` |
| Page styles missing, textures 404 | `npm run dev` isn't running, or a stale `public/hot` file is left after it stopped: delete `public/hot` |
| "No upcoming workshops" | Seeded dates have passed — re-seed |
| `could not find driver` | `pdo_pgsql` isn't enabled — see step 2 under PostgreSQL |
| `password authentication failed for user "postgres"` | `DB_PASSWORD` in `.env` doesn't match the one set during installation |
| `database "craft_woman_test" does not exist` | Create it (step 3); tests need it |
| A new message key shows as `workshops.title` | The key is missing in `resources/js/i18n/uk.json` or `en.json` — run `npm run i18n:check` |
