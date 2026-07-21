<?php

namespace App\Http\Controllers;

use App\Models\Service;
use App\Models\User;
use App\Support\LandingDestination;
use App\Support\Permissions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class HomeController extends Controller
{
    public function __invoke(Request $request, LandingDestination $landing): Response|RedirectResponse
    {
        if (! $landing->canAccessHome($request->user())) {
            $destination = $landing->for($request->user());
            abort_if($destination === null, 403);

            return redirect()->to($destination);
        }

        $metrics = [];

        if ($request->user()->can(Permissions::SERVICES_VIEW)) {
            $metrics['active_services'] = Service::query()->where('is_active', true)->count();
        }

        if ($request->user()->can(Permissions::USERS_VIEW)) {
            $metrics['active_users'] = User::query()->where('is_active', true)->count();
        }

        return Inertia::render('Home', ['metrics' => $metrics]);
    }
}
