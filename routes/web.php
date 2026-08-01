<?php
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response()->json([
        'message' => 'Welcome to Tupay!',
        'status' => 'success',
        'data' => [
            'app_name' => config('app.name'),
            'app_url' => config('app.url'),
        ],
    ]);
});