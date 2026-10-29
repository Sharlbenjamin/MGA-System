<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MobilePushToken;
use App\Services\MobilePushTokenStore;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class NotificationApiController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $query = $user->notifications();
        if ($request->boolean('unread')) {
            $query->whereNull('read_at');
        }

        $perPage = min(max((int) $request->input('per_page', 30), 1), 100);
        $page = $query->latest()->paginate($perPage);

        return response()->json([
            'unread_count' => $user->unreadNotifications()->count(),
            'data' => $page->items(),
            'current_page' => $page->currentPage(),
            'last_page' => $page->lastPage(),
        ]);
    }

    public function read(Request $request, string $id): JsonResponse
    {
        $notification = $request->user()->notifications()->where('id', $id)->first();
        if (! $notification) {
            return response()->json(['message' => 'Notification not found'], 404);
        }
        $notification->markAsRead();

        return response()->json(['message' => 'Read']);
    }

    public function readAll(Request $request): JsonResponse
    {
        $request->user()->unreadNotifications->markAsRead();

        return response()->json(['message' => 'All notifications marked read']);
    }

    public function storePushToken(Request $request, MobilePushTokenStore $store): JsonResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string', 'max:255'],
            'platform' => ['nullable', 'string', 'max:20'],
            'notification_sound' => ['nullable', 'string', 'max:40'],
        ]);
        $store->put(
            $request->user()->id,
            $data['token'],
            $data['platform'] ?? 'unknown',
            $data['notification_sound'] ?? null,
        );

        return response()->json(['message' => 'Push token saved']);
    }

    public function updatePushPreferences(Request $request, MobilePushTokenStore $store): JsonResponse
    {
        $data = $request->validate([
            'notification_sound' => ['required', 'string', 'max:40'],
            'token' => ['nullable', 'string', 'max:255'],
        ]);
        $sound = MobilePushTokenStore::normalizeNotificationSound($data['notification_sound']);
        $store->updateNotificationSound(
            $request->user()->id,
            $sound,
            $data['token'] ?? null,
        );

        return response()->json([
            'message' => 'Push preferences saved',
            'notification_sound' => $sound,
        ]);
    }

    public function destroyPushToken(Request $request, MobilePushTokenStore $store): JsonResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string', 'max:255'],
        ]);
        $store->forget($request->user()->id, $data['token']);

        return response()->json(['message' => 'Push token removed']);
    }

    public function pushStatus(Request $request, MobilePushTokenStore $store): JsonResponse
    {
        $user = $request->user();
        $tokens = $store->pushTargetsForUser($user->id);
        $tableReady = Schema::hasTable('mobile_push_tokens');
        $lastRegistered = null;
        if ($tableReady) {
            $lastRegistered = MobilePushToken::query()
                ->where('user_id', $user->id)
                ->orderByDesc('updated_at')
                ->value('updated_at');
        }

        $pendingReceipts = null;
        if (Schema::hasTable('expo_push_receipts')) {
            $pendingReceipts = \App\Models\ExpoPushReceipt::query()->where('state', 'pending')->count();
        }

        return response()->json([
            'registered_device_count' => count($tokens),
            'storage' => $tableReady ? 'database' : 'cache_fallback',
            'pending_expo_receipts' => $pendingReceipts,
            'last_registered_at' => $lastRegistered,
            'expo_project_hint' => '27a8000f-3bdf-41ac-a0d4-fa35239ea394',
            'bundle_id' => 'com.medguarda.MGA',
            'remote_push_required' => true,
            'note' => count($tokens) === 0
                ? 'No device token on server — open the app while online to register.'
                : 'If lock-screen alerts still fail, verify APNs credentials on EAS and deploy backend migrations.',
        ]);
    }
}
