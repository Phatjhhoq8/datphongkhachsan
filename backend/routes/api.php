<?php

use App\Http\Controllers\Api\V1\BookingController;
use App\Http\Controllers\Api\V1\HotelController;
use App\Http\Controllers\Api\V1\SearchController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::get('/hotels', [HotelController::class, 'index']);
    Route::get('/hotels/{hotel:slug}', [HotelController::class, 'show']);
    Route::get('/search', SearchController::class);

    Route::post('/bookings', [BookingController::class, 'store']);
    Route::get('/bookings/{booking:code}', [BookingController::class, 'show']);
    Route::post('/bookings/{booking:code}/cancel', [BookingController::class, 'cancel']);
});
