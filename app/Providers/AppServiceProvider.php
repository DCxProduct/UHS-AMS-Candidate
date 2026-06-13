<?php

namespace App\Providers;

use App\Models\User;
use BezhanSalleh\LanguageSwitch\LanguageSwitch;
use Chanthoeun\FilamentCustomForms\Models\CustomForm;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
    }

    public function boot(): void
    {
        ResetPassword::createUrlUsing(function (User $user, string $token): string {
            return route('student.password.reset', [
                'token' => $token,
                'email' => $user->getEmailForPasswordReset(),
            ]);
        });

        CustomForm::creating(function (CustomForm $form): void {
            if (blank($form->allowed_roles)) {
                $form->allowed_roles = json_encode([
                    'student',
                    'admin',
                ], JSON_UNESCAPED_UNICODE);
            }
        });

        CustomForm::saving(function (CustomForm $form): void {
            if (blank($form->allowed_roles)) {
                $form->allowed_roles = json_encode([
                    'student',
                    'admin',
                ], JSON_UNESCAPED_UNICODE);
            }
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
