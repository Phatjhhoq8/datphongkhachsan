<?php

use App\Http\Controllers\Api\V1\ActivityEventController;
use App\Http\Controllers\Api\V1\BookingController;
use App\Http\Controllers\Api\V1\ChatController;
use App\Http\Controllers\Api\V1\HotelController;
use App\Http\Controllers\Api\V1\SearchController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::post('/activity-events', ActivityEventController::class)->middleware('throttle:120,1');
    Route::get('/destinations', [HotelController::class, 'destinations']);
    Route::get('/hotels', [HotelController::class, 'index']);
    Route::get('/hotels/{hotel:slug}', [HotelController::class, 'show']);
    Route::get('/search', SearchController::class);

    Route::post('/bookings', [BookingController::class, 'store']);
    Route::get('/bookings/{booking:code}', [BookingController::class, 'show'])->middleware('throttle:30,1');
    Route::post('/bookings/{booking:code}/cancel', [BookingController::class, 'cancel'])->middleware('throttle:30,1');

    Route::prefix('chat')->middleware('throttle:60,1')->group(function () {
        Route::post('conversations', [ChatController::class, 'storeConversation'])->middleware('throttle:10,1');
        Route::get('conversations/{conversation}/messages', [ChatController::class, 'messages']);
        Route::post('conversations/{conversation}/messages', [ChatController::class, 'send'])->middleware('throttle:30,1');
        Route::post('socket-auth', [ChatController::class, 'socketAuth'])->middleware('throttle:120,1');
    });
});
