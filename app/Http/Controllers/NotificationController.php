<?php

namespace App\Http\Controllers;

use App\Http\Resources\InternalNotificationResource;
use App\Models\InternalNotification;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Inertia\Inertia;
use Inertia\Response;

class NotificationController extends Controller
{
    public function index(Request $request): Response|AnonymousResourceCollection
    {
        $filter = $request->string('filter')->toString() === 'unread' ? 'unread' : 'all';
        $notifications = InternalNotificationResource::collection(
            $this->owned($request->user())
                ->when($filter === 'unread', fn ($query) => $query->whereNull('read_at'))
                ->latest()
                ->paginate(20)
                ->withQueryString(),
        );

        if ($request->expectsJson()) {
            return $notifications;
        }

        return Inertia::render('Notifications/Index', [
            'notifications' => $notifications,
            'filters' => ['filter' => $filter],
        ]);
    }

    public function recent(Request $request): AnonymousResourceCollection
    {
        return InternalNotificationResource::collection(
            $this->owned($request->user())->latest()->limit(10)->get(),
        );
    }

    public function read(Request $request, string $notification): JsonResponse
    {
        $owned = $this->owned($request->user())->whereKey($notification)->firstOrFail();
        if ($owned->read_at === null) {
            $owned->forceFill(['read_at' => now('UTC')])->save();
        }

        return response()->json(['data' => (new InternalNotificationResource($owned))->resolve($request)]);
    }

    public function readAll(Request $request): JsonResponse
    {
        $this->owned($request->user())->whereNull('read_at')->update(['read_at' => now('UTC')]);

        return response()->json(['unread_count' => 0]);
    }

    private function owned(User $user)
    {
        return InternalNotification::query()
            ->where('notifiable_type', User::class)
            ->where('notifiable_id', $user->getKey());
    }
}
