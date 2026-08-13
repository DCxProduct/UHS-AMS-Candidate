<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $locale = session('locale')
            ?? $request->query('locale')
            ?? $request->input('locale')
            ?? $request->cookie('filament_language_switch_locale')
            ?? config('app.locale', 'km');

        if (! in_array($locale, ['en', 'km'], true)) {
            $locale = 'km';
        }

        session()->put('locale', $locale);
        App::setLocale($locale);

        return $next($request);
    }
}
