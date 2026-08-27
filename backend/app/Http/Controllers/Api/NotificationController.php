<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $notifications = Notification::query()
            ->where('user_id', $request->user()->id)
            ->with('subjectUser:id,name')
            ->latest('id')
            ->limit(100)
            ->get();

        return response()->json([
            'unread_count' => $notifications->whereNull('read_at')->count(),
            'notifications' => $notifications->map(fn (Notification $n) => [
                'id' => $n->id,
                'type' => $n->type,
                'title' => $n->title,
                'body' => $n->body,
                'subject_name' => $n->subjectUser?->name,
                'leave_request_id' => $n->leave_request_id,
                'read_at' => $n->read_at?->toDateTimeString(),
                'created_at' => $n->created_at?->toDateTimeString(),
                'created_at_human' => $n->created_at?->diffForHumans(),
            ])->all(),
        ]);
    }

    public function markRead(Request $request, Notification $notification): JsonResponse
    {
        if ($notification->user_id !== $request->user()->id) {
            abort(403, 'Ky njoftim nuk ju përket.');
        }

        $notification->update(['read_at' => now()]);

        return response()->json(['message' => 'U shënua si i lexuar.']);
    }

    public function markAllRead(Request $request): JsonResponse
    {
        Notification::where('user_id', $request->user()->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return response()->json(['message' => 'Të gjitha njoftimet u shënuan si të lexuara.']);
    }
}
