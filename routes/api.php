<?php

use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    
    Route::get('health', function () {
        return response()->json([
            'success' => true,
            'message' => 'Bartcash API is running',
            'version' => 'v1',
        ]);
    });

    // Authentication endpoints (Sprint 2)
    Route::prefix('auth')->group(function () {
        // POST /register
        // POST /verify-otp
        // POST /resend-otp
        // POST /login
        // POST /refresh
        // POST /logout
        // POST /forgot-password
        // POST /reset-password
    });

    // Protected endpoints — requires valid JWT
    Route::middleware('auth:sanctum')->group(function () {
        // All authenticated endpoints go here
    });

});