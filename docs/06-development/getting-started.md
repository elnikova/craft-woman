# Getting started

The project runs entirely in Docker: PHP, nginx, PostgreSQL and Vite. You need nothing else installed — no PHP, Composer, Node.js or PostgreSQL on Windows. To run it natively with Herd instead, see [without-docker.md](without-docker.md).

## Requirements

| Tool | Notes |
|---|---|
| [Docker Desktop](https://www.docker.com/products/docker-desktop/) | With the WSL 2 backend. To keep images and data off drive C:, install it from an administrator PowerShell with `--installation-dir` and `--wsl-default-data-root` (see [docker.md](docker.md#installing-docker-desktop-on-another-drive)) |
| Git | |
| PhpStorm (optional) | Settings for the Docker interpreter are below |

## First run

PowerShell 5 doesn't understand `&&`. Run the commands one per line.

```powershell
copy .env.example .env
.\dev up
.\dev artisan key:generate
.\dev fresh
```

The first `up` takes 5–10 minutes: it builds the PHP image and runs `composer install` and `npm install` inside the containers. `.\dev ready` tells you when both are done.

Open **http://localhost:8080**.

`dev.cmd` in the project root is a shortcut for the `docker compose` commands. It works in PowerShell and cmd and needs no execution-policy changes. Run `.\dev` with no arguments to list the commands. Inside WSL (see [docker.md → Speed](docker.md#speed)) use `./dev`, which has the same commands.

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

| Task | Shortcut | Full command |
|---|---|---|
| Start / stop | `.\dev up` / `.\dev down` | `docker compose up -d` / `docker compose down` |
| Are dependencies installed? | `.\dev ready` | — |
| Status | `.\dev ps` | `docker compose ps` |
| Logs | `.\dev logs` (or `.\dev logs app`) | `docker compose logs -f …` |
| Artisan | `.\dev artisan route:list --path=api` | `docker compose exec app php artisan …` |
| Reset the database with demo data | `.\dev fresh` | `… php artisan migrate:fresh --seed` |
| Back-end tests | `.\dev test` (or `.\dev test --filter=Workshops`) | `… php artisan test` |
| Code style (PHP) | `.\dev pint` | `… vendor/bin/pint` |
| Type-check the SPA | `.\dev tsc` | `docker compose exec vite npx vue-tsc --noEmit` |
| Check UI translations | `.\dev i18n` | `… npm run i18n:check` |
| Everything before a pull request | `.\dev check` | Pint on changed files, tests, type-check, translations |
| Composer / npm | `.\dev composer require …` / `.\dev npm install …` | |
| Shell in the PHP container | `.\dev shell` | `docker compose exec app bash` |
| PostgreSQL console | `.\dev psql` | `docker compose exec db psql -U postgres craft_woman` |
| Copy `vendor` for PhpStorm | `.\dev ide` | `docker compose cp app:/var/www/html/vendor .` |

## PhpStorm

- **PHP interpreter:** Settings → PHP → CLI Interpreter → **…** → **+** → **From Docker, Vagrant, VM…** → **Docker Compose**, service `app`. Then tests run from the IDE as well.
- **Database:** Database tool window → **+** → PostgreSQL. Use host `127.0.0.1`, port `5433`, user `postgres`, password `secret`, database `craft_woman`.
- **Autocomplete:** the containers keep `vendor` and `node_modules` in Docker volumes. The IDE reads the copies in the project folder. After `composer.lock` changes, run `.\dev ide` to refresh `vendor` for the IDE.

## Git workflow

- `main` holds what has been reviewed. Each feature is a branch (`feature/…`) with a pull request in English.
- Features are stacked: each branch starts from the previous one. Merge pull requests in order, oldest first.
- After switching to a branch with new dependencies or migrations, run `.\dev composer install`, `.\dev restart vite` and `.\dev fresh`. Migrations are still edited in place before the first release, so `migrate` alone may not be enough.

## Troubleshooting

| Symptom | Fix |
|---|---|
| `The token '&&' is not a valid statement separator` | PowerShell 5 — run commands one per line, or install PowerShell 7 (`winget install Microsoft.PowerShell`) |
| `.\dev` is not recognized | Run it from the project root, with `.\` in front |
| `error during connect` / `The system cannot find the file specified` | Docker Desktop isn't running — start it and wait for "Engine running" |
| Site at :8080 doesn't open right after `up` | Dependencies are still installing — `.\dev ready` |
| Pages are slow (seconds) | Expected with the project on a Windows drive — see [docker.md → Speed](docker.md#speed) |
| "No upcoming workshops" | Seeded dates have passed — `.\dev fresh` |

Docker-specific problems (ports, Vite, CSRF) are covered in [docker.md](docker.md#troubleshooting).
