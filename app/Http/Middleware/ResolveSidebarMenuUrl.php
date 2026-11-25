<?php

namespace App\Http\Middleware;

use App\Support\SidebarMenuUrls;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveSidebarMenuUrl
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->is('api', 'api/*', 'livewire/*', 'storage/*', 'build/*', 'vendor/*', 'protected/*', 'language/*', 'up')) {
            return $next($request);
        }

        $urls = app(SidebarMenuUrls::class);
        $path = $request->getBaseUrl().$request->getPathInfo();
        $original = $urls->originalPath($path);
        if ($original !== null) {
            // Resolve the renamed address through the original route and all its middleware.
            // Keep the same request, method, body, uploads, query and authentication state.
            $server = $request->server->all();
            $server['REQUEST_URI'] = $original.($request->getQueryString() ? '?'.$request->getQueryString() : '');
            $server['PATH_INFO'] = $original;
            $request->initialize($request->query->all(), $request->request->all(), $request->attributes->all(),
                $request->cookies->all(), $request->files->all(), $server, $request->getContent());
        } elseif (in_array($request->method(), ['GET', 'HEAD'], true) && $urls->renamedPath($path) !== null) {
            return redirect()->to($urls->forUrl($request->getRequestUri()));
        }

        $response = $next($request);
        if ($response->isRedirection() && $response->headers->has('Location')) {
            $response->headers->set('Location', $urls->forUrl($response->headers->get('Location')));
        }

        return $response;
    }
}
