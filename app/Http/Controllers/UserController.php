<?php

namespace App\Http\Controllers;

use App\Actions\Notifications\PublishInternalNotificationAction;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdatePasswordRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Support\Permissions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function __construct(private PublishInternalNotificationAction $publishNotification) {}

    public function index(Request $request): Response
    {
        $this->allowed($request, Permissions::USERS_VIEW);
        $users = User::query()->with('roles')->when($request->search, fn ($q, $s) => $q->where(fn ($q) => $q->where('name', 'like', "%{$s}%")->orWhere('email', 'like', "%{$s}%")))
            ->when($request->role, fn ($q, $role) => $q->role($role))->when($request->filled('status'), fn ($q) => $q->where('is_active', $request->boolean('status')))->latest()->paginate(10)->withQueryString();

        return Inertia::render('Configuration/Users', ['users' => UserResource::collection($users), 'filters' => $request->only('search', 'role', 'status'), 'roles' => ['owner', 'administrator', 'employee']]);
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        $this->validateRole($request, $request->string('role')->toString());
        $data = $request->validated();
        $role = $data['role'];
        unset($data['role']);
        DB::transaction(function () use ($request, $data, $role) {
            $user = User::create($data);
            $user->assignRole($role);
            $occurredAt = now('UTC');
            $this->publishNotification->execute(
                $request->user(), 'user.created', 'Usuario creado', "Se creó el usuario {$user->name}.",
                '/configuration/users', ['type' => 'user', 'id' => $user->getKey()],
                "user:{$user->getKey()}:created", $occurredAt,
            );
        });

        return back()->with('success', 'Usuario creado correctamente.');
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $this->guardTarget($request, $user);
        $data = $request->validated();
        $role = $data['role'] ?? null;
        unset($data['role']);
        if ($role) {
            $this->validateRole($request, $role);
            $this->preventLastOwnerRoleRemoval($user, $role);
        }
        $user->update($data);
        if ($role) {
            $user->syncRoles([$role]);
        }

        return back()->with('success', 'Usuario actualizado correctamente.');
    }

    public function status(Request $request, User $user): RedirectResponse
    {
        $this->allowed($request, Permissions::USERS_TOGGLE_STATUS);
        $request->validate(['is_active' => ['required', 'boolean']]);
        $this->guardTarget($request, $user);
        abort_if($user->is($request->user()), 422, 'No puedes desactivar tu propia cuenta.');
        if (! $request->boolean('is_active')) {
            $this->preventLastActiveOwnerDeactivation($user);
        }
        DB::transaction(function () use ($request, $user) {
            $active = $request->boolean('is_active');
            $user->update(['is_active' => $active]);
            if (! $user->wasChanged('is_active')) {
                return;
            }
            $occurredAt = now('UTC');
            $state = $active ? 'activated' : 'deactivated';
            $this->publishNotification->execute(
                $request->user(), "user.{$state}", $active ? 'Usuario activado' : 'Usuario desactivado',
                ($active ? 'Se activó' : 'Se desactivó')." el usuario {$user->name}.",
                '/configuration/users', ['type' => 'user', 'id' => $user->getKey()],
                'user:'.$user->getKey().':'.$state.':'.Str::uuid(), $occurredAt,
            );
        });

        return back()->with('success', 'Estado del usuario actualizado.');
    }

    public function password(UpdatePasswordRequest $request, User $user): RedirectResponse
    {
        $this->guardTarget($request, $user, Permissions::USERS_RESET_PASSWORD);
        $user->update(['password' => Hash::make($request->validated('password'))]);

        return back()->with('success', 'Contraseña restablecida correctamente.');
    }

    private function allowed(Request $request, string $permission): void
    {
        abort_unless($request->user()->can($permission), 403);
    }

    private function guardTarget(Request $request, User $target, string $permission = Permissions::USERS_UPDATE): void
    {
        $this->allowed($request, $permission);
        abort_if(! $request->user()->hasRole('owner') && $target->hasRole('owner'), 403);
    }

    private function validateRole(Request $request, string $role): void
    {
        abort_if($role === 'owner' && ! $request->user()->hasRole('owner'), 403);
        abort_unless($request->user()->can(Permissions::USERS_ASSIGN_ROLE), 403);
    }

    private function preventLastActiveOwnerDeactivation(User $user): void
    {
        if ($user->hasRole('owner') && $user->is_active && User::role('owner')->where('is_active', true)->count() <= 1) {
            abort(422, 'Debe existir al menos un propietario activo.');
        }
    }

    private function preventLastOwnerRoleRemoval(User $user, string $role): void
    {
        if ($user->hasRole('owner') && $role !== 'owner' && $user->is_active && User::role('owner')->where('is_active', true)->count() <= 1) {
            abort(422, 'No se puede quitar el rol al último propietario activo.');
        }
    }
}
