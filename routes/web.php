<?php

use App\Http\Controllers\Auth\PasswordResetController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect('/admin/login');
});

Route::get('/language/toggle', function () {
    $currentLocale = session('locale', app()->getLocale());

    $nextLocale = $currentLocale === 'km' ? 'en' : 'km';

    session(['locale' => $nextLocale]);

    app()->setLocale($nextLocale);

    return back();
})->name('language.toggle');

Route::middleware('guest')->group(function () {
    Route::get('/admin/forgot-password', [PasswordResetController::class, 'showForgotPasswordForm'])
        ->name('admin.password.request');

    Route::post('/admin/forgot-password', [PasswordResetController::class, 'sendResetLink'])
        ->name('admin.password.email');

    Route::get('/admin/reset-password/{token}', [PasswordResetController::class, 'showResetPasswordForm'])
        ->name('admin.password.reset');

    Route::post('/admin/reset-password', [PasswordResetController::class, 'resetPassword'])
        ->name('admin.password.update');
});
