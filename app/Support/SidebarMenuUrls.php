<?php

namespace App\Support;

use App\Models\SidebarMenuName;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class SidebarMenuUrls
{
    /** @var array<string, string>|null Original path => renamed path. */
    private ?array $aliases = null;

    public function flush(): void
    {
        $this->aliases = null;
    }

    public function aliases(): array
    {
        if ($this->aliases !== null) {
            return $this->aliases;
        }
        if (! Schema::hasTable('sidebar_menu_names')) {
            return $this->aliases = [];
        }
        $names = SidebarMenuName::query()->where('target_key', 'like', 'item:%')
            ->whereNotNull('label_en')->orderBy('target_key')->get();
        if ($names->isEmpty()) {
            return $this->aliases = [];
        }

        $catalog = app(SidebarNavigation::class)->catalog();
        $reserved = [];
        foreach (Route::getRoutes() as $route) {
            $segment = explode('/', trim($route->uri(), '/'))[0];
            if ($segment !== '' && ! str_contains($segment, '{')) {
                $reserved[$segment] = true;
            }
        }
        $basePath = rtrim(parse_url(url('/'), PHP_URL_PATH) ?? '', '/');
        $aliases = [];
        foreach ($names as $name) {
            $target = $catalog[$name->target_key] ?? null;
            if (($target['kind'] ?? null) !== 'item' || blank($target['url'] ?? null)) {
                continue;
            }
            $slug = Str::slug($name->label_en);
            if ($slug === '' || $slug === Str::slug($target['label_en'] ?? '')) {
                continue;
            }
            $path = rtrim(parse_url($target['url'], PHP_URL_PATH) ?? '', '/');
            if ($path === '' || $path === $basePath || $path === $basePath.'/'.$slug || isset($aliases[$path])) {
                continue;
            }

            // Duplicate names and existing routes must never take over another menu's URL.
            $candidate = $slug;
            if (isset($reserved[$candidate])) {
                $candidate .= '-'.substr(hash('sha256', $name->target_key), 0, 8);
            }
            while (isset($reserved[$candidate])) {
                $candidate .= '-menu';
            }
            $reserved[$candidate] = true;
            $aliases[$path] = $basePath.'/'.$candidate;
        }

        // Match a child path against its most specific menu first.
        uksort($aliases, fn (string $first, string $second): int => strlen($second) <=> strlen($first));

        return $this->aliases = $aliases;
    }

    public function originalPath(string $path): ?string
    {
        foreach ($this->aliases() as $original => $alias) {
            if ($path === $alias || str_starts_with($path, $alias.'/')) {
                return $original.substr($path, strlen($alias));
            }
        }

        return null;
    }

    public function renamedPath(string $path): ?string
    {
        foreach ($this->aliases() as $original => $alias) {
            if ($path === $original || str_starts_with($path, $original.'/')) {
                return $alias.substr($path, strlen($original));
            }
        }

        return null;
    }

    public function forUrl(string $url): string
    {
        $host = parse_url($url, PHP_URL_HOST);
        if ($host && ! in_array($host, [request()->getHost(), parse_url(config('app.url'), PHP_URL_HOST)], true)) {
            return $url;
        }
        $path = parse_url($url, PHP_URL_PATH) ?? '';
        $renamed = $this->renamedPath($path);

        return $renamed === null ? $url : preg_replace('~'.preg_quote($path, '~').'(?=[?#]|$)~', $renamed, $url, 1);
    }
}
