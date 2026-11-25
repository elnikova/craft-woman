<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** PRD §16: Ukrainian and English. The SPA sends Accept-Language; default is the app locale. */
class SetLocaleFromHeader
{
    public function handle(Request $request, Closure $next): Response
    {
        $preferred = $request->getPreferredLanguage(\App\Support\Locales::SUPPORTED);
        if ($preferred && $request->headers->has('Accept-Language')) {
            app()->setLocale($preferred);
        }

        return $next($request);
    }
}
