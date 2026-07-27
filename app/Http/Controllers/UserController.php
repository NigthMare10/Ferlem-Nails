<?php

namespace App\Http\Controllers;

use App\Actions\Notifications\PublishInternalNotificationAction;
use App\Actions\Payroll\ConfigureCompensationProfileAction;
use App\Http\Requests\StoreCompensationProfileRequest;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdatePasswordRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Http\Resources\UserResource;
use App\Models\EmployeeCompensationProfile;
use App\Models\PayrollEvent;
use App\Models\User;
use App\Support\Money;
use App\Support\Permissions;
use Carbon\CarbonImmutable;
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
        $relations = ['roles'];
        if ($request->user()->can(Permissions::PAYROLL_CONFIGURE)) {
            $relations[] = 'compensationProfiles';
        }
        $users = User::query()->with($relations)->when($request->search, fn ($q, $s) => $q->where(fn ($q) => $q->where('name', 'like', "%{$s}%")->orWhere('email', 'like', "%{$s}%")))
            ->when($request->role, fn ($q, $role) => $q->role($role))->when($request->filled('status'), fn ($q) => $q->where('is_active', $request->boolean('status')))->latest()->paginate(10)->withQueryString();

        return Inertia::render('Configuration/Users', ['users' => UserResource::collection($users), 'filters' => $request->only('search', 'role', 'status'), 'roles' => ['owner', 'administrator', 'employee']]);
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        $this->validateRole($request, $request->string('role')->toString());
        $data = $request->validated();
        $role = $data['role'];
        $employment = $this->employmentData($data, true);
        $userData = collect($data)->only(['name', 'email', 'password', 'is_active'])->all();
        DB::transaction(function () use ($request, $userData, $role, $employment) {
            $user = User::create($userData);
            $user->assignRole($role);
            if ($employment) {
                app(ConfigureCompensationProfileAction::class)->execute($request->user(), $user, $employment, true);
            }
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
        abort_if($user->hasRole('employee') && ! $request->user()->can(Permissions::PAYROLL_CONFIGURE), 403);
        $data = $request->validated();
        $role = $data['role'] ?? null;
        $employment = $this->employmentData($data, false, $user);
        $userData = collect($data)->only(['name', 'email'])->all();
        if ($role) {
            $this->validateRole($request, $role);
            $this->preventLastOwnerRoleRemoval($user, $role);
        }
        DB::transaction(function () use ($request, $user, $userData, $role, $employment): void {
            $user->update($userData);
            if ($role) {
                $user->syncRoles([$role]);
            }
            if ($employment && $this->employmentChanged($user, $employment)) {
                app(ConfigureCompensationProfileAction::class)->execute($request->user(), $user, $employment, true);
            }
        });

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

    public function compensation(Request $request, User $user): Response
    {
        $this->allowed($request, Permissions::PAYROLL_CONFIGURE);

        $canViewPayroll = $request->user()->can(Permissions::PAYROLL_VIEW);

        return Inertia::render('Configuration/Compensation', [
            'user' => ['id' => $user->id, 'name' => $user->name, 'is_active' => $user->is_active],
            'profiles' => $canViewPayroll ? EmployeeCompensationProfile::query()->where('user_id', $user->id)->latest('effective_from')->get() : [],
            'events' => $canViewPayroll ? PayrollEvent::query()->where('subject_type', EmployeeCompensationProfile::class)->whereIn('subject_id', EmployeeCompensationProfile::query()->where('user_id', $user->id)->select('id'))->with('performedBy:id,name')->latest('occurred_at')->get()->map(fn (PayrollEvent $event) => ['text' => $this->payrollEventText($event), 'performed_by' => $event->performedBy?->name, 'occurred_at' => $event->occurred_at?->setTimezone('America/Tegucigalpa')->translatedFormat('j \\d\\e F \\d\\e Y, h:i a')])->values() : [],
        ]);
    }

    public function storeCompensation(StoreCompensationProfileRequest $request, User $user, ConfigureCompensationProfileAction $action): RedirectResponse
    {
        $this->guardTarget($request, $user, Permissions::PAYROLL_CONFIGURE);
        $action->execute($request->user(), $user, $request->validated());

        return back()->with('success', 'Perfil de compensación guardado.');
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
        abort_if($role === 'employee' && ! $request->user()->can(Permissions::PAYROLL_CONFIGURE), 403);
        abort_unless($request->user()->can(Permissions::USERS_ASSIGN_ROLE), 403);
    }

    private function preventLastActiveOwnerDeactivation(User $user): void
    {
        if ($user->hasRole('owner') && $user->is_active && User::role('owner')->where('is_active', true)->count() <= 1) {
            abort(422, 'Debe existir al menos un propietario activo.');
        }
    }

    private function payrollEventText(PayrollEvent $event): string
    {
        $values = $event->new_values ?? [];

        return match ($event->event_type) {
            'profile.closed' => 'Vigencia anterior finalizada el '.CarbonImmutable::parse($values['effective_to'], 'America/Tegucigalpa')->translatedFormat('j \\d\\e F \\d\\e Y'),
            'profile.created' => implode(' · ', [
                'Salario: L '.number_format((float) $values['monthly_salary'], 2),
                'Contrato: '.CarbonImmutable::parse($values['contract_start_date'] ?? $values['effective_from'], 'America/Tegucigalpa')->translatedFormat('j \\d\\e F \\d\\e Y').' → '.(($values['contract_end_date'] ?? null) ? CarbonImmutable::parse($values['contract_end_date'], 'America/Tegucigalpa')->translatedFormat('j \\d\\e F \\d\\e Y') : 'Indefinido'),
                'Método habitual: '.match ($values['default_payment_method'] ?? null) {
                    'cash' => 'Efectivo', 'card' => 'Tarjeta', 'transfer' => 'Transferencia', default => 'Sin configurar'
                },
                ($values['auto_generate_payroll_expense'] ?? false) ? 'Generación automática activa' : 'Generación automática inactiva',
            ]),
            default => 'Cambio de perfil salarial',
        };
    }

    private function preventLastOwnerRoleRemoval(User $user, string $role): void
    {
        if ($user->hasRole('owner') && $role !== 'owner' && $user->is_active && User::role('owner')->where('is_active', true)->count() <= 1) {
            abort(422, 'No se puede quitar el rol al último propietario activo.');
        }
    }

    private function employmentData(array $data, bool $creating, ?User $user = null): ?array
    {
        if (($data['role'] ?? null) !== 'employee' && ! ($data['has_employment_profile'] ?? false)) {
            return null;
        }

        $today = CarbonImmutable::now('America/Tegucigalpa')->startOfDay();
        $current = $user?->compensationProfiles()->where('is_active', true)->latest('effective_from')->first();
        $effectiveFrom = $creating
            ? $data['contract_start_date']
            : ($current && $current->effective_from->gte($today) ? $current->effective_from->addDay()->toDateString() : $today->toDateString());

        return [
            'monthly_salary' => $data['monthly_salary'],
            'effective_from' => $effectiveFrom,
            'effective_to' => ($data['is_indefinite'] ?? false) ? null : ($data['contract_end_date'] ?? null),
            'contract_start_date' => $data['contract_start_date'],
            'contract_end_date' => ($data['is_indefinite'] ?? false) ? null : ($data['contract_end_date'] ?? null),
            'is_indefinite' => (bool) $data['is_indefinite'],
            'default_payment_method' => $data['default_payment_method'] ?? null,
            'auto_generate_payroll_expense' => (bool) ($data['auto_generate_payroll_expense'] ?? false),
            'notes' => null,
        ];
    }

    private function employmentChanged(User $user, array $data): bool
    {
        $profile = $user->compensationProfiles()->where('is_active', true)->latest('effective_from')->first();
        if (! $profile) {
            return true;
        }

        return $profile->monthly_salary !== Money::fromCents(Money::toCents((string) $data['monthly_salary']))
            || $profile->contract_start_date?->toDateString() !== $data['contract_start_date']
            || $profile->contract_end_date?->toDateString() !== $data['contract_end_date']
            || $profile->is_indefinite !== $data['is_indefinite']
            || $profile->default_payment_method !== $data['default_payment_method']
            || $profile->auto_generate_payroll_expense !== $data['auto_generate_payroll_expense'];
    }
}
