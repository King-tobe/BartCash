<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\ItemController;
use App\Http\Controllers\TradeController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {

    // -------------------------------------------------------------------------
    // Authentication Routes — Public
    // -------------------------------------------------------------------------
    Route::prefix('auth')->group(function () {
        Route::post('register',        [AuthController::class, 'register']);
        Route::post('verify-otp',      [AuthController::class, 'verifyOtp']);
        Route::post('resend-otp',      [AuthController::class, 'resendOtp']);
        Route::post('login',           [AuthController::class, 'login']);
        Route::post('refresh',         [AuthController::class, 'refresh']);
        Route::post('forgot-password', [AuthController::class, 'forgotPassword']);
        Route::post('reset-password',  [AuthController::class, 'resetPassword']);
    });

    // -------------------------------------------------------------------------
    // Protected Routes — Require valid JWT
    // -------------------------------------------------------------------------
    Route::middleware('auth:api')->group(function () {

        // Auth
        Route::post('auth/logout', [AuthController::class, 'logout']);

        // Categories
        Route::get('categories', [CategoryController::class, 'index']);

        // User
        Route::get('user/profile',  [UserController::class, 'profile']);
        Route::put('user/profile',  [UserController::class, 'updateProfile']);
        Route::post('user/avatar',  [UserController::class, 'updateAvatar']);
        Route::delete('user',       [UserController::class, 'deleteAccount']);

        // Items — order matters: /items/mine must be before /items/{id}
        Route::post('items',                      [ItemController::class, 'create']);
        Route::get('items/mine',                  [ItemController::class, 'mine']);
        Route::get('items',                       [ItemController::class, 'index']);
        Route::get('items/{id}',                  [ItemController::class, 'show']);
        Route::put('items/{id}',                  [ItemController::class, 'update']);
        Route::patch('items/{id}/deactivate',     [ItemController::class, 'deactivate']);
        Route::delete('items/{id}',               [ItemController::class, 'destroy']);
        Route::post('items/{id}/images',          [ItemController::class, 'uploadImages']);
        Route::get('items/{id}/valuation',        [ItemController::class, 'valuation']);
        Route::post('items/{id}/valuation/retry', [ItemController::class, 'retryValuation']);

        // Trades
        Route::post('trades',                    [TradeController::class, 'create']);
        Route::get('trades',                     [TradeController::class, 'index']);
        Route::get('trades/{id}',                [TradeController::class, 'show']);
        Route::patch('trades/{id}/accept',       [TradeController::class, 'accept']);
        Route::patch('trades/{id}/decline',      [TradeController::class, 'decline']);
        Route::patch('trades/{id}/cancel',       [TradeController::class, 'cancel']);
        Route::patch('trades/{id}/complete',     [TradeController::class, 'complete']);

    });

});