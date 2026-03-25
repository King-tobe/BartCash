<?php

namespace App\Http\Controllers;

use App\Http\Requests\User\DeleteAccountRequest;
use App\Http\Requests\User\UpdateAvatarRequest;
use App\Http\Requests\User\UpdateProfileRequest;
use App\Models\RefreshToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class UserController extends Controller
{
    // -------------------------------------------------------------------------
    // GET /user/profile
    // -------------------------------------------------------------------------
    public function profile(): JsonResponse
    {
        $user = Auth::user();

        return response()->json([
            'success' => true,
            'message' => 'Profile retrieved.',
            'data'    => [
                'user' => [
                    'id'                => $user->id,
                    'first_name'        => $user->first_name,
                    'last_name'         => $user->last_name,
                    'email'             => $user->email,
                    'profile_photo'     => $user->profile_photo,
                    'bio'               => $user->bio,
                    'location'          => $user->location,
                    'average_rating'    => $user->average_rating,
                    'total_trades'      => $user->total_trades,
                    'email_verified_at' => $user->email_verified_at,
                    'created_at'        => $user->created_at,
                ],
            ],
        ]);
    }

    // -------------------------------------------------------------------------
    // PUT /user/profile
    // -------------------------------------------------------------------------
    public function updateProfile(UpdateProfileRequest $request): JsonResponse
    {
        $user      = Auth::user();
        $validated = $request->validated();

        $user->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Profile updated.',
            'data'    => [
                'user' => [
                    'id'             => $user->id,
                    'first_name'     => $user->first_name,
                    'last_name'      => $user->last_name,
                    'email'          => $user->email,
                    'profile_photo'  => $user->profile_photo,
                    'bio'            => $user->bio,
                    'location'       => $user->location,
                    'average_rating' => $user->average_rating,
                    'total_trades'   => $user->total_trades,
                    'created_at'     => $user->created_at,
                ],
            ],
        ]);
    }

    // -------------------------------------------------------------------------
    // POST /user/avatar
    // -------------------------------------------------------------------------
    public function updateAvatar(UpdateAvatarRequest $request): JsonResponse
    {
        $user = Auth::user();
        $file = $request->file('avatar');

        if ($user->profile_photo) {
            $existingPath = parse_url($user->profile_photo, PHP_URL_PATH);
            $existingPath = ltrim($existingPath, '/');

            if (Storage::disk('r2')->exists($existingPath)) {
                Storage::disk('r2')->delete($existingPath);
            }
        }

        $path = $file->storeAs(
            'avatars',
            $user->id . '_' . time() . '.' . $file->getClientOriginalExtension(),
            'r2'
        );

        $url = rtrim(config('filesystems.disks.r2.url'), '/') . '/' . ltrim($path, '/');

        $user->update(['profile_photo' => $url]);

        return response()->json([
            'success' => true,
            'message' => 'Avatar updated.',
            'data'    => [
                'profile_photo' => $url,
            ],
        ]);
    }

    // -------------------------------------------------------------------------
    // DELETE /user
    // -------------------------------------------------------------------------
    public function deleteAccount(DeleteAccountRequest $request): JsonResponse
    {
        $user = Auth::user();

        RefreshToken::where('user_id', $user->id)
            ->where('revoked', false)
            ->update([
                'revoked'    => true,
                'revoked_at' => now(),
            ]);

        $user->delete();

        return response()->json([
            'success' => true,
            'message' => 'Account deleted successfully.',
        ]);
    }
}