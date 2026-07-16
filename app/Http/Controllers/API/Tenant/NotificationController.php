<?php

namespace App\Http\Controllers\API\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant\NotificationPreference;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class NotificationController extends Controller
{
    /**
     * Get in-app notifications for authenticated user.
     */
    public function index(Request $request): JsonResponse
    {
        $user = auth()->user();

        if (!$user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $query = $user->notifications();

        if ($request->boolean('unread')) {
            $query->unread();
        }

        $notifications = $query->orderBy('created_at', 'desc')->get();

        return response()->json($notifications);
    }

    /**
     * Mark a specific notification as read.
     */
    public function markAsRead(string $id): JsonResponse
    {
        $user = auth()->user();

        if (!$user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $notification = $user->notifications()->where('id', $id)->first();

        if (!$notification) {
            return response()->json(['message' => 'Notification not found.'], 404);
        }

        $notification->markAsRead();

        return response()->json(['message' => 'Notification marked as read.']);
    }

    /**
     * Mark all notifications as read.
     */
    public function markAllAsRead(): JsonResponse
    {
        $user = auth()->user();

        if (!$user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $user->unreadNotifications->markAsRead();

        return response()->json(['message' => 'All notifications marked as read.']);
    }

    /**
     * GET user notification preferences.
     */
    public function getPreferences(): JsonResponse
    {
        $userId = auth()->id() ?? '00000000-0000-0000-0000-000000000000';

        $eventTypes = ['order_completed', 'cnh_expiring', 'payment_received'];
        $preferences = [];

        foreach ($eventTypes as $event) {
            $pref = NotificationPreference::where('user_id', $userId)
                ->where('event_type', $event)
                ->first();

            $preferences[$event] = $pref ? $pref->channels : [
                'mail' => true,
                'database' => true,
                'sms' => false,
            ];
        }

        return response()->json($preferences);
    }

    /**
     * POST update user notification preferences.
     */
    public function updatePreferences(Request $request): JsonResponse
    {
        $userId = auth()->id() ?? '00000000-0000-0000-0000-000000000000';

        $data = $request->validate([
            'preferences' => 'required|array',
            'preferences.*.event_type' => 'required|string|in:order_completed,cnh_expiring,payment_received',
            'preferences.*.channels' => 'required|array',
            'preferences.*.channels.mail' => 'required|boolean',
            'preferences.*.channels.database' => 'required|boolean',
            'preferences.*.channels.sms' => 'required|boolean',
        ]);

        foreach ($data['preferences'] as $item) {
            NotificationPreference::updateOrCreate(
                ['user_id' => $userId, 'event_type' => $item['event_type']],
                ['channels' => $item['channels']]
            );
        }

        return response()->json(['message' => 'Preferências de notificação atualizadas com sucesso.']);
    }
}
