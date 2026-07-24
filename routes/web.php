<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response()->json([
        'status' => true,
        'message' => 'Laravel API is running successfully'
    ]);
});