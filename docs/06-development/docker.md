# Docker setup

How the containers are put together. To get started, see [getting-started.md](getting-started.md).

## Installing Docker Desktop on another drive

The installer you double-click always installs on drive C:. To put the program and, more importantly, the images and data on another drive, run the installer from PowerShell **as administrator**, from the folder where it was downloaded:

```powershell
Start-Process -Wait ".\Docker Desktop Installer.exe" -ArgumentList 'install', '--accept-license', '--installation-dir=D:\Docker\Desktop', '--wsl-default-data-root=D:\Docker\wsl'
```

In the window that opens, choose **All-users installation** and **WSL 2**. Leave Windows Containers unchecked. Afterwards check Settings → Resources → Advanced → **Disk image location**: it should show `D:\Docker\wsl`. A few hundred MB of settings and WSL itself stay on C:.

## Services (`compose.yaml`)

| Service | Image | Port on the host | Purpose |
|---|---|---|---|
| `web` | nginx 1.27 | **8080** | serves `public/`, sends PHP to `app`, `/storage` straight from `storage/app/public` |
| `app` | `docker/php/Dockerfile`: PHP 8.3-FPM + pdo_pgsql, intl, gd, zip, bcmath, opcache, Composer | — | Laravel |
| `db` | postgres 17 (builtin `C.UTF-8` locale, so `ILIKE` folds Cyrillic) | **5433** | databases `craft_woman` and `craft_woman_test` (`docker/postgres/init.sql`) |
| `vite` | node 22 | **5173** | `npm run dev`; polls for changes, because file events from Windows don't reach containers |

| Volume | Holds | Removed by |
|---|---|---|
| `craft-woman_pgdata` | the database | `docker compose down -v` |
| `craft-woman_vendor` | Linux `vendor/` | same |
| `craft-woman_node_modules` | Linux `node_modules/` (esbuild and rollup have native binaries) | same |

The project folder is mounted into `app`, `web` and `vite`, so code changes apply immediately. Uploaded files go to `storage/app` in the project folder.

`compose.yaml` overrides the database settings, `APP_URL` and Sanctum domains from `.env`, so `.env` keeps working for a native (Herd) setup as well.

On first start, `app` runs `composer install` when `vendor/autoload.php` is missing. `vite` runs `npm install` when `package-lock.json` has changed since its last install (it tracks this in `node_modules/.lock.sha1`).

## Notes

- **Speed.** See [Speed](#speed) below.
- **Limits.** PHP accepts uploads up to 110 MB and nginx up to 120 MB (`docker/php/php.ini`, `docker/nginx/default.conf`). The app allows 100 MB for course files and 50 MB for product files.
- **Rebuild** the PHP image after changing `docker/php/*`: `docker compose up -d --build app`.
- **Secrets.** `secret` is a local development password. This setup is for development only, not for a server.

## Speed

On Windows the project folder is mounted into Linux containers through a slow bridge (9p): every file PHP checks costs a round trip. A Laravel request touches thousands of files, so pages can take one to several seconds.

### Already applied

| Setting | Effect |
|---|---|
| `vendor`, `node_modules` in Docker volumes | Dependencies never cross the bridge |
| `composer dump-autoload --optimize` on start | Classes come from a class map instead of file lookups |
| `realpath_cache_size = 4096K`, `realpath_cache_ttl = 600` | PHP remembers resolved paths |
| OPcache with `revalidate_freq = 1` | Compiled code is reused; changes show up within a second |
| Vite polling every 500 ms | Less CPU spent watching files |

After changing `docker/php/*`, rebuild the image with `.\dev up --build`.

### The real fix: keep the project inside WSL

Inside the WSL file system the containers read files natively, which is usually 5–10× faster. Then it's as fast as Herd.

1. Install Ubuntu in WSL from an administrator PowerShell, then restart Windows if asked:
   ```powershell
   wsl --install -d Ubuntu
   ```
   On first start, Ubuntu asks for a Linux user name and password.
2. *(Optional, to keep it off drive C:)* Move the distribution:
   ```powershell
   wsl --shutdown
   wsl --manage Ubuntu --move D:\WSL\Ubuntu
   ```
3. In Docker Desktop, go to Settings → Resources → **WSL integration** → turn on **Ubuntu** → Apply & restart.
4. In Ubuntu (Start → Ubuntu), copy the project without the Windows `vendor` and `node_modules`. Your `.env` and any uncommitted work come along:
   ```bash
   sudo apt-get update && sudo apt-get install -y rsync
   rsync -a --exclude vendor --exclude node_modules /mnt/c/Users/Admin/Herd/craft-woman/ ~/craft-woman/
   cd ~/craft-woman
   docker compose down          # stop the containers that use the Windows copy
   ./dev up
   ./dev fresh
   ```
   The volumes (`vendor`, `node_modules`, the database) are reused because the compose project name stays `craft-woman`.
5. In PhpStorm, use File → Open → `\\wsl$\Ubuntu\home\<user>\craft-woman`. Run commands in the PhpStorm terminal (it opens Ubuntu for WSL projects) with `./dev …` — `dev.cmd` is for Windows paths only.
6. Git works the same inside Ubuntu. The first time, set your name and e-mail (`git config --global user.name …`) and sign in to GitHub (`gh auth login` or a personal access token).

Keep only one copy of the project in use, so changes don't end up in two places.

## Troubleshooting

| Symptom | Fix |
|---|---|
| `port is already allocated` (5173) | Another Vite is running, for example `npm run dev` on Windows — stop it |
| `port is already allocated` (8080 or 5433) | Another program uses the port. Change the left number in `compose.yaml` (`"8081:80"`); for 8080 also change `APP_URL` and `SANCTUM_STATEFUL_DOMAINS` there |
| 419 / CSRF after login | Open the site as `http://localhost:8080`, not `127.0.0.1:8080` |
| Page without styles, console: `127.0.0.1:5173` refused | `vite` is still installing or crashed — `.\dev logs vite` |
| `could not translate host name "db"` | `php artisan` ran on Windows instead of the container — use `.\dev artisan …` |
| `.vue` changes don't reload | Wait a second (polling every 300 ms); otherwise `.\dev restart vite` |
| Database looks empty after `down -v` | `-v` deletes volumes — `.\dev fresh` |
