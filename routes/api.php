<?php

use App\Http\Controllers\Api\AmsOtherFeePaymentController;
use Illuminate\Support\Facades\Route;

Route::post('/external/ams/other-fee-payments', AmsOtherFeePaymentController::class)
    ->middleware('throttle:60,1')->name('external.ams.other-fee-payments');
