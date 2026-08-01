<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DevTotpController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login'])
    ->middleware('throttle:5,1');

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/2fa/challenge', [AuthController::class, 'twoFactorChallenge'])
        ->middleware('throttle:10,1');

    Route::get('/dev/totp/current', [DevTotpController::class, 'current'])
        ->middleware('throttle:30,1');
});
