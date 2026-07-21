<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $notifications = $user->notifications()->latest()->limit(20)->get();

        return response()->json([
            'notifications' => $notifications->map(fn($n) => [
                'id'         => $n->id,
                'type'       => $n->data['type'] ?? null,
                'title'      => $n->data['title'] ?? '',
                'message'    => $n->data['message'] ?? '',
                'link'       => $n->data['link'] ?? null,
                'read'       => !is_null($n->read_at),
                'created_at' => $n->created_at->diffForHumans(),
            ]),
            'unread_count' => $user->unreadNotifications()->count(),
        ]);
    }

    public function markRead(Request $request, string $id): JsonResponse
    {
        $notification = $request->user()->notifications()->findOrFail($id);
        $notification->markAsRead();

        return response()->json(['message' => 'Notificación marcada como leída']);
    }

    public function markAllRead(Request $request): JsonResponse
    {
        $request->user()->unreadNotifications->markAsRead();

        return response()->json(['message' => 'Todas las notificaciones marcadas como leídas']);
    }
}