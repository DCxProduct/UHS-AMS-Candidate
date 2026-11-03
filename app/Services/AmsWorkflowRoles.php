<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

/** Role names belong to AMS when that system collects the payment. */
final class AmsWorkflowRoles
{
    public static function options(): array
    {
        $baseUrl = rtrim((string) config('services.ams.base_url'), '/');
        $token = (string) config('services.ams.token');
        if (! config('services.ams.enabled') || $baseUrl === '' || $token === '') {
            return [];
        }

        $locale = app()->getLocale();
        $key = 'ams.workflow.roles.'.hash('sha256', $baseUrl.'|'.$token.'|'.$locale);
        $cached = Cache::get($key);
        if (is_array($cached)) {
            return $cached;
        }

        try {
            $data = Http::acceptJson()->withToken($token)->connectTimeout(2)
                ->timeout((int) config('services.ams.timeout', 5))
                ->get($baseUrl.'/api/external/candidate/roles', ['locale' => $locale])
                ->throw()->json('data');
            if (! is_array($data)) {
                Cache::put($key, [], now()->addSeconds(5));
                return [];
            }

            $options = [];
            foreach ($data as $role) {
                if (! is_array($role) || ! is_string($role['name'] ?? null) || trim($role['name']) === '') {
                    continue;
                }
                $options[$role['name']] = is_string($role['label'] ?? null) && filled($role['label'])
                    ? $role['label'] : $role['name'];
            }
            Cache::put($key, $options, now()->addMinute());

            return $options;
        } catch (Throwable) {
            // Never substitute Candidate roles when AMS is unavailable.
            Cache::put($key, [], now()->addSeconds(5));
            return [];
        }
    }
}
