# Getting started

Local setup on Windows with [Laravel Herd](https://herd.laravel.com). The project folder is `C:\Users\Admin\Herd\craft-woman` and the site is served at `http://craft-woman.test`.

## Requirements

| Tool | Version |
|---|---|
| PHP | 8.2+ (comes with Herd) |
| Composer | 2.x (comes with Herd) |
| Node.js | 20+ |
| Database | SQLite (default, file `database/database.sqlite`) |

The design documents target PostgreSQL. The code currently runs and is tested on SQLite only; see [roadmap.md](../07-status/roadmap.md#known-gaps).

## First run

PowerShell 5 doesn't understand `&&`. Run each command on its own line, or join them with `;`.

```powershell
composer install
npm install
copy .env.example .env
php artisan key:generate
php artisan migrate:fresh --seed
php artisan storage:link
npm run dev
```

`.env` values the SPA needs:

```env
APP_URL=http://craft-woman.test
APP_LOCALE=uk
SANCTUM_STATEFUL_DOMAINS=craft-woman.test
SESSION_DOMAIN=null
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
| Back-end tests | `php artisan test` |
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
| A new message key shows as `workshops.title` | The key is missing in `resources/js/i18n/uk.json` or `en.json` — run `npm run i18n:check` |
