<?php

use App\Http\Controllers\Api\DevWebhookController;
use App\Http\Controllers\Api\WebhookController;
use Illuminate\Support\Facades\Route;

Route::post('/dev/webhooks/sign', [DevWebhookController::class, 'sign'])
    ->middleware('throttle:60,1');

Route::post('/webhooks/settlement', [WebhookController::class, 'settlement'])
    ->middleware(['webhook.verify', 'throttle:120,1']);
