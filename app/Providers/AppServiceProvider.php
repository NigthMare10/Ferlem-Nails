<?php

namespace App\Providers;

use App\Http\Resources\InternalNotificationResource;
use App\Http\Resources\UserResource;
use App\Support\LandingDestination;
use App\Support\Permissions;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Inertia\Inertia;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::before(fn ($user) => $user->hasRole('owner') ? true : null);

        Inertia::share('auth', function () {
            if (! auth()->check()) {
                return null;
            }

            $user = auth()->user();
            $landing = app(LandingDestination::class);

            $auth = [
                'user' => (new UserResource($user))->resolve(),
                'roles' => $user->getRoleNames()->values(),
                'permissions' => $user->getAllPermissions()->pluck('name')->values(),
                'navigation' => [
                    'home' => $landing->canAccessHome($user),
                    'sales' => $landing->canNavigateToSales($user),
                    'appointments' => $landing->canNavigateToAppointments($user),
                    'earnings' => $landing->canNavigateToEarnings($user),
                ],
            ];

            if ($user->is_active && $user->hasPermissionTo(Permissions::NOTIFICATIONS_ACCESS)) {
                $query = $user->internalNotifications();
                $auth['notifications'] = [
                    'unread_count' => (clone $query)->whereNull('read_at')->count(),
                    'recent' => InternalNotificationResource::collection(
                        (clone $query)->limit(10)->get(),
                    )->resolve(request()),
                ];
            }

            return $auth;
        });
        Inertia::share('flash', fn () => [
            'success' => session('success'),
            'error' => session('error'),
        ]);
    }
}
