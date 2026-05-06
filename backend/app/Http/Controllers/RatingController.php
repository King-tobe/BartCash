<?php

namespace App\Http\Controllers;

use App\Http\Requests\Rating\CreateRatingRequest;
use App\Models\Rating;
use App\Models\Trade;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class RatingController extends Controller
{
    // -------------------------------------------------------------------------
    // POST /ratings
    // -------------------------------------------------------------------------
    public function create(CreateRatingRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $raterId   = Auth::id();

        $trade = Trade::find($validated['trade_id']);

        if ($trade->status !== 'completed') {
            return response()->json([
                'success' => false,
                'message' => 'Ratings can only be submitted for completed trades.',
            ], 422);
        }

        $isProposer = $trade->proposer_id === $raterId;
        $isReceiver = $trade->receiver_id === $raterId;

        if (!$isProposer && !$isReceiver) {
            return response()->json([
                'success' => false,
                'message' => 'You are not a participant in this trade.',
            ], 403);
        }

        $ratedId = $isProposer ? $trade->receiver_id : $trade->proposer_id;

        $existingRating = Rating::where('trade_id', $validated['trade_id'])
            ->where('rater_id', $raterId)
            ->first();

        if ($existingRating) {
            return response()->json([
                'success' => false,
                'message' => 'You have already submitted a rating for this trade.',
            ], 409);
        }

        $rating = DB::transaction(function () use ($validated, $raterId, $ratedId) {
            $rating = Rating::create([
                'trade_id' => $validated['trade_id'],
                'rater_id' => $raterId,
                'rated_id' => $ratedId,
                'score'    => $validated['score'],
                'review'   => $validated['review'] ?? null,
            ]);

            $average = Rating::where('rated_id', $ratedId)->avg('score');

            User::where('id', $ratedId)->update([
                'average_rating' => round($average, 2),
            ]);

            return $rating;
        });

        return response()->json([
            'success' => true,
            'message' => 'Rating submitted.',
            'data'    => [
                'rating' => [
                    'id'         => $rating->id,
                    'trade_id'   => $rating->trade_id,
                    'score'      => $rating->score,
                    'review'     => $rating->review,
                    'created_at' => $rating->created_at,
                ],
            ],
        ], 201);
    }

    // -------------------------------------------------------------------------
    // GET /users/{id}/ratings
    // -------------------------------------------------------------------------
    public function index(Request $request, string $userId): JsonResponse
    {
        $user = User::find($userId);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found.',
            ], 404);
        }

        $limit  = (int) $request->query('limit', 20);
        $cursor = $request->query('cursor');

        $query = Rating::with('rater')
            ->where('rated_id', $userId)
            ->orderByDesc('created_at');

        if ($cursor) {
            $query->where('id', '<', $cursor);
        }

        $ratings = $query->limit($limit + 1)->get();

        $hasMore    = $ratings->count() > $limit;
        $ratings    = $ratings->take($limit);
        $nextCursor = $hasMore ? $ratings->last()->id : null;

        $formatted = $ratings->map(fn($rating) => [
            'id'     => $rating->id,
            'score'  => $rating->score,
            'review' => $rating->review,
            'rater'  => [
                'id'            => $rating->rater->id,
                'first_name'    => $rating->rater->first_name,
                'last_name'     => $rating->rater->last_name,
                'profile_photo' => $rating->rater->profile_photo,
            ],
            'created_at' => $rating->created_at,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Ratings retrieved.',
            'data'    => [
                'ratings'        => $formatted,
                'average_rating' => $user->average_rating,
                'total_ratings'  => $ratings->count(),
                'next_cursor'    => $nextCursor,
            ],
        ]);
    }
}