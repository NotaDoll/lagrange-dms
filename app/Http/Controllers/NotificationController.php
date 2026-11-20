<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function unreadCount(Request $request)
    {
        return response()->json([
            'count' => $request->user()->notifications()->where('is_read', false)->count(),
        ]);
    }

    /** The list. A notification is marked read only when the tenant opens it (or uses Mark All Read). */
    public function index(Request $request)
    {
        $notifications = $request->user()
            ->notifications()
            ->latest()
            ->paginate(20);

        return view('notifications.index', compact('notifications'));
    }

    /** Opening a notification in its modal marks it read. */
    public function markRead(Request $request, Notification $notification)
    {
        abort_unless($notification->user_id === $request->user()->id, 404);

        if (! $notification->is_read) {
            $notification->update(['is_read' => true]);
        }

        return response()->json([
            'read' => true,
            'unread' => $request->user()->notifications()->where('is_read', false)->count(),
        ]);
    }

    public function markAllRead(Request $request)
    {
        $request->user()
            ->notifications()
            ->where('is_read', false)
            ->update(['is_read' => true]);

        return redirect()
            ->route('notifications.index')
            ->with('success', 'Notifications marked as read.');
    }
}
