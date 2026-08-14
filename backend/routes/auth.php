<?php

use App\Http\Controllers\Api\V1\AuthController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->controller(AuthController::class)->group(function () {
    Route::post('register', 'register');
    Route::post('login', 'login');
    Route::post('forgot-password', 'forgotPassword')->middleware('throttle:5,1');
    Route::post('reset-password', 'resetPassword')->middleware('throttle:5,1');

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('logout', 'logout');
        Route::get('me', 'me');
    });
});

Route::middleware('auth:sanctum')->get('me', [AuthController::class, 'me']);
