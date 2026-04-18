<?php

namespace App\Modules\IdentidadAcceso\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Modules\IdentidadAcceso\Http\Requests\ReauthenticateAdminRequest;
use App\Modules\IdentidadAcceso\Services\AdminReauthenticationService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class AdminReauthenticationController extends Controller
{
    public function __construct(protected AdminReauthenticationService $service)
    {
    }

    public function show(): Response
    {
        return Inertia::render('Auth/ReauthenticateAdmin');
    }

    public function store(ReauthenticateAdminRequest $request): RedirectResponse
    {
        $this->service->confirm($request->user(), $request->string('password')->toString(), $request);

        return redirect()->intended(route('dashboard'));
    }
}
