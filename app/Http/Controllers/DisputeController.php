<?php

namespace App\Http\Controllers;

use App\Http\Requests\Dispute\CreateDisputeRequest;
use App\Models\Dispute;
use App\Models\Trade;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class DisputeController extends Controller
{
    // -------------------------------------------------------------------------
    // POST /disputes
    // -------------------------------------------------------------------------
    public function create(CreateDisputeRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $userId    = Auth::id();

        $trade = Trade::find($validated['trade_id']);

        $isProposer = $trade->proposer_id === $userId;
        $isReceiver = $trade->receiver_id === $userId;

        if (!$isProposer && !$isReceiver) {
            return response()->json([
                'success' => false,
                'message' => 'You are not a participant in this trade.',
            ], 403);
        }

        $allowedStatuses = ['accepted', 'completed'];

        if (!in_array($trade->status, $allowedStatuses)) {
            return response()->json([
                'success' => false,
                'message' => 'A dispute can only be raised on accepted or completed trades.',
            ], 422);
        }

        $existingDispute = Dispute::where('trade_id', $validated['trade_id'])
            ->whereIn('status', ['open', 'under_review'])
            ->first();

        if ($existingDispute) {
            return response()->json([
                'success' => false,
                'message' => 'An open dispute already exists for this trade.',
                'data'    => [
                    'dispute_id' => $existingDispute->id,
                ],
            ], 409);
        }

        $dispute = DB::transaction(function () use ($validated, $userId, $trade) {
            $dispute = Dispute::create([
                'trade_id'  => $validated['trade_id'],
                'raised_by' => $userId,
                'reason'    => $validated['reason'],
                'status'    => 'open',
            ]);

            $trade->update(['status' => 'disputed']);

            return $dispute;
        });

        return response()->json([
            'success' => true,
            'message' => 'Dispute raised. Both parties have been notified.',
            'data'    => [
                'dispute' => [
                    'id'         => $dispute->id,
                    'trade_id'   => $dispute->trade_id,
                    'status'     => $dispute->status,
                    'reason'     => $dispute->reason,
                    'created_at' => $dispute->created_at,
                ],
            ],
        ], 201);
    }

    // -------------------------------------------------------------------------
    // GET /disputes/{id}
    // -------------------------------------------------------------------------
    public function show(string $id): JsonResponse
    {
        $dispute = Dispute::with(['trade', 'raisedBy'])->find($id);

        if (!$dispute) {
            return response()->json([
                'success' => false,
                'message' => 'Dispute not found.',
            ], 404);
        }

        $userId = Auth::id();
        $trade  = $dispute->trade;

        if ($trade->proposer_id !== $userId && $trade->receiver_id !== $userId) {
            return response()->json([
                'success' => false,
                'message' => 'You are not a participant in this trade.',
            ], 403);
        }

        return response()->json([
            'success' => true,
            'message' => 'Dispute retrieved.',
            'data'    => [
                'dispute' => [
                    'id'               => $dispute->id,
                    'status'           => $dispute->status,
                    'reason'           => $dispute->reason,
                    'resolution_notes' => $dispute->resolution_notes,
                    'raised_by'        => [
                        'id'            => $dispute->raisedBy->id,
                        'first_name'    => $dispute->raisedBy->first_name,
                        'last_name'     => $dispute->raisedBy->last_name,
                        'profile_photo' => $dispute->raisedBy->profile_photo,
                    ],
                    'trade' => [
                        'id'     => $trade->id,
                        'status' => $trade->status,
                    ],
                    'created_at' => $dispute->created_at,
                ],
            ],
        ]);
    }
}