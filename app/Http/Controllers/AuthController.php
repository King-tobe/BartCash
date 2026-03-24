<?php

namespace App\Http\Controllers;

use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Requests\Auth\VerifyOtpRequest;
use App\Http\Requests\Auth\ResendOtpRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RefreshTokenRequest;
use App\Http\Requests\Auth\LogoutRequest;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Models\PasswordResetToken;
use App\Models\User;
use App\Models\RefreshToken;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use App\Mail\OtpMail;
use App\Mail\PasswordResetMail;

class AuthController extends Controller
{
    public function register(RegisterRequest $request)
    {
        if (User::where('email', $request->email)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Email address is already registered.',
                'errors'  => ['email' => ['An account with this email already exists.']],
            ], 409);
        }

        $user = User::create([
            'id'         => Str::uuid(),
            'first_name' => $request->first_name,
            'last_name'  => $request->last_name,
            'email'      => $request->email,
            'password'   => bcrypt($request->password),
            'is_active'  => true,
        ]);

        $otp = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        Cache::put('otp:' . $user->email, [
            'hash'     => bcrypt($otp),
            'attempts' => 0,
        ], now()->addMinutes(10));

Mail::to($user->email)->send(new OtpMail($otp, $user->first_name));

        return response()->json([
            'success' => true,
            'message' => 'Account created. Please verify your email.',
            'data'    => [
                'user_id' => $user->id,
                'email'   => $user->email,
            ],
        ], 201);
    }

    public function verifyOtp(VerifyOtpRequest $request)
    {
        $cacheKey = 'otp:' . $request->email;
        $otpData  = Cache::get($cacheKey);

        // OTP not found or expired
        if (!$otpData) {
            return response()->json([
                'success' => false,
                'message' => 'OTP has expired or does not exist. Please request a new one.',
                'errors'  => ['otp' => ['Code has expired or is invalid.']],
            ], 400);
        }

        // Max attempts check
        if ($otpData['attempts'] >= 3) {
            Cache::forget($cacheKey);
            return response()->json([
                'success' => false,
                'message' => 'Too many incorrect attempts. Please request a new code.',
                'errors'  => ['otp' => ['Maximum attempts exceeded.']],
            ], 429);
        }

        // Wrong OTP
        if (!Hash::check($request->otp, $otpData['hash'])) {
            $otpData['attempts']++;
            Cache::put($cacheKey, $otpData, now()->addMinutes(10));

            return response()->json([
                'success' => false,
                'message' => 'Incorrect code. Please try again.',
                'errors'  => ['otp' => ['The code you entered is incorrect.']],
            ], 400);
        }

        // OTP is valid — find user and verify
        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found.',
            ], 404);
        }

        $user->email_verified_at = now();
        $user->save();

        Cache::forget($cacheKey);

        return response()->json([
            'success' => true,
            'message' => 'Email verified. You may now log in.',
        ], 200);
    }
    
    public function resendOtp(ResendOtpRequest $request)
{
    // Look up the user
    $user = User::where('email', $request->email)->first();

    // If no account exists, return generic success — never reveal account existence
    if (!$user) {
        return response()->json([
            'success' => true,
            'message' => 'If an unverified account exists with this email, a new code has been sent.',
        ], 200);
    }

    // If already verified, return 400
    if ($user->email_verified_at) {
        return response()->json([
            'success' => false,
            'message' => 'This email address is already verified.',
            'errors'  => ['email' => ['Account is already verified.']],
        ], 400);
    }

    // Rate limiting — max 3 resend requests per 30 minutes per email
    $rateLimitKey = 'resend-otp-limit:' . $request->email;
    $attempts = Cache::get($rateLimitKey, 0);

    if ($attempts >= 3) {
        return response()->json([
            'success' => false,
            'message' => 'Too many requests. Please try again in 30 minutes.',
            'errors'  => ['email' => ['Rate limit exceeded.']],
        ], 429);
    }

    // Increment rate limit counter
    Cache::put($rateLimitKey, $attempts + 1, now()->addMinutes(30));

    // Generate fresh OTP and overwrite previous one in cache
    $otp = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);

    Cache::put('otp:' . $user->email, [
        'hash'     => bcrypt($otp),
        'attempts' => 0,
    ], now()->addMinutes(10));

    // Send email
