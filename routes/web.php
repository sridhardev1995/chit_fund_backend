<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response()->json([
        'success' => true,
        'message' => 'Chit Fund API is Running',
        'version' => '1.0.0'
    ]);
});