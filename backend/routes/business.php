<?php

use App\Http\Controllers\Api\V1\BusinessController;
use Illuminate\Support\Facades\Route;

Route::post('/quotes', [BusinessController::class, 'quote']);
Route::get('/hotels/{hotel:slug}/services', [BusinessController::class, 'services']);
Route::get('/vouchers', [BusinessController::class, 'vouchers']);
Route::post('/vouchers/validate', [BusinessController::class, 'validateVoucher']);
Route::post('/bookings/{booking:code}/payments/mock/intents', [BusinessController::class, 'createPaymentIntent'])->middleware('throttle:30,1');
Route::post('/payments/mock/{payment:reference}/confirm', [BusinessController::class, 'confirmPayment'])->middleware('throttle:30,1');
Route::get('/bookings/{booking:code}/payments', [BusinessController::class, 'bookingPayments']);
Route::get('/bookings/{booking:code}/invoice', [BusinessController::class, 'bookingInvoice']);
// Temporary aliases for the current booking detail client.
Route::post('/booking/{booking:code}/payments/mock/intents', [BusinessController::class, 'createPaymentIntent'])->middleware('throttle:30,1');
Route::get('/booking/{booking:code}/payments', [BusinessController::class, 'bookingPayments']);
Route::get('/booking/{booking:code}/invoice', [BusinessController::class, 'bookingInvoice']);
Route::get('/hotels/{hotel:slug}/reviews', [BusinessController::class, 'reviews']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/wishlist', [BusinessController::class, 'wishlistIndex']);
    Route::post('/wishlist', [BusinessController::class, 'wishlistStore']);
    Route::delete('/wishlist/{roomType}', [BusinessController::class, 'wishlistDestroy']);
    Route::get('/me/wishlist', [BusinessController::class, 'wishlistIndex']);
    Route::post('/me/wishlist', [BusinessController::class, 'wishlistStore']);
    Route::delete('/me/wishlist/{roomType}', [BusinessController::class, 'wishlistDestroy']);
    Route::post('/reviews', [BusinessController::class, 'createReview']);
    Route::get('/me/bookings', [BusinessController::class, 'myBookings']);
    Route::get('/me/bookings/{booking:code}', [BusinessController::class, 'myBooking']);
});
