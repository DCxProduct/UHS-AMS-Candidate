<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        // Same-origin PDF review stays functional. Script/style restrictions need a
        // separate deployment asset inventory before they can safely be enforced.
        $response->headers->set('Content-Security-Policy', "base-uri 'self'; object-src 'self'; frame-ancestors 'self'");

        return $response;
    }
}
