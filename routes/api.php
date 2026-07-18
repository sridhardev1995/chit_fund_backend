<?php

use App\Http\Controllers\Api\InstallmentController;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ChitController;
use App\Http\Controllers\Api\CustomerController;
use App\Http\Controllers\Api\CommissionController;


/*
|--------------------------------------------------------------------------
| Authentication APIs
|--------------------------------------------------------------------------
*/

Route::prefix('auth')->group(function () {

    // Public Login
    Route::post('/login', [AuthController::class, 'login']);


    // Protected Auth APIs
    Route::middleware('auth:sanctum')->group(function () {

        Route::post('/logout', [AuthController::class, 'logout']);

        Route::get('/profile', [AuthController::class, 'profile']);

    });

});


/*
|--------------------------------------------------------------------------
| Protected Application APIs
|--------------------------------------------------------------------------
*/

Route::middleware('auth:sanctum')->group(function () {


    /*
    |--------------------------------------------------------------------------
    | Chits Management
    |--------------------------------------------------------------------------
    */

    Route::apiResource(
        'chits',
        ChitController::class
    );


    /*
    |--------------------------------------------------------------------------
    | Customer Management
    |--------------------------------------------------------------------------
    */

    Route::apiResource(
        'customers',
        CustomerController::class
    );


    /*
    |--------------------------------------------------------------------------
    | Commission Settings (Admin Only)
    |--------------------------------------------------------------------------
    */

    Route::apiResource(
        'commission-settings',
        CommissionController::class
    );
    Route::get(
        '/chits/{chit}/installments',
        [ChitController::class, 'index']
    );
  Route::get('installments', [InstallmentController::class, 'allInstallments']);
});