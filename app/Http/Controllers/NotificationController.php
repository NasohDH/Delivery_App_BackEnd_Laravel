<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $unreadNotifications = $user->unreadNotifications->select('id', 'data', 'read_at', 'created_at');
        $readNotifications = $user->readNotifications->select('id', 'data', 'read_at', 'created_at');
        if ($readNotifications->isNotEmpty() || $unreadNotifications->isNotEmpty()) {

            $user->unreadNotifications->markAsRead();

            return response()->json([
                'readNotifications' => $readNotifications,
                'unread_notifications' => $unreadNotifications,
            ],200);
        }

        return response()->json([
            'message' => 'No available notifications'
        ] , 404);
    }

    public function markAsRead(Request $request, $id)
    {
        $notification = $request->user()->notifications()->find($id);

        if ($notification) {
            $notification->markAsRead();
            return response()->json(['message' => 'Notification marked as read'] , 200);
        }

        return response()->json(['message' => 'Notification not found'], 404);
    }
}
