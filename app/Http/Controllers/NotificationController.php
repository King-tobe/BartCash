<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class NotificationController extends Controller
{
    /**
     * GET /notifications
     */
    
    public function index(Request $request)
{
    $user  = auth()->user();
    $limit = (int) ($request->query('limit', 20));
    $cursor = $request->query('cursor');

    $query = Notification::where('user_id', $user->id)
        ->orderByDesc('created_at');

    if ($cursor) {
        $query->where('id', '<', $cursor);
    }

    $notifications = $query->limit($limit + 1)->get();

    $hasMore    = $notifications->count() > $limit;
    $notifications = $notifications->take($limit);
    $nextCursor = $hasMore ? $notifications->last()->id : null;

    $unreadCount = Notification::where('user_id', $user->id)
        ->whereNull('read_at')
        ->count();

    return response()->json([
        'success' => true,
        'message' => 'Notifications retrieved.',
        'data'    => [
            'notifications' => $notifications->values(),
            'unread_count'  => $unreadCount,
            'next_cursor'   => $nextCursor,
        ],
    ]);
}

    /**
     * PATCH /notifications/{id}/read
     */
    public function markAsRead($id)
    {
        $notification = Notification::where('id', $id)
            ->where('user_id', auth()->id())
            ->first();

        if (!$notification) {
            return response()->json([
                'success' => false,
                'message' => 'Notification not found.',
            ], 404);
        }

        $notification->markAsRead();

        return response()->json([
            'success' => true,
            'message' => 'Notification marked as read.',
        ]);
    }

    /**
     * PATCH /notifications/read-all
     */
    public function markAllAsRead()
    {
        Notification::where('user_id', auth()->id())
            ->whereNull('read_at')
            ->update([
                'read_at' => Carbon::now(),
            ]);

        return response()->json([
            'success' => true,
            'message' => 'All notifications marked as read.',
        ]);
    }

    /**
     * DELETE /notifications/{id}
     */
    public function destroy($id)
    {
        $notification = Notification::where('id', $id)
            ->where('user_id', auth()->id())
            ->first();

        if (!$notification) {
            return response()->json([
                'success' => false,
                'message' => 'Notification not found.',
            ], 404);
        }

        $notification->delete();

        return response()->json([
            'success' => true,
            'message' => 'Notification deleted successfully.',
        ]);
    }
}