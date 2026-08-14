<?php

use App\Http\Controllers\Admin\AmenityController;
use App\Http\Controllers\Admin\AnalyticsController;
use App\Http\Controllers\Admin\BookingController;
use App\Http\Controllers\Admin\HotelController;
use App\Http\Controllers\Admin\PaymentController;
use App\Http\Controllers\Admin\ReviewController;
use App\Http\Controllers\Admin\RoomController;
use App\Http\Controllers\Admin\RoomImageController;
use App\Http\Controllers\Admin\RoomTypeController;
use App\Http\Controllers\Admin\ServiceController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\VoucherController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum'])->group(function () {
    Route::middleware('role:super_admin,hotel_manager')->group(function () {
        Route::apiResource('hotels', HotelController::class);
        Route::apiResource('room-types', RoomTypeController::class)->parameters(['room-types' => 'room_type']);
        Route::apiResource('rooms', RoomController::class);
        Route::apiResource('amenities', AmenityController::class)->except('show');
        Route::post('room-types/{room_type}/images', [RoomImageController::class, 'store']);
        Route::patch('room-images/{room_image}', [RoomImageController::class, 'update']);
        Route::delete('room-images/{room_image}', [RoomImageController::class, 'destroy']);
        Route::apiResource('services', ServiceController::class);
        Route::apiResource('vouchers', VoucherController::class);
    });

    Route::middleware('role:super_admin,hotel_manager,receptionist')->group(function () {
        Route::get('bookings', [BookingController::class, 'index']);
        Route::post('bookings', [BookingController::class, 'store']);
        Route::post('bookings/counter', [BookingController::class, 'store']);
        Route::get('bookings/{booking}', [BookingController::class, 'show']);
        Route::patch('bookings/{booking}/status', [BookingController::class, 'updateStatus']);
        Route::post('bookings/{booking}/check-in', [BookingController::class, 'checkIn']);
        Route::post('bookings/{booking}/check-out', [BookingController::class, 'checkOut']);
        Route::get('bookings/{booking}/invoice', [BookingController::class, 'invoice']);
        Route::get('room-map', [RoomController::class, 'map']);
        Route::post('rooms/{room}/cleaning-complete', [RoomController::class, 'cleaningComplete']);
    });

    Route::middleware('role:super_admin,hotel_manager')->group(function () {
        Route::get('users', [UserController::class, 'index']);
        Route::post('users', [UserController::class, 'store']);
        Route::patch('users/{user}', [UserController::class, 'update']);
        Route::patch('users/{user}/status', [UserController::class, 'updateStatus']);
    });

    Route::middleware('role:super_admin,hotel_manager,accountant')->prefix('analytics')->group(function () {
        Route::get('dashboard', [AnalyticsController::class, 'dashboard']);
        Route::get('revenue', [AnalyticsController::class, 'revenue']);
        Route::get('occupancy', [AnalyticsController::class, 'occupancy']);
        Route::get('loyalty', [AnalyticsController::class, 'loyalty']);
        Route::get('satisfaction', [AnalyticsController::class, 'satisfaction']);
    });

    Route::middleware('role:super_admin,hotel_manager,accountant')->group(function () {
        Route::get('dashboard', [AnalyticsController::class, 'dashboard']);
        Route::get('analytics', [AnalyticsController::class, 'overview']);
        Route::get('payments', [PaymentController::class, 'index']);
        Route::post('bookings/{booking}/payments', [PaymentController::class, 'store']);
    });

    Route::middleware('role:super_admin,hotel_manager')->group(function () {
        Route::get('reviews', [ReviewController::class, 'index']);
        Route::put('reviews/{review}', [ReviewController::class, 'update']);
        Route::patch('reviews/{review}', [ReviewController::class, 'update']);
        Route::patch('reviews/{review}/status', [ReviewController::class, 'update']);
    });
});
