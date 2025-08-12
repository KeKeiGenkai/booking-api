<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\BookingController;

Route::middleware('auth.api_token')->group(function () {
    Route::get('/bookings', [BookingController::class, 'index']);
    Route::post('/bookings', [BookingController::class, 'store']);
    Route::patch('/bookings/{booking}/slots/{slot}', [BookingController::class, 'updateSlot']);
    Route::post('/bookings/{booking}/slots', [BookingController::class, 'addSlot']);
    Route::delete('/bookings/{booking}', [BookingController::class, 'destroy']);
});
