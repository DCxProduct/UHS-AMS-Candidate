<?php

use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\DbSyncController;
use BezhanSalleh\LanguageSwitch\Events\LocaleChanged;
use BezhanSalleh\LanguageSwitch\LanguageSwitch;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AdminEntryPdfReviewController;

Route::get('/', function () {
    return redirect('/login');
});

Route::get('/language/toggle', function () {
    $currentLocale = LanguageSwitch::make()->getPreferredLocale();

    $nextLocale = $currentLocale === 'km' ? 'en' : 'km';

    session()->put('locale', $nextLocale);
    app()->setLocale($nextLocale);
    cookie()->queue(cookie()->forever('filament_language_switch_locale', $nextLocale));

    event(new LocaleChanged($nextLocale));

    return redirect()->to(url()->previous('/login'));
})->name('language.toggle');

Route::get('/language/{locale}', function (string $locale) {
    if (in_array($locale, ['en', 'km'], true)) {
        session()->put('locale', $locale);
        app()->setLocale($locale);
        cookie()->queue(cookie()->forever('filament_language_switch_locale', $locale));

        event(new LocaleChanged($locale));
    }

    return redirect()->to(url()->previous('/login'));
})->name('language.set');




Route::middleware(['web', 'auth'])->group(function () {
    Route::get('/admin/custom-form-entries/{entry}/pdf-review', [AdminEntryPdfReviewController::class, 'show'])
        ->name('admin.custom-form-entries.pdf-review');

    Route::get('/admin/custom-form-entries/{entry}/pdf-inline', [AdminEntryPdfReviewController::class, 'pdf'])
        ->name('admin.custom-form-entries.pdf-inline');

    Route::post('/admin/custom-form-entries/{entry}/approve', [AdminEntryPdfReviewController::class, 'approve'])
        ->name('admin.custom-form-entries.approve');

    Route::post('/admin/custom-form-entries/{entry}/reject', [AdminEntryPdfReviewController::class, 'reject'])
        ->name('admin.custom-form-entries.reject');
});
Route::post('/sync/run', [DbSyncController::class, 'sync'])
    ->name('sync.run')
    ->middleware('auth');

/*
|--------------------------------------------------------------------------
| Password Reset
|--------------------------------------------------------------------------
| Keep name student.* because your AppServiceProvider / old code may use:
| route('student.password.reset')
|--------------------------------------------------------------------------
*/
Route::middleware('guest')
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
