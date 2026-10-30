<?php

namespace App\Http\Controllers;

use App\Http\Resources\InternalNotificationResource;
use App\Models\InternalNotification;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class NotificationController extends Controller
{
    public function index(Request $request): Response
    {
        $filter = $request->string('filter')->toString() === 'unread' ? 'unread' : 'all';
        $notifications = InternalNotificationResource::collection(
            $this->owned($request->user())
                ->when($filter === 'unread', fn ($query) => $query->whereNull('read_at'))
                ->latest()
                ->paginate(20)
                ->withQueryString(),
        );

        return Inertia::render('Notifications/Index', [
            'notifications' => $notifications,
            'filters' => ['filter' => $filter],
        ]);
    }

    public function recent(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->snapshot($request->user(), $request)]);
    }

    public function read(Request $request, string $notification): JsonResponse
    {
        $owned = $this->owned($request->user())->whereKey($notification)->firstOrFail();
        if ($owned->read_at === null) {
            $owned->forceFill(['read_at' => now('UTC')])->save();
        }

        return response()->json(['data' => [
            'notification' => (new InternalNotificationResource($owned))->resolve($request),
            'unread_count' => $this->owned($request->user())->whereNull('read_at')->count(),
            'changed' => $owned->wasChanged('read_at'),
        ]]);
    }

    public function readAll(Request $request): JsonResponse
    {
        $readAt = now('UTC');
        $updated = $this->owned($request->user())->whereNull('read_at')->update(['read_at' => $readAt]);

        return response()->json(['data' => [
            'updated_count' => $updated,
            'unread_count' => $this->owned($request->user())->whereNull('read_at')->count(),
            'as_of' => $readAt->toIso8601String(),
        ]]);
    }

    private function owned(User $user)
    {
        return InternalNotification::query()
            ->where('notifiable_type', User::class)
            ->where('notifiable_id', $user->getKey());
    }

    private function snapshot(User $user, Request $request): array
    {
        $query = $this->owned($user);

        return [
            'unread_count' => (clone $query)->whereNull('read_at')->count(),
            'recent' => InternalNotificationResource::collection((clone $query)->latest()->limit(10)->get())->resolve($request),
            'as_of' => now('UTC')->toIso8601String(),
        ];
    }
}
