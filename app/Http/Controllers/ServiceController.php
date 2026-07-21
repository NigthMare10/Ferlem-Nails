<?php

namespace App\Http\Controllers;

use App\Http\Requests\ServiceRequest;
use App\Http\Resources\ServiceResource;
use App\Models\Service;
use App\Support\Permissions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ServiceController extends Controller
{
    public function index(Request $request): Response
    {
        $this->allowed($request, Permissions::SERVICES_VIEW);
        $services = Service::query()->when($request->search, fn ($q, $s) => $q->where('name', 'like', "%{$s}%"))
            ->when($request->filled('status'), fn ($q) => $q->where('is_active', $request->boolean('status')))->latest()->paginate(10)->withQueryString();
        return Inertia::render('Configuration/Services', ['services' => ServiceResource::collection($services), 'filters' => $request->only('search', 'status')]);
    }
    public function store(ServiceRequest $request): RedirectResponse
    {
        $this->allowed($request, Permissions::SERVICES_CREATE); Service::create($request->validated());
        return back()->with('success', 'Servicio creado correctamente.');
    }
    public function update(ServiceRequest $request, Service $service): RedirectResponse
    {
        $this->allowed($request, Permissions::SERVICES_UPDATE); $service->update($request->validated());
        return back()->with('success', 'Servicio actualizado correctamente.');
    }
    public function status(Request $request, Service $service): RedirectResponse
    {
        $this->allowed($request, Permissions::SERVICES_TOGGLE_STATUS); $request->validate(['is_active' => ['required', 'boolean']]);
        $service->update(['is_active' => $request->boolean('is_active')]);
        return back()->with('success', 'Estado del servicio actualizado.');
    }
    public function destroy(Request $request, Service $service): RedirectResponse
    {
        $this->allowed($request, Permissions::SERVICES_DELETE); $service->delete();
        return back()->with('success', 'Servicio eliminado correctamente.');
    }
    private function allowed(Request $request, string $permission): void { abort_unless($request->user()->can($permission), 403); }
}
