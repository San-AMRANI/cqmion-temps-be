<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response()->json([
        'message' => 'Invalid request.',
    ] );
});

Route::get('/login', function () {
    return response()->json([
        'message' => 'Unauthenticated. Use POST /api/login to obtain an API token.',
    ], 401);
})->name('login');
