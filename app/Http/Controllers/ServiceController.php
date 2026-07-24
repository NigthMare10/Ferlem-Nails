<?php

namespace App\Http\Controllers;

use App\Actions\Notifications\PublishInternalNotificationAction;
use App\Http\Requests\ServiceRequest;
use App\Http\Resources\ServiceResource;
use App\Models\Service;
use App\Support\Permissions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class ServiceController extends Controller
{
    public function __construct(private PublishInternalNotificationAction $publishNotification) {}

    public function index(Request $request): Response
    {
        $this->allowed($request, Permissions::SERVICES_VIEW);
        $services = Service::query()->when($request->search, fn ($q, $s) => $q->where('name', 'like', "%{$s}%"))
            ->when($request->filled('status'), fn ($q) => $q->where('is_active', $request->boolean('status')))->latest()->paginate(10)->withQueryString();

        return Inertia::render('Configuration/Services', ['services' => ServiceResource::collection($services), 'filters' => $request->only('search', 'status')]);
    }

    public function store(ServiceRequest $request): RedirectResponse
    {
        $this->allowed($request, Permissions::SERVICES_CREATE);
        DB::transaction(function () use ($request) {
            $service = Service::create($request->validated());
            $occurredAt = now('UTC');
            $this->publishNotification->execute(
                $request->user(), 'service.created', 'Servicio creado', "Se creó el servicio {$service->name}.",
                '/configuration/services', ['type' => 'service', 'id' => $service->getKey()],
                "service:{$service->getKey()}:created", $occurredAt,
            );
        });

        return back()->with('success', 'Servicio creado correctamente.');
    }

    public function update(ServiceRequest $request, Service $service): RedirectResponse
    {
        $this->allowed($request, Permissions::SERVICES_UPDATE);
        $service->update($request->validated());

        return back()->with('success', 'Servicio actualizado correctamente.');
    }

    public function status(Request $request, Service $service): RedirectResponse
    {
        $this->allowed($request, Permissions::SERVICES_TOGGLE_STATUS);
        $request->validate(['is_active' => ['required', 'boolean']]);
        DB::transaction(function () use ($request, $service) {
            $active = $request->boolean('is_active');
            $service->update(['is_active' => $active]);
            if (! $service->wasChanged('is_active')) {
                return;
            }
            $occurredAt = now('UTC');
            $state = $active ? 'activated' : 'deactivated';
            $this->publishNotification->execute(
                $request->user(), "service.{$state}", $active ? 'Servicio activado' : 'Servicio desactivado',
                ($active ? 'Se activó' : 'Se desactivó')." el servicio {$service->name}.",
                '/configuration/services', ['type' => 'service', 'id' => $service->getKey()],
                'service:'.$service->getKey().':'.$state.':'.Str::uuid(), $occurredAt,
            );
        });

        return back()->with('success', 'Estado del servicio actualizado.');
    }

    public function destroy(Request $request, Service $service): RedirectResponse
    {
        $this->allowed($request, Permissions::SERVICES_DELETE);
        $service->delete();

        return back()->with('success', 'Servicio eliminado correctamente.');
    }

    private function allowed(Request $request, string $permission): void
    {
        abort_unless($request->user()->can($permission), 403);
    }
}
