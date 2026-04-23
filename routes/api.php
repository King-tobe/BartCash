<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\DisputeController;
use App\Http\Controllers\ItemController;
use App\Http\Controllers\MessageController;
use App\Http\Controllers\RatingController;
use App\Http\Controllers\TradeController;
use App\Http\Controllers\NotificationController;
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

    // Temporary valuation debug route — REMOVE AFTER DEBUGGING
Route::get('v1/debug/valuation/{itemId}', function ($itemId) {
    $item = \App\Models\Item::with('images')->find($itemId);
    
    if (!$item) {
        return response()->json(['error' => 'Item not found']);
    }

    if ($item->images->isEmpty()) {
        return response()->json(['error' => 'No images on this item']);
    }

    $primaryImage = $item->images->firstWhere('is_primary', true) ?? $item->images->first();
    
    // Test fetching the image
    $publicUrl = rtrim(config('filesystems.disks.r2.url'), '/') . '/' . ltrim($primaryImage->storage_path, '/');
    
    try {
        $response = \Illuminate\Support\Facades\Http::timeout(15)->get($publicUrl);
        if (!$response->successful()) {
            return response()->json([
                'error' => 'Failed to fetch image',
                'url' => $publicUrl,
                'status' => $response->status()
            ]);
        }
    } catch (\Exception $e) {
        return response()->json([
            'error' => 'Image fetch exception',
            'url' => $publicUrl,
            'message' => $e->getMessage()
        ]);
    }

    // Test calling Groq
    try {
        $imageBase64 = base64_encode($response->body());
        $apiKey = config('services.groq.api_key');
        $model = config('services.groq.model');

        $groqResponse = \Illuminate\Support\Facades\Http::timeout(30)
            ->withToken($apiKey)
            ->post('https://api.groq.com/openai/v1/chat/completions', [
                'model' => $model,
                'temperature' => 0.1,
                'messages' => [[
                    'role' => 'user',
                    'content' => [
                        ['type' => 'image_url', 'image_url' => ['url' => 'data:image/jpeg;base64,' . $imageBase64]],
                        ['type' => 'text', 'text' => 'What is in this image? Respond in one sentence.'],
                    ],
                ]],
            ]);

        return response()->json([
            'image_url' => $publicUrl,
            'groq_status' => $groqResponse->status(),
            'groq_response' => $groqResponse->json(),
        ]);

    } catch (\Exception $e) {
        return response()->json([
            'error' => 'Groq exception',
            'message' => $e->getMessage()
        ]);
    }
});

// -------------------------------------------------------------------------
// Temporary Debug Route — REMOVE AFTER DEBUGGING
// -------------------------------------------------------------------------
Route::get('/debug/r2', function () {
    try {
        \Storage::disk('r2')->put('test.txt', 'hello from render');
        $url = \Storage::disk('r2')->url('test.txt');
        return response()->json(['status' => 'success', 'url' => $url]);
    } catch (\Exception $e) {
        return response()->json([
            'status' => 'error',
            'message' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
    }
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
Route::patch('items/{id}/valuation/override', [ItemController::class, 'overrideValuation']);

        // Trades
        Route::post('trades',                    [TradeController::class, 'create']);
        Route::get('trades',                     [TradeController::class, 'index']);
        Route::get('trades/{id}',                [TradeController::class, 'show']);
        Route::patch('trades/{id}/accept',       [TradeController::class, 'accept']);
        Route::patch('trades/{id}/decline',      [TradeController::class, 'decline']);
        Route::patch('trades/{id}/cancel',       [TradeController::class, 'cancel']);
        Route::patch('trades/{id}/complete',     [TradeController::class, 'complete']);

        // Messages
        Route::get('trades/{id}/messages',        [MessageController::class, 'index']);
        Route::post('trades/{id}/messages',       [MessageController::class, 'send']);
        Route::patch('trades/{id}/messages/read', [MessageController::class, 'markRead']);

        // Ratings
        Route::post('ratings',           [RatingController::class, 'create']);
        Route::get('users/{id}/ratings', [RatingController::class, 'index']);

        // Disputes
        Route::post('disputes',       [DisputeController::class, 'create']);
        Route::get('disputes/{id}',   [DisputeController::class, 'show']);

// Notification
       Route::get('notifications',                  [NotificationController::class, 'index']);
Route::patch('notifications/read-all',       [NotificationController::class, 'markAllAsRead']);
Route::patch('notifications/{id}/read',      [NotificationController::class, 'markAsRead']);
Route::delete('notifications/{id}',          [NotificationController::class, 'destroy']);     

    });

});