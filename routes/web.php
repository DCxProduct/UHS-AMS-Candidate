<?php

use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\DbSyncController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AdminEntryPdfReviewController;

Route::get('/', function () {
    return redirect('/login');
});

Route::get('/language/toggle', function () {
    $currentLocale = session('locale', app()->getLocale());

    $nextLocale = $currentLocale === 'km' ? 'en' : 'km';

    session([
        'locale' => $nextLocale,
    ]);

    app()->setLocale($nextLocale);

    return back();
})->name('language.toggle');

Route::get('/language/{locale}', function (string $locale) {
    if (in_array($locale, ['en', 'km'], true)) {
        session([
            'locale' => $locale,
        ]);

        app()->setLocale($locale);
    }

    return back();
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
