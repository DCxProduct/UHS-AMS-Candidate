<?php

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
