<?php

use App\Http\Controllers\Auth\PasswordResetController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect('/student/login');
});

Route::get('/language/toggle', function () {
    $currentLocale = session('locale', app()->getLocale());

    $nextLocale = $currentLocale === 'km' ? 'en' : 'km';

    session(['locale' => $nextLocale]);

    app()->setLocale($nextLocale);

    return back();
})->name('language.toggle');

Route::middleware('guest')
    ->prefix('student')
    ->name('student.')
    ->group(function () {
        Route::get('/forgot-password', [PasswordResetController::class, 'showForgotPasswordForm'])
            ->name('password.request');

        Route::post('/forgot-password', [PasswordResetController::class, 'sendResetLink'])
            ->name('password.email');

        Route::get('/reset-password/{token}', [PasswordResetController::class, 'showResetPasswordForm'])
            ->name('password.reset');

        Route::post('/reset-password', [PasswordResetController::class, 'resetPassword'])
            ->name('password.update');
    });
