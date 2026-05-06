<?php

namespace App\Http\Controllers;

use App\Http\Requests\Message\SendMessageRequest;
use App\Models\Message;
use App\Models\Trade;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class MessageController extends Controller
{
    // -------------------------------------------------------------------------
    // GET /trades/{id}/messages
    // -------------------------------------------------------------------------
    public function index(Request $request, string $tradeId): JsonResponse
    {
        $trade  = Trade::find($tradeId);
        $userId = Auth::id();

        if (!$trade) {
            return response()->json([
                'success' => false,
                'message' => 'Trade not found.',
            ], 404);
        }

        if ($trade->proposer_id !== $userId && $trade->receiver_id !== $userId) {
            return response()->json([
                'success' => false,
                'message' => 'You are not a participant in this trade.',
            ], 403);
        }

        $limit  = (int) $request->query('limit', 50);
        $cursor = $request->query('cursor');

        $query = Message::where('trade_id', $tradeId)
            ->orderBy('created_at', 'asc');

        if ($cursor) {
            $query->where('id', '>', $cursor);
        }

        $messages = $query->limit($limit + 1)->get();

        $hasMore    = $messages->count() > $limit;
        $messages   = $messages->take($limit);
        $nextCursor = $hasMore ? $messages->last()->id : null;

        Message::where('trade_id', $tradeId)
            ->where('sender_id', '!=', $userId)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        $formatted = $messages->map(fn($message) => [
            'id'         => $message->id,
            'sender_id'  => $message->sender_id,
            'body'       => $message->body,
            'read_at'    => $message->read_at,
            'created_at' => $message->created_at,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Messages retrieved.',
            'data'    => [
                'messages'    => $formatted,
                'next_cursor' => $nextCursor,
            ],
        ]);
    }

    // -------------------------------------------------------------------------
    // POST /trades/{id}/messages
    // -------------------------------------------------------------------------
    public function send(SendMessageRequest $request, string $tradeId): JsonResponse
    {
        $trade  = Trade::find($tradeId);
        $userId = Auth::id();

        if (!$trade) {
            return response()->json([
                'success' => false,
                'message' => 'Trade not found.',
            ], 404);
        }

        if ($trade->proposer_id !== $userId && $trade->receiver_id !== $userId) {
            return response()->json([
                'success' => false,
                'message' => 'You are not a participant in this trade.',
            ], 403);
        }

        $allowedStatuses = ['pending', 'accepted'];

        if (!in_array($trade->status, $allowedStatuses)) {
            return response()->json([
                'success' => false,
                'message' => 'This trade is closed. Messaging is disabled.',
            ], 422);
        }

        $message = Message::create([
            'trade_id'  => $tradeId,
            'sender_id' => $userId,
            'body'      => $request->validated()['body'],
            'read_at'   => null,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Message sent.',
            'data'    => [
                'message' => [
                    'id'         => $message->id,
                    'sender_id'  => $message->sender_id,
                    'body'       => $message->body,
                    'read_at'    => $message->read_at,
                    'created_at' => $message->created_at,
                ],
            ],
        ], 201);
    }

    // -------------------------------------------------------------------------
    // PATCH /trades/{id}/messages/read
    // -------------------------------------------------------------------------
    public function markRead(string $tradeId): JsonResponse
    {
        $trade  = Trade::find($tradeId);
        $userId = Auth::id();

        if (!$trade) {
            return response()->json([
                'success' => false,
                'message' => 'Trade not found.',
            ], 404);
        }

        if ($trade->proposer_id !== $userId && $trade->receiver_id !== $userId) {
            return response()->json([
                'success' => false,
                'message' => 'You are not a participant in this trade.',
            ], 403);
        }

        $count = Message::where('trade_id', $tradeId)
            ->where('sender_id', '!=', $userId)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return response()->json([
            'success' => true,
            'message' => 'Messages marked as read.',
            'data'    => [
                'marked_read' => $count,
            ],
        ]);
    }
}