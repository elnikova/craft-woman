<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        apiPrefix: 'api/v1',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // The same-domain SPA authenticates with the Sanctum session cookie.
        // Bearer tokens still work for mobile clients.
        $middleware->statefulApi();
        $middleware->appendToGroup('api', \App\Http\Middleware\SetLocaleFromHeader::class);
        $middleware->appendToGroup('api', \App\Http\Middleware\EnsureAccountIsActive::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Domain rule violations (disallowed status transition, etc.) → 409 Conflict
        $exceptions->render(function (DomainException $e, Request $request) {
            if ($request->expectsJson()) {
                // Domain exceptions carry a translation key (lang/*/domain.php); otherwise use the technical message
                // Parameter values (statuses, requirements) are translated too; lists are comma-separated
                $translate = fn (string $v) => trans()->has("domain.values.{$v}") ? __("domain.values.{$v}") : $v;
                $params = array_map(
                    fn ($v) => is_string($v) ? implode(', ', array_map($translate, explode(',', $v))) : $v,
                    property_exists($e, 'params') ? $e->params : [],
                );
                $message = property_exists($e, 'key') && trans()->has('domain.'.$e->key)
                    ? __('domain.'.$e->key, $params)
                    : $e->getMessage();

                return response()->json(['message' => $message], 409);
            }
        });
    })->create();
