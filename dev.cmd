@echo off
rem Shortcuts for the Docker setup (docs/06-development/getting-started.md).
rem Works in PowerShell (.\dev test) and cmd (dev test) without execution-policy changes.
setlocal
cd /d "%~dp0"

set "CMD=%~1"
if "%CMD%"=="" goto help

rem Everything after the first word is passed through
set "ARGS="
shift
:collect
if "%~1"=="" goto dispatch
set ARGS=%ARGS% %1
shift
goto collect

:dispatch
if /i "%CMD%"=="up" goto up
if /i "%CMD%"=="down" goto down
if /i "%CMD%"=="restart" goto restart
if /i "%CMD%"=="ps" goto ps
if /i "%CMD%"=="logs" goto logs
if /i "%CMD%"=="ready" goto ready
if /i "%CMD%"=="artisan" goto artisan
if /i "%CMD%"=="fresh" goto fresh
if /i "%CMD%"=="test" goto test
if /i "%CMD%"=="pint" goto pint
if /i "%CMD%"=="composer" goto composer
if /i "%CMD%"=="npm" goto npm
if /i "%CMD%"=="tsc" goto tsc
if /i "%CMD%"=="i18n" goto i18n
if /i "%CMD%"=="check" goto check
if /i "%CMD%"=="shell" goto shell
if /i "%CMD%"=="psql" goto psql
if /i "%CMD%"=="ide" goto ide
echo Unknown command: %CMD%
echo.
goto help

:up
docker compose up -d%ARGS%
goto end

:down
docker compose down%ARGS%
goto end

:restart
docker compose restart%ARGS%
goto end

:ps
docker compose ps
goto end

:logs
docker compose logs -f --tail=100%ARGS%
goto end

:ready
docker compose exec app sh -c "test -f vendor/autoload.php && echo 'composer: ready' || echo 'composer: still installing'"
docker compose exec vite sh -c "test -f node_modules/.lock.sha1 && echo 'npm: ready' || echo 'npm: still installing'"
goto end

:artisan
docker compose exec app php artisan%ARGS%
goto end

:fresh
docker compose exec app php artisan migrate:fresh --seed
goto end

:test
docker compose exec app php artisan test%ARGS%
goto end

:pint
docker compose exec app vendor/bin/pint%ARGS%
goto end

:composer
docker compose exec app composer%ARGS%
goto end

:npm
docker compose exec vite npm%ARGS%
goto end

:tsc
docker compose exec vite npx vue-tsc --noEmit
goto end

:i18n
docker compose exec vite npm run i18n:check
goto end

:check
echo == Pint (changed files) ==
docker compose exec app vendor/bin/pint --test --dirty || goto failed
echo == Tests ==
docker compose exec app php artisan test --compact || goto failed
echo == Type-check ==
docker compose exec vite npx vue-tsc --noEmit || goto failed
echo == Translations ==
docker compose exec vite npm run i18n:check || goto failed
echo.
echo All checks passed.
goto end

:shell
docker compose exec app bash
goto end

:psql
docker compose exec db psql -U postgres craft_woman
goto end

:ide
rem Copy the container's vendor next to the code so PhpStorm can index it
docker compose cp app:/var/www/html/vendor .
goto end

:failed
echo.
echo Check failed - see the output above.
exit /b 1

:help
echo Usage: .\dev ^<command^> [arguments]
echo.
echo   up              start all containers (first run installs dependencies)
echo   down            stop containers (data stays)
echo   restart [svc]   restart all or one service (app, web, db, vite)
echo   ps              container status
echo   logs [svc]      follow logs
echo   ready           are composer and npm dependencies installed?
echo   artisan ...     php artisan ...
echo   fresh           migrate:fresh --seed (demo data)
echo   test [...]      php artisan test, e.g. .\dev test --filter=Workshops
echo   pint [...]      code style
echo   composer ...    composer in the app container
echo   npm ...         npm in the vite container
echo   tsc             type-check the SPA
echo   i18n            check UI translations
echo   check           pint on changed files, tests, type-check, translations
echo   shell           bash in the app container
echo   psql            PostgreSQL console
echo   ide             copy vendor from the container for PhpStorm
goto end

:end
endlocal
