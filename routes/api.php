<?php

use App\Http\Controllers\Api\AmsOtherFeePaymentController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AmsStudentAccountController;

Route::post('/external/ams/new-student-accounts', AmsStudentAccountController::class)
    ->middleware('throttle:60,1');

Route::post('/external/ams/other-fee-payments', AmsOtherFeePaymentController::class)
    ->middleware('throttle:60,1')->name('external.ams.other-fee-payments');

Route::post('/external/ams/excel-student-accounts', \App\Http\Controllers\Api\AmsExcelStudentAccountController::class)
    ->middleware('throttle:60,1');
