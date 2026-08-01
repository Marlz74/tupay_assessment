<?php

use App\Http\Controllers\Api\SwapController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {

    Route::post('/swap', [SwapController::class, 'store'])
        ->middleware(['eat:swap', 'throttle:30,1']);
});
