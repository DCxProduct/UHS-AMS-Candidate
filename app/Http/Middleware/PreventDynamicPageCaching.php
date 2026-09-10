<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PreventDynamicPageCaching
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $isAuthPage = $request->is('login') || $request->is('register');
        $isLivewireRequest = $request->hasHeader('X-Livewire');

        if ($isAuthPage || $isLivewireRequest) {
            $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0, private');
            $response->headers->set('Pragma', 'no-cache');
            $response->headers->set('Expires', '0');
        }

        return $response;
    }
}
