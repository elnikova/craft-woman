# Running in Docker

Everything in containers: PHP-FPM, nginx, PostgreSQL and the Vite dev server. It runs side by side with Herd.

| | Herd | Docker |
|---|---|---|
| Site | http://craft-woman.test | http://localhost:8080 |
| PostgreSQL | Windows service, port 5432 | container `db`, port **5433** on the host |
| Database | `craft_woman` on the Windows server | `craft_woman` inside the container (separate data) |
| `vendor/`, `node_modules/` | the project folder | Docker volumes, Linux builds |
| Vite | `npm run dev` on Windows | container `vite`, http://localhost:5173 |

Run either Herd's `npm run dev` or the Docker `vite` container, not both: they share port 5173.

## Services (`compose.yaml`)

| Service | Image | Purpose |
|---|---|---|
| `app` | `docker/php/Dockerfile` (PHP 8.3-FPM + pdo_pgsql, intl, gd, zip, Composer) | Laravel |
| `web` | nginx 1.27 | serves `public/`, sends PHP to `app`, `/storage` straight from `storage/app/public` |
| `db` | postgres 17 (builtin `C.UTF-8` locale) | databases `craft_woman` and `craft_woman_test` |
| `vite` | node 22 | `npm run dev` with polling, because file events from Windows don't reach containers |

The containers get their own database settings and `APP_URL` from `compose.yaml`. They override `.env`, so one `.env` serves both Herd and Docker.

## First start

```powershell
docker compose up -d --build
docker compose exec app php artisan migrate:fresh --seed
```

The first start takes a few minutes: it builds the PHP image, runs `composer install` in `app` and `npm install` in `vite`. Follow the progress with `docker compose logs -f app vite`. When `vite` prints `ready`, open http://localhost:8080.

## Everyday commands

| Task | Command |
|---|---|
| Start / stop | `docker compose up -d` / `docker compose down` |
| Status | `docker compose ps` |
| Logs | `docker compose logs -f app` (or `web`, `vite`, `db`) |
| Artisan | `docker compose exec app php artisan …` |
| Tests | `docker compose exec app php artisan test` |
| Composer after `composer.lock` changed | `docker compose exec app composer install` |
| npm after `package-lock.json` changed | `docker compose restart vite` (it reinstalls when the lock file changed) |
| psql | `docker compose exec db psql -U postgres craft_woman` |
| Connect from pgAdmin / PhpStorm | host `127.0.0.1`, port `5433`, user `postgres`, password `secret` |
| Rebuild the PHP image (Dockerfile changed) | `docker compose up -d --build app` |
| Start from scratch (**deletes the Docker database**) | `docker compose down -v` |

## Notes

- **Speed.** The project folder lives on the Windows drive and is mounted into Linux containers, which is slower than native Herd. Expect page loads in hundreds of milliseconds. Keeping `vendor` and `node_modules` in volumes helps a lot. For full speed, keep the project inside the WSL file system (`\\wsl$\…`).
- **Uploads** go to `storage/app` in the project folder, the same place Herd uses. Herd and Docker see the same files but different databases.
- **Limits.** PHP accepts uploads up to 110 MB and nginx up to 120 MB (`docker/php/php.ini`, `docker/nginx/default.conf`).
- **Secrets.** `secret` is a local development password. Don't reuse this setup for a server.

## Troubleshooting

| Symptom | Fix |
|---|---|
| `port is already allocated` for 5173 | Herd's `npm run dev` is running — stop it |
| `port is already allocated` for 8080 or 5433 | Another program uses the port. Change the left number in `compose.yaml` (`"8081:80"`), and for 8080 also `APP_URL` and `SANCTUM_STATEFUL_DOMAINS` |
| 419 / CSRF after login | Open the site as `http://localhost:8080`, not `127.0.0.1:8080`, or add it to `SANCTUM_STATEFUL_DOMAINS` |
| Page without styles, console: `127.0.0.1:5173` refused | `vite` is still installing packages or crashed — `docker compose logs vite` |
| `SQLSTATE … could not translate host name "db"` | `php artisan` was run on Windows instead of in the container — prefix with `docker compose exec app` |
| Changes in `.vue` files don't reload | Wait about a second (polling every 300 ms); if nothing happens, `docker compose restart vite` |