Mail::to($user->email)->send(new OtpMail($otp, $user->first_name));

    return response()->json([
        'success' => true,
        'message' => 'If an unverified account exists with this email, a new code has been sent.',
    ], 200);
}
public function login(LoginRequest $request)
{
    // Rate limiting — max 5 attempts per 15 minutes per email
    $rateLimitKey = 'login:' . $request->email;

    if (RateLimiter::tooManyAttempts($rateLimitKey, 5)) {
        $seconds = RateLimiter::availableIn($rateLimitKey);
        return response()->json([
            'success' => false,
            'message' => 'Too many failed attempts. Please try again in ' . ceil($seconds / 60) . ' minutes.',
        ], 429);
    }

    // Attempt to get the user by email
    $user = User::where('email', $request->email)->first();

    // Check credentials — never reveal which field is wrong
    if (!$user || !Hash::check($request->password, $user->password)) {
        // Increment the rate limit counter on failure
        RateLimiter::hit($rateLimitKey, 900); // 900 seconds = 15 minutes

        return response()->json([
            'success' => false,
            'message' => 'Invalid email or password.',
            'errors'  => ['credentials' => ['The provided credentials are incorrect.']],
        ], 401);
    }

    // Check account is active
    if (!$user->is_active) {
        return response()->json([
            'success' => false,
            'message' => 'Your account has been deactivated. Please contact support.',
        ], 403);
    }

    // Check email is verified
    if (!$user->email_verified_at) {
        return response()->json([
            'success' => false,
            'message' => 'Please verify your email before logging in.',
            'errors'  => ['email' => ['Email address is not verified.']],
        ], 403);
    }

    // Clear rate limit on successful login
    RateLimiter::clear($rateLimitKey);

    // Generate JWT access token
    $accessToken = Auth::guard('api')->login($user);

    // Generate refresh token — random 64 bytes, store hash in DB
    $plainRefreshToken = bin2hex(random_bytes(64));

    RefreshToken::create([
        'id'         => Str::uuid(),
        'user_id'    => $user->id,
        'token'      => hash('sha256', $plainRefreshToken),
        'expires_at' => now()->addDays(30),
        'revoked'    => false,
        'ip_address' => $request->ip(),
        'user_agent' => $request->userAgent(),
    ]);

    return response()->json([
        'success' => true,
        'message' => 'Login successful.',
        'data'    => [
            'access_token'  => $accessToken,
            'refresh_token' => $plainRefreshToken,
            'user'          => [
                'id'             => $user->id,
                'first_name'     => $user->first_name,
                'last_name'      => $user->last_name,
                'email'          => $user->email,
                'profile_photo'  => $user->profile_photo,
                'average_rating' => $user->average_rating,
            ],
        ],
    ], 200);
}
public function refresh(RefreshTokenRequest $request)
{
    // Hash the submitted token and look it up in the database
    $hashedToken = hash('sha256', $request->refresh_token);

    $tokenRecord = RefreshToken::where('token', $hashedToken)
        ->where('revoked', false)
        ->where('expires_at', '>', now())
        ->first();

    // Token not found, already revoked, or expired
    if (!$tokenRecord) {
        return response()->json([
            'success' => false,
            'message' => 'Invalid or expired refresh token. Please log in again.',
        ], 401);
    }

    // Get the associated user
    $user = User::find($tokenRecord->user_id);

    if (!$user || !$user->is_active) {
        return response()->json([
            'success' => false,
            'message' => 'User not found or account is inactive.',
        ], 401);
    }

    // Revoke the old refresh token
    $tokenRecord->update([
        'revoked'    => true,
        'revoked_at' => now(),
    ]);

    // Generate new JWT access token
    $newAccessToken = Auth::guard('api')->login($user);

    // Generate new refresh token
    $newPlainRefreshToken = bin2hex(random_bytes(64));

    RefreshToken::create([
        'id'         => Str::uuid(),
        'user_id'    => $user->id,
        'token'      => hash('sha256', $newPlainRefreshToken),
        'expires_at' => now()->addDays(5),
        'revoked'    => false,
        'ip_address' => $request->ip(),
        'user_agent' => $request->userAgent(),
    ]);

    return response()->json([
        'success' => true,
        'message' => 'Tokens refreshed.',
        'data'    => [
            'access_token'  => $newAccessToken,
            'refresh_token' => $newPlainRefreshToken,
        ],
    ], 200);
}
public function logout(LogoutRequest $request)
{
    // Hash the submitted refresh token and find it in the database
    $hashedToken = hash('sha256', $request->refresh_token);

    $tokenRecord = RefreshToken::where('token', $hashedToken)
        ->where('user_id', Auth::guard('api')->id())
        ->first();

    // If found, revoke it — if not found, proceed silently (idempotent)
    if ($tokenRecord) {
        $tokenRecord->update([
            'revoked'    => true,
            'revoked_at' => now(),
        ]);
    }

    // Invalidate the JWT access token
    Auth::guard('api')->logout();

    return response()->json([
        'success' => true,
        'message' => 'Logged out successfully.',
    ], 200);
}
public function forgotPassword(ForgotPasswordRequest $request)
{
    // Rate limiting — max 3 requests per 15 minutes per email
    $rateLimitKey = 'forgot-password:' . $request->email;

    if (RateLimiter::tooManyAttempts($rateLimitKey, 3)) {
        $seconds = RateLimiter::availableIn($rateLimitKey);
        return response()->json([
            'success' => false,
            'message' => 'Too many requests. Please try again in ' . ceil($seconds / 60) . ' minutes.',
        ], 429);
    }

    RateLimiter::hit($rateLimitKey, 900); // 900 seconds = 15 minutes

    // Look up user — if not found, return generic success (never reveal account existence)
    $user = User::where('email', $request->email)->first();

    if (!$user) {
        return response()->json([
            'success' => true,
            'message' => 'If an account exists with this email, a reset link has been sent.',
        ], 200);
    }

    // Invalidate any previous unused reset tokens for this user
    PasswordResetToken::where('user_id', $user->id)
        ->where('used', false)
        ->update(['used' => true, 'used_at' => now()]);

    // Generate a cryptographically secure plaintext token
    $plainToken = bin2hex(random_bytes(32));

    // Store the hashed version in the database
    PasswordResetToken::create([
        'id'         => Str::uuid(),
        'user_id'    => $user->id,
        'token'      => hash('sha256', $plainToken),
        'expires_at' => now()->addHour(),
        'used'       => false,
    ]);

    // Build the deep link the mobile app will open
    $resetLink = 'bartcash://reset-password?token=' . $plainToken;

    // Send the reset email
Mail::to($user->email)->send(new PasswordResetMail($resetLink, $user->first_name));

    return response()->json([
        'success' => true,
        'message' => 'If an account exists with this email, a reset link has been sent.',
    ], 200);
}
public function resetPassword(ResetPasswordRequest $request)
{
    // Hash the submitted token and look it up in the database
    $hashedToken = hash('sha256', $request->token);

    $tokenRecord = PasswordResetToken::where('token', $hashedToken)
        ->where('used', false)
        ->where('expires_at', '>', now())
        ->first();

    // Token not found, already used, or expired
    if (!$tokenRecord) {
        return response()->json([
            'success' => false,
            'message' => 'This reset link is invalid or has expired. Please request a new one.',
        ], 400);
    }

    // Get the associated user
    $user = User::find($tokenRecord->user_id);

    if (!$user) {
        return response()->json([
            'success' => false,
            'message' => 'User not found.',
        ], 404);
    }

    // Update the user's password
    $user->password = bcrypt($request->password);
    $user->save();

    // Mark the reset token as used
    $tokenRecord->update([
        'used'    => true,
        'used_at' => now(),
    ]);

    // Revoke all active refresh tokens for this user
    // This forces logout on all devices for security
    RefreshToken::where('user_id', $user->id)
        ->where('revoked', false)
        ->update([
            'revoked'    => true,
            'revoked_at' => now(),
        ]);

    return response()->json([
        'success' => true,
        'message' => 'Password reset successfully. Please log in with your new password.',
    ], 200);
}
}
