<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\OAuthController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->controller(AuthController::class)->group(function () {
    Route::post('register', 'register');
    Route::post('login', 'login');
    Route::post('forgot-password', 'forgotPassword')->middleware('throttle:5,1');
    Route::post('reset-password', 'resetPassword')->middleware('throttle:5,1');

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('logout', 'logout');
        Route::get('me', 'me');
        Route::patch('profile', 'updateProfile');
        Route::put('password', 'changePassword');
    });
});

Route::prefix('auth/oauth')->controller(OAuthController::class)->group(function () {
    Route::get('providers', 'providers');
    Route::get('{provider}/redirect', 'redirect');
    Route::get('{provider}/callback', 'callback');
    Route::post('exchange', 'exchange')->middleware('throttle:10,1');
});

Route::middleware('auth:sanctum')->get('me', [AuthController::class, 'me']);
