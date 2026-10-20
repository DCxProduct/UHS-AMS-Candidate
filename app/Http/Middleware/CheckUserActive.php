<?php

namespace App\Http\Middleware;

use App\Support\NotificationLanguage;
use Closure;
use Filament\Notifications\Notification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CheckUserActive
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if ($user && ! (bool) $user->fresh()?->is_active) {
            Notification::make()
                ->title(NotificationLanguage::trans('auth.account_disabled'))
                ->danger()
                ->send();

            Auth::logout();

            $request->session()->invalidate();
            $request->session()->regenerateToken();

            if ($request->expectsJson()) {
                return response()->json(['message' => 'This account is inactive.'], 403);
            }

            return redirect('/login');
        }

        return $next($request);
    }
}
