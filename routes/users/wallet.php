<?php

use App\Http\Controllers\Api\LedgerController;
use App\Http\Controllers\Api\WalletController;

Route::middleware('auth:sanctum')->group(function () {

    Route::get('/wallets', [WalletController::class, 'index'])
        ->middleware('throttle:60,1');

    Route::get('/ledger/{wallet_id}', [LedgerController::class, 'index'])
        ->middleware('throttle:60,1');
});
