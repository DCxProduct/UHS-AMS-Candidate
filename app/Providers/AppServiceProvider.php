<?php

namespace App\Providers;

use App\Models\User;
use BezhanSalleh\LanguageSwitch\LanguageSwitch;
use Illuminate\Support\ServiceProvider;
use Illuminate\Auth\Notifications\ResetPassword;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
    }

    public function boot(): void
    {
        ResetPassword::createUrlUsing(function (User $user, string $token): string {
            return route('admin.password.reset', [
                'token' => $token,
                'email' => $user->getEmailForPasswordReset(),
            ]);
        });

        LanguageSwitch::configureUsing(function (LanguageSwitch $switch) {
            $switch
                ->locales([
                    'en',
                    'km',
                ])
                ->labels([
                    'en' => 'English',
                    'km' => 'ខ្មែរ',
                ])
                ->flags([
                    'en' => 'https://flagcdn.com/w40/gb.png',
                    'km' => 'https://flagcdn.com/w40/kh.png',
                ])
                ->flagsOnly(false)
                ->circular()
                ->visible(
                    insidePanels: true,
                    outsidePanels: true,
                );
        });
    }
}
