# Getting started

Local setup on Windows with [Laravel Herd](https://herd.laravel.com). To run everything in containers instead, see [docker.md](docker.md). The project folder is `C:\Users\Admin\Herd\craft-woman` and the site is served at `http://craft-woman.test`.

## Requirements

| Tool | Version |
|---|---|
| PHP | 8.2+ (comes with Herd) |
| Composer | 2.x (comes with Herd) |
| Node.js | 20+ |
| PostgreSQL | 16+ (tested on 16 and 19) |
| PHP extension | `pdo_pgsql` |

## PostgreSQL

### Option A: official installer (recommended)

1. Download the Windows installer from postgresql.org → Download → Windows (EDB). The project runs on 16+ and is used with 19.
2. Run it **as administrator**. Select the components **PostgreSQL Server**, **pgAdmin 4** and **Command Line Tools**. Set a password for `postgres` and keep port `5432`.
3. Add `C:\Program Files\PostgreSQL\<version>\bin` to the user `PATH`, then restart PhpStorm. Restarting only the terminal tab isn't enough.
4. Check: `Get-Service *postgres*` shows `Running`.

**If the installer stopped half-way** (`Get-Service` shows nothing, and `data\postgresql.conf` is missing while `bin\postgres.exe` exists), initialise the cluster yourself in PowerShell **as administrator**. Type the password when asked, and run the commands one at a time:

```powershell
$pg = "C:\Program Files\PostgreSQL\19"
& "$pg\bin\initdb.exe" -D "$pg\data" -U postgres -W -E UTF8 --locale-provider=builtin --builtin-locale=C.UTF-8
icacls "$pg\data" /grant "NT AUTHORITY\NetworkService:(OI)(CI)F"
& "$pg\bin\pg_ctl.exe" register -N postgresql-x64-19 -D "$pg\data" -S auto -U "NT AUTHORITY\NetworkService"
Start-Service postgresql-x64-19
```

`--builtin-locale=C.UTF-8` (PostgreSQL 17+) gives Unicode case folding, so case-insensitive search works for Cyrillic. `initdb` warns that local connections use `trust` (no password check). That is fine for development. To require the password, change `trust` to `scram-sha-256` in `data\pg_hba.conf` and restart the service.

### Option B: Docker

```powershell
docker run -d --name craft-pg -e POSTGRES_PASSWORD=secret -p 5432:5432 -v craft-pg:/var/lib/postgresql/data postgres:17
```

Start it again after a reboot with `docker start craft-pg`. Run `psql` inside the container: `docker exec craft-pg psql -U postgres …`.

### Databases and PHP

1. PHP needs `pdo_pgsql`: `php -m | findstr pgsql`. If it is missing, enable `extension=pdo_pgsql` in the `php.ini` that `php --ini` points to, then restart Herd.
2. Create the databases:
   ```powershell
   psql -U postgres -c "create database craft_woman"
   psql -U postgres -c "create database craft_woman_test"
   ```
   pgAdmin works too: Servers → PostgreSQL → Databases → Create → Database.

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
| `psql` is not recognized | Its `bin` folder isn't in `PATH`, or PhpStorm was started before you added it |
| `Connection refused` on port 5432 | The server isn't running: `Get-Service *postgres*`; if nothing is listed, see "If the installer stopped half-way" |
| `could not find driver` | `pdo_pgsql` isn't enabled — see step 2 under PostgreSQL |
| `password authentication failed for user "postgres"` | `DB_PASSWORD` in `.env` doesn't match the one set during installation |
| `database "craft_woman_test" does not exist` | Create it (step 3); tests need it |
| A new message key shows as `workshops.title` | The key is missing in `resources/js/i18n/uk.json` or `en.json` — run `npm run i18n:check` |
