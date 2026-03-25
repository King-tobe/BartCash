<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ItemController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {

    Route::prefix('auth')->group(function () {
        Route::post('register', [AuthController::class, 'register']);
        Route::post('verify-otp', [AuthController::class, 'verifyOtp']);
Route::post('resend-otp', [AuthController::class, 'resendOtp']);
Route::post('login', [AuthController::class, 'login']);
Route::post('refresh', [AuthController::class, 'refresh']);
  Route::post('forgot-password', [AuthController::class, 'forgotPassword']);
Route::post('reset-password', [AuthController::class, 'resetPassword']);
    });

    Route::middleware('auth:api')->group(function () {
        // Authenticated endpoints go here
         Route::post('auth/logout', [AuthController::class, 'logout']);
        
        // Items — order matters: /items/mine must be before /items/{id}
        Route::post('items',                          [ItemController::class, 'create']);
        Route::get('items/mine',                      [ItemController::class, 'mine']);
        Route::get('items',                           [ItemController::class, 'index']);
        Route::get('items/{id}',                      [ItemController::class, 'show']);
        Route::put('items/{id}',                      [ItemController::class, 'update']);
        Route::patch('items/{id}/deactivate',         [ItemController::class, 'deactivate']);
        Route::delete('items/{id}',                   [ItemController::class, 'destroy']);
        Route::post('items/{id}/images',              [ItemController::class, 'uploadImages']);
        Route::get('items/{id}/valuation',            [ItemController::class, 'valuation']);
        Route::post('items/{id}/valuation/retry',     [ItemController::class, 'retryValuation']);
    });

});
