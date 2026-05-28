<?php

namespace App\Providers;

use BezhanSalleh\LanguageSwitch\LanguageSwitch;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
    }

    public function boot(): void
    {
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
                    outsidePanels: false,
                );
        });
    }
}