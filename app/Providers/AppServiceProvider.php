<?php

namespace App\Providers;

use App\Http\Resources\UserResource;
use App\Support\LandingDestination;
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

            return [
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
        });
        Inertia::share('flash', fn () => [
            'success' => session('success'),
            'error' => session('error'),
        ]);
    }
}
