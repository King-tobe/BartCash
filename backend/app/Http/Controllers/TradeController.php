<?php

namespace App\Http\Controllers;

use App\Http\Requests\Trade\AcceptTradeRequest;
use App\Http\Requests\Trade\CreateTradeRequest;
use App\Http\Requests\Trade\ListTradesRequest;
use App\Models\Item;
use App\Models\Trade;
use App\Models\TradeItem;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class TradeController extends Controller
{
    // -------------------------------------------------------------------------
    // POST /trades
    // -------------------------------------------------------------------------
    public function create(CreateTradeRequest $request): JsonResponse
    {
        $validated  = $request->validated();
        $proposerId = Auth::id();

        if ($validated['receiver_id'] === $proposerId) {
            return response()->json([
                'success' => false,
                'message' => 'You cannot propose a trade with yourself.',
            ], 403);
        }

        $receiver = User::find($validated['receiver_id']);
        if (!$receiver) {
            return response()->json([
                'success' => false,
                'message' => 'Receiver not found.',
            ], 404);
        }

        $receiverItem = Item::find($validated['receiver_item_id']);
        if (
            !$receiverItem ||
            $receiverItem->user_id !== $validated['receiver_id'] ||
            $receiverItem->status !== 'available'
        ) {
            return response()->json([
                'success' => false,
                'message' => 'The requested item is not available for trade.',
            ], 422);
        }

        $offeredItems = Item::whereIn('id', $validated['offered_item_ids'])->get();

        if ($offeredItems->count() !== count($validated['offered_item_ids'])) {
            return response()->json([
                'success' => false,
                'message' => 'One or more offered items were not found.',
            ], 422);
        }

        foreach ($offeredItems as $item) {
            if ($item->user_id !== $proposerId || $item->status !== 'available') {
                return response()->json([
                    'success' => false,
                    'message' => 'One or more offered items are not available for trade.',
                ], 422);
            }
        }

        $trade = DB::transaction(function () use ($validated, $proposerId, $receiverItem, $offeredItems) {
            $trade = Trade::create([
                'proposer_id' => $proposerId,
                'receiver_id' => $validated['receiver_id'],
                'status'      => 'pending',
            ]);

            TradeItem::create([
                'trade_id' => $trade->id,
                'item_id'  => $receiverItem->id,
                'side'     => 'receiver',
            ]);

            foreach ($offeredItems as $item) {
                TradeItem::create([
                    'trade_id' => $trade->id,
                    'item_id'  => $item->id,
                    'side'     => 'proposer',
                ]);
            }

            $allItemIds = array_merge(
                [$receiverItem->id],
                $offeredItems->pluck('id')->toArray()
            );

            Item::whereIn('id', $allItemIds)->update(['status' => 'in_trade']);

            return $trade;
        });

        $trade->load(['proposer', 'receiver', 'proposerItems.item', 'receiverItems.item']);

        return response()->json([
            'success' => true,
            'message' => 'Trade proposal sent.',
            'data'    => [
                'trade' => $this->formatTrade($trade),
            ],
        ], 201);
    }

    // -------------------------------------------------------------------------
    // GET /trades
    // -------------------------------------------------------------------------
    public function index(ListTradesRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $userId    = Auth::id();
        $limit     = $validated['limit'] ?? 20;

        $query = Trade::with([
            'proposer',
            'receiver',
            'proposerItems.item.primaryImage',
            'receiverItems.item.primaryImage',
            'recentMessages',
        ])
            ->where(function ($q) use ($userId) {
                $q->where('proposer_id', $userId)
                  ->orWhere('receiver_id', $userId);
            });

        if (!empty($validated['status'])) {
            $query->where('status', $validated['status']);
        }

        if (!empty($validated['cursor'])) {
            $query->where('id', '<', $validated['cursor']);
        }

        $trades = $query->orderByDesc('updated_at')
            ->limit($limit + 1)
            ->get();

        $hasMore    = $trades->count() > $limit;
        $trades     = $trades->take($limit);
        $nextCursor = $hasMore ? $trades->last()->id : null;

        $formatted = $trades->map(function ($trade) use ($userId) {
            $otherParty = $trade->proposer_id === $userId
                ? $trade->receiver
                : $trade->proposer;

            $itemsPreview = array_merge(
                $trade->proposerItems->map(fn($ti) => $ti->item->primaryImage?->url)->filter()->values()->toArray(),
                $trade->receiverItems->map(fn($ti) => $ti->item->primaryImage?->url)->filter()->values()->toArray()
            );

            $lastMessage = $trade->recentMessages->first();

            return [
                'id'          => $trade->id,
                'status'      => $trade->status,
                'other_party' => [
                    'id'            => $otherParty->id,
                    'first_name'    => $otherParty->first_name,
                    'last_name'     => $otherParty->last_name,
                    'profile_photo' => $otherParty->profile_photo,
                ],
                'items_preview' => $itemsPreview,
                'last_message'  => $lastMessage ? [
                    'body'       => $lastMessage->body,
                    'created_at' => $lastMessage->created_at,
                ] : null,
                'updated_at' => $trade->updated_at,
            ];
        });

        return response()->json([
            'success' => true,
            'message' => 'Trades retrieved.',
            'data'    => [
                'trades'      => $formatted,
                'next_cursor' => $nextCursor,
            ],
        ]);
    }

    // -------------------------------------------------------------------------
    // GET /trades/{id}
    // -------------------------------------------------------------------------
    public function show(string $id): JsonResponse
    {
        $trade = Trade::with([
            'proposer',
            'receiver',
            'proposerItems.item.images',
            'proposerItems.item.latestValuation',
            'receiverItems.item.images',
            'receiverItems.item.latestValuation',
            'recentMessages',
            'dispute',
            'ratings',
        ])->find($id);

        if (!$trade) {
            return response()->json([
                'success' => false,
                'message' => 'Trade not found.',
            ], 404);
        }

        $userId = Auth::id();

        if ($trade->proposer_id !== $userId && $trade->receiver_id !== $userId) {
            return response()->json([
                'success' => false,
                'message' => 'You are not a participant in this trade.',
            ], 403);
        }

        return response()->json([
            'success' => true,
            'message' => 'Trade retrieved.',
            'data'    => [
                'trade' => $this->formatTrade($trade, true),
            ],
        ]);
    }

    // -------------------------------------------------------------------------
    // PATCH /trades/{id}/accept
    // -------------------------------------------------------------------------
    public function accept(AcceptTradeRequest $request, string $id): JsonResponse
    {
        $trade  = Trade::find($id);
        $userId = Auth::id();

        if (!$trade) {
            return response()->json([
                'success' => false,
                'message' => 'Trade not found.',
            ], 404);
        }

        if ($trade->receiver_id !== $userId) {
            return response()->json([
                'success' => false,
                'message' => 'Only the receiver can accept this trade.',
            ], 403);
        }

        if ($trade->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'This trade is no longer pending.',
            ], 422);
        }

        $trade->update([
            'status'            => 'accepted',
            'completion_method' => $request->validated()['completion_method'],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Trade accepted.',
            'data'    => [
                'trade' => [
                    'id'                => $trade->id,
                    'status'            => $trade->status,
                    'completion_method' => $trade->completion_method,
                ],
            ],
        ]);
    }

    // -------------------------------------------------------------------------
    // PATCH /trades/{id}/decline
    // -------------------------------------------------------------------------
    public function decline(string $id): JsonResponse
    {
        $trade  = Trade::with('tradeItems.item')->find($id);
        $userId = Auth::id();

        if (!$trade) {
            return response()->json([
                'success' => false,
                'message' => 'Trade not found.',
            ], 404);
        }

        if ($trade->receiver_id !== $userId) {
            return response()->json([
                'success' => false,
                'message' => 'Only the receiver can decline this trade.',
            ], 403);
        }

        if ($trade->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'This trade is no longer pending.',
            ], 422);
        }

        DB::transaction(function () use ($trade) {
            $trade->update(['status' => 'declined']);

            $itemIds = $trade->tradeItems->pluck('item_id')->toArray();
            Item::whereIn('id', $itemIds)->update(['status' => 'available']);
        });

        return response()->json([
            'success' => true,
            'message' => 'Trade declined.',
            'data'    => [
                'trade' => [
                    'id'     => $trade->id,
                    'status' => $trade->status,
                ],
            ],
        ]);
    }

    // -------------------------------------------------------------------------
    // PATCH /trades/{id}/cancel
    // -------------------------------------------------------------------------
    public function cancel(string $id): JsonResponse
    {
        $trade  = Trade::with('tradeItems.item')->find($id);
        $userId = Auth::id();

        if (!$trade) {
            return response()->json([
                'success' => false,
                'message' => 'Trade not found.',
            ], 404);
        }

        if ($trade->proposer_id !== $userId) {
            return response()->json([
                'success' => false,
                'message' => 'Only the proposer can cancel this trade.',
            ], 403);
        }

        if ($trade->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'Only pending trades can be cancelled.',
            ], 422);
        }

        DB::transaction(function () use ($trade, $userId) {
            $trade->update([
                'status'       => 'cancelled',
                'cancelled_at' => now(),
                'cancelled_by' => $userId,
            ]);

            $itemIds = $trade->tradeItems->pluck('item_id')->toArray();
            Item::whereIn('id', $itemIds)->update(['status' => 'available']);
        });

        return response()->json([
            'success' => true,
            'message' => 'Trade cancelled.',
            'data'    => [
                'trade' => [
                    'id'     => $trade->id,
                    'status' => $trade->status,
                ],
            ],
        ]);
    }

    // -------------------------------------------------------------------------
    // PATCH /trades/{id}/complete
    // -------------------------------------------------------------------------
    public function complete(string $id): JsonResponse
    {
        $trade  = Trade::with('tradeItems')->find($id);
        $userId = Auth::id();

        if (!$trade) {
            return response()->json([
                'success' => false,
                'message' => 'Trade not found.',
            ], 404);
        }

        $isProposer = $trade->proposer_id === $userId;
        $isReceiver = $trade->receiver_id === $userId;

        if (!$isProposer && !$isReceiver) {
            return response()->json([
                'success' => false,
                'message' => 'You are not a participant in this trade.',
            ], 403);
        }

        if ($trade->status !== 'accepted') {
            return response()->json([
                'success' => false,
                'message' => 'Only accepted trades can be completed.',
            ], 422);
        }

        DB::transaction(function () use ($trade, $isProposer, $isReceiver) {
            if ($isProposer) {
                $trade->update(['proposer_confirmed' => true]);
            }

            if ($isReceiver) {
                $trade->update(['receiver_confirmed' => true]);
            }

            $trade->refresh();

            if ($trade->proposer_confirmed && $trade->receiver_confirmed) {
                $trade->update([
                    'status'       => 'completed',
                    'completed_at' => now(),
                ]);

                $itemIds = $trade->tradeItems->pluck('item_id')->toArray();
                Item::whereIn('id', $itemIds)->update(['status' => 'traded']);
            }
        });

        $trade->refresh();

        return response()->json([
            'success' => true,
            'message' => $trade->status === 'completed'
                ? 'Trade completed!'
                : 'Completion confirmed. Waiting for the other party to confirm.',
            'data'    => [
                'trade' => [
                    'id'                 => $trade->id,
                    'status'             => $trade->status,
                    'proposer_confirmed' => $trade->proposer_confirmed,
                    'receiver_confirmed' => $trade->receiver_confirmed,
                ],
            ],
        ]);
    }

    // -------------------------------------------------------------------------
    // Private helper — formats a trade object for API responses
    // -------------------------------------------------------------------------
    private function formatTrade(Trade $trade, bool $detailed = false): array
    {
        $base = [
            'id'                 => $trade->id,
            'status'             => $trade->status,
            'completion_method'  => $trade->completion_method,
            'proposer_confirmed' => $trade->proposer_confirmed,
            'receiver_confirmed' => $trade->receiver_confirmed,
            'completed_at'       => $trade->completed_at,
            'proposer'           => [
                'id'            => $trade->proposer->id,
                'first_name'    => $trade->proposer->first_name,
                'last_name'     => $trade->proposer->last_name,
                'profile_photo' => $trade->proposer->profile_photo,
                'average_rating'=> $trade->proposer->average_rating,
            ],
            'receiver'           => [
                'id'            => $trade->receiver->id,
                'first_name'    => $trade->receiver->first_name,
                'last_name'     => $trade->receiver->last_name,
                'profile_photo' => $trade->receiver->profile_photo,
                'average_rating'=> $trade->receiver->average_rating,
            ],
            'proposer_items' => $trade->proposerItems->map(fn($ti) => $this->formatTradeItem($ti, $detailed)),
            'receiver_items' => $trade->receiverItems->map(fn($ti) => $this->formatTradeItem($ti, $detailed)),
            'created_at'     => $trade->created_at,
        ];

        if ($detailed) {
            $base['recent_messages'] = $trade->recentMessages?->map(fn($m) => [
                'id'         => $m->id,
                'sender_id'  => $m->sender_id,
                'body'       => $m->body,
                'created_at' => $m->created_at,
            ]);
            $base['dispute'] = $trade->dispute ? [
                'id'     => $trade->dispute->id,
                'status' => $trade->dispute->status,
                'reason' => $trade->dispute->reason,
            ] : null;
        }

        return $base;
    }

    private function formatTradeItem(TradeItem $tradeItem, bool $detailed = false): array
    {
        $item = $tradeItem->item;

        $base = [
            'id'        => $item->id,
            'title'     => $item->title,
            'condition' => $item->condition,
            'status'    => $item->status,
            'valuation' => $item->latestValuation ? [
                'value_min' => $item->latestValuation->value_min,
                'value_max' => $item->latestValuation->value_max,
                'status'    => $item->latestValuation->status,
            ] : null,
            'primary_image' => $item->primaryImage?->url,
        ];

        if ($detailed) {
            $base['images'] = $item->images?->map(fn($img) => [
                'id'         => $img->id,
                'url'        => $img->url,
                'is_primary' => $img->is_primary,
            ]);
        }

        return $base;
    }
}