<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\Response;

class SaveUserLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $user = $request->user();
        $locale = app()->getLocale();

        if (
            $user
            && in_array($locale, ['en', 'km'], true)
            && Schema::hasTable('users')
            && Schema::hasColumn('users', 'locale')
            && $user->locale !== $locale
        ) {
            $user->forceFill([
                'locale' => $locale,
            ])->saveQuietly();
        }

        return $response;
    }
}
