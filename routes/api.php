<?php
use App\Http\Controllers\Api\InstallmentController;
use App\Http\Controllers\Api\PaymentController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ChitController;
use App\Http\Controllers\Api\CustomerController;
use App\Http\Controllers\Api\CommissionController;
use App\Http\Controllers\Api\SavingSchemeController;
use App\Http\Controllers\Api\SavingChitController;
use App\Http\Controllers\Api\SavingInstallmentController;

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

    // FIX: was pointing at ChitController::index (lists ALL chits) instead
    // of a specific chit's installments. Now uses the new installments()
    // method added to ChitController.
    Route::get(
        '/chits/{chit}/installments',
        [ChitController::class, 'installments']
    );

    Route::get('installments', [InstallmentController::class, 'allInstallments']);
    Route::apiResource('payments', PaymentController::class)->except(['update']);
    Route::apiResource('saving-schemes', SavingSchemeController::class);
    Route::apiResource('saving-chits', SavingChitController::class);

    /*
    |--------------------------------------------------------------------------
    | Saving Chit Installments (now JSON-based, stored inside saving_chits
    | row -- no more separate saving_installments table)
    |--------------------------------------------------------------------------
    */

    // List one chit's full 52-week schedule (reads straight from the
    // chit row's `installments` JSON column).
    Route::get('saving-chits/{savingChit}/installments', [SavingInstallmentController::class, 'index']);

    // CHANGED: pay() now needs BOTH the chit id AND the week number,
    // since installments no longer have their own primary key / row.
    // Old:  POST saving-installments/{installment}/pay
    // New:  POST saving-chits/{savingChit}/installments/{installmentNumber}/pay
    Route::post(
        'saving-chits/{savingChit}/installments/{installmentNumber}/pay',
        [SavingInstallmentController::class, 'pay']
    );

    // Multichit consolidated collection (e.g. Selva's 50 chits shown/paid as one weekly total)
    Route::get('customers/{customer}/saving-collections', [SavingInstallmentController::class, 'weeklyCollectionSummary']);
        Route::post('customers/{customer}/saving-collections/{installmentNumber}/pay', [SavingInstallmentController::class, 'payWeeklyCollection']);
});