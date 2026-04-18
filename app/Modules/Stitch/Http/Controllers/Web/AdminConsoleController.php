<?php

namespace App\Modules\Stitch\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Catalogo\Models\CategoriaServicio;
use App\Modules\Catalogo\Models\HistorialPrecioServicio;
use App\Modules\Catalogo\Models\PrecioServicio;
use App\Modules\Catalogo\Models\Servicio;
use App\Modules\Catalogo\Services\CatalogoService;
use App\Modules\Empleados\Models\Empleado;
use App\Modules\Shared\Support\Money;
use App\Modules\Sucursales\Models\ConfiguracionSucursal;
use App\Modules\Sucursales\Models\Sucursal;
use App\Modules\Sucursales\Support\SucursalContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminConsoleController extends Controller
{
    protected array $employeeImages = [
        'https://lh3.googleusercontent.com/aida-public/AB6AXuDTboIwz6A2v6TLDBuuryNg0h38xLCEb7-4KaH4GOoz9YuIbHPjK-yYa08v89j5i3JcK06v6VlH_VCIGIi7GA-X7L5WzXsnvIazXQDVeJk2jyOVLWSL7smW3Y3fyGCHhd4CpA0q3EC4nVI3MUz8u3xkHAP3VPARK-HlKwUGx_i_FGUE_MOTUrULp1gXzQhzoS2sFIyy26uXKnKZc6RD64HBCIVSiXPAaipDtQYsoZwBDvCeaQPy76lE9hTa2Lx_c9Vgw5QcwXkUQI7w',
        'https://lh3.googleusercontent.com/aida-public/AB6AXuAzdSsrvxEwCMTn_GfbDS4qcOyjFcTn95iv3yVsS12QtTOwariuTM5vxdVd42BKlFo8uQIVIXFksD9YbhKzU-r0eglHT_c5azbOhT0-rmMxRJ6BBGU8B1qKD6Qow-IEQPAZ4Jv7NB6VndGkdHI2Ts1C4TeNaNlbnMMsPZxJQQfHFlMqCR1GAlQeosZRpJuBIup4rjUoUo6cob4wIzAn6j7C9agMKY1nlzqv9d1pAOe-Xq7H1o6kDeI3jZbbt3uyO1Rt2BcbstpiAbI_',
        'https://lh3.googleusercontent.com/aida-public/AB6AXuBdt24FkfGKH_WfLiRhl8KzFkyULUCMvxtlLI4AGUtIbzw2c027dzNU9CO0FGYR31Culhr2-jruZWgHdc9Xoxx8NMpKk1eZfitYjCASHIRgl5rRQ_mlKiARfG2t_NAIHz3gBU1690_sLQCc8DeMTBbh3n3743djNn9cRHh3cgUlggOLZudCG4gbCs72bnIVpkXT1K864-c2dOO2qiy2ZCI03BeP9t-58Ef3q1cpdIKCPsUZvNDOzrZ2u0nYyiVL3B61wohsoHbVIAIp',
        'https://lh3.googleusercontent.com/aida-public/AB6AXuBV1dZuoEn1LG-OfTHLlSJBIs17D0_m8fTEoxSEmC_HYrTRyxV3uK1jYpVQwdiRAKd2ydNdiDubqzNH4C0hjH2mtHW1ydxrMOjxb-WteyiY2sMgxZBlyumE5icWJrIY81tOeN5bxvlGHHB3dqGrThRBiJFDMfBA246Ke1_ZbYx6avTldF-GegZhXArnN05xnL9g4RlfKf5yFpqbefRfjJjsbHPUrNeLL3DnV3tji9wozO114dpE2unPOtbTMj5NB_cFLZ9g9FlL2iTN',
    ];

    protected array $serviceImages = [
        'https://lh3.googleusercontent.com/aida-public/AB6AXuBBcEk5JlBHb3oian9vEZb_uPtw-ZApttCrqZVMRhGAoy0I_xxCBGZWKH_CExCL4SOyx_OiR9cYdOoWHYxYtpr_RpBDvTJVg4ZLKhxQR193aVdskHhF6CpZReVjHGJ7SXzhy75fbKxcel86rLRDsbJBo0ddThDp2szsj9M6TCnOlOlyDje93HsVqbi2kNmbIj_HSZPhRE0s7gOwO9yDTvps6V_FOyddYiVFdiV_zLehd-YRqKSvcO6XvoaDGhFLFvGImKFBsCfCwdPT',
        'https://lh3.googleusercontent.com/aida-public/AB6AXuD2MHIeL-Zjgz0befkK0VNE3ENXkhJ8VIhJjZtqmqeknVAq9WastoJ5znEElVtNHHJ8nwles5b3u8FAyIFKV-Giu_nzCZLpqjUbAplNP068-BmPEcB_HY7KaO6nO6DZp20yDrqPXHiDzRaMKWW8R383X4dB1g7h7iz8ULXzvZvN6ISxEXoi-ixaXbVWDen_SA4rZq3re_IVDYM8wocZPNfhuF3BmQ51XiUyL5NK1wTD4kcG6MKgZIB5GBp8iUUuOi4LQS-TqHNug3qC',
        'https://lh3.googleusercontent.com/aida-public/AB6AXuAEigsJ6As72uFhFqNVFQ26uiciAaUXElaDtUchSM_nH2hNbxUw2eVDAflyDt1aqNSS_BjK2L44IMPu354Cg2sMIWYVOQuwyAT7tC8en9u3vrZZ3Tw0bTjqAE16TeXBuHKfoG2n50XzdWtDwcb9pZs5Hb9E9gej379XJlbJrbnkjXNDk9PLkregOiNx0dGR7dPQco7CTAR81zQAYY1H_6n63DNTu0Whs_xdjBLzCRp3OQ8JWEv6JPGm_kSNTi_Qujnm_2XrhMTL3JPF',
    ];

    public function __construct(
        protected SucursalContext $branchContext,
        protected CatalogoService $catalogoService,
    ) {
    }

    public function reportes(): Response
    {
        $employees = $this->activeEmployees()->values();
        $staffPerformance = $employees->take(3)->values()->map(function (Empleado $employee, int $index): array {
            $profile = $this->buildEmployeeProfile($employee, $index);

            return [
                'employeePublicId' => $employee->public_id,
                'name' => $profile['name'],
                'department' => $profile['role'],
                'revenue' => $profile['metrics']['totalRevenue'],
                'image' => $profile['image'],
            ];
        })->all();

        return Inertia::render('Admin/ReportsAnalytics', [
            'title' => 'Reportes de Ventas Analytics',
            'calendarOptions' => [
                ['label' => 'Abril 2026', 'key' => 'abril-2026'],
                ['label' => 'Mayo 2026', 'key' => 'mayo-2026'],
                ['label' => 'Junio 2026', 'key' => 'junio-2026'],
            ],
            'datasets' => [
                'abril-2026' => [
                    'summary' => [
                        'ingresos' => 'L 42,850',
                        'variacionIngresos' => '+12.5% vs mes anterior',
                        'ticketPromedio' => 'L 148',
                        'variacionTicket' => 'Rendimiento estable',
                        'citas' => '312',
                        'variacionCitas' => '+14 esta semana',
                        'categoriaPrincipal' => 'Pestañas',
                        'variacionCategoria' => '58% del total de reservas',
                    ],
                    'trend' => [78, 58, 30, 40],
                    'previousTrend' => [90, 80, 60, 70],
                    'servicePopularity' => [
                        ['name' => 'Manicura Rusa Prestige', 'value' => 32],
                        ['name' => 'Extensiones de Pestañas Volumen', 'value' => 28],
                        ['name' => 'Cejas Ombré Powder', 'value' => 22],
                        ['name' => 'Balayage Signature', 'value' => 18],
                    ],
                    'staffPerformance' => $staffPerformance,
                    'retention' => [
                        'value' => '74%',
                        'delta' => '+3%',
                        'note' => 'Tu base de clientas recurrentes sigue creciendo. Considera premiar al 5% con mayor gasto mensual.',
                    ],
                    'insight' => [
                        'title' => 'Pico de Demanda de Fin de Semana',
                        'body' => 'La analítica indica un aumento del 42% en servicios de alto ticket entre viernes por la tarde y domingo por la mañana. Conviene abrir dos estaciones adicionales de pestañas en esos intervalos.',
                    ],
                ],
                'mayo-2026' => [
                    'summary' => [
                        'ingresos' => 'L 46,120',
                        'variacionIngresos' => '+7.6% vs abril',
                        'ticketPromedio' => 'L 154',
                        'variacionTicket' => 'Sube con pestañas premium',
                        'citas' => '328',
                        'variacionCitas' => '+9 esta semana',
                        'categoriaPrincipal' => 'Uñas',
                        'variacionCategoria' => '51% del total de reservas',
                    ],
                    'trend' => [82, 42, 34, 22],
                    'previousTrend' => [88, 70, 58, 40],
                    'servicePopularity' => [
                        ['name' => 'Pestañas Clásicas', 'value' => 34],
                        ['name' => 'Manicura Rusa Prestige', 'value' => 27],
                        ['name' => 'Hydrafacial', 'value' => 21],
                        ['name' => 'Lifting de Pestañas', 'value' => 18],
                    ],
                    'staffPerformance' => $staffPerformance,
                    'retention' => [
                        'value' => '77%',
                        'delta' => '+3%',
                        'note' => 'Las clientas que reservaron bundles de mantenimiento regresaron con más frecuencia. Mantener esa estrategia puede elevar el ticket recurrente.',
                    ],
                    'insight' => [
                        'title' => 'Aumento de Servicios Recurrentes',
                        'body' => 'Los packs de mantenimiento tuvieron mejor conversión durante mayo. Aprovecha para reforzar ofertas de retoque y reservas automáticas.',
                    ],
                ],
                'junio-2026' => [
                    'summary' => [
                        'ingresos' => 'L 39,980',
                        'variacionIngresos' => '-4.2% vs mayo',
                        'ticketPromedio' => 'L 139',
                        'variacionTicket' => 'Afectado por descuentos estacionales',
                        'citas' => '289',
                        'variacionCitas' => '-11 esta semana',
                        'categoriaPrincipal' => 'Cejas',
                        'variacionCategoria' => '45% del total de reservas',
                    ],
                    'trend' => [72, 48, 62, 44],
                    'previousTrend' => [88, 66, 74, 58],
                    'servicePopularity' => [
                        ['name' => 'Retoque Volumen Ruso', 'value' => 30],
                        ['name' => 'Retiro Gel', 'value' => 26],
                        ['name' => 'Set Híbrido Completo', 'value' => 24],
                        ['name' => 'Tratamiento Express', 'value' => 20],
                    ],
                    'staffPerformance' => $staffPerformance,
                    'retention' => [
                        'value' => '71%',
                        'delta' => '-6%',
                        'note' => 'La tasa de retención cayó por promociones externas y menos reservas recurrentes. Conviene reforzar el seguimiento posterior al servicio.',
                    ],
                    'insight' => [
                        'title' => 'Alerta de Retención',
                        'body' => 'Junio presenta menor retorno en clientas nuevas. Revisa los seguimientos post-servicio y los beneficios de rebooking en recepción.',
                    ],
                ],
            ],
        ]);
    }

    public function gestionEmpleadosAdmin(): Response
    {
        $employees = $this->activeEmployees()->values();

        return Inertia::render('Admin/EmployeeManagement', [
            'title' => 'Gestión de Empleados Admin',
            'employees' => $employees->map(fn (Empleado $employee, int $index) => $this->mapEmployeeCard($employee, $index))->all(),
            'specializationOptions' => [
                'Técnica de Pestañas',
                'Artista de Uñas',
                'Esteticista',
                'Líder de Salón',
            ],
            'summaryCards' => [
                ['value' => (string) $employees->count(), 'label' => 'Especialistas Activos'],
                ['value' => $employees->contains(fn (Empleado $employee) => $employee->usuario?->hasRole('super_admin')) ? '1' : '0', 'label' => 'Perfiles Admin'],
                ['value' => (string) User::query()->where('is_active', true)->whereHas('sucursales', fn ($query) => $query->whereKey($this->activeBranch()->id))->count(), 'label' => 'Perfiles de Login'],
            ],
        ]);
    }

    public function storeEmployee(Request $request): RedirectResponse
    {
        $branch = $this->activeBranch();

        $payload = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'role' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:180', 'unique:users,email'],
            'startDate' => ['required', 'date'],
            'password' => ['required', 'string', 'min:4', 'max:120'],
        ]);

        DB::transaction(function () use ($payload, $branch): void {
            $user = User::create([
                'name' => $payload['name'],
                'email' => Str::lower($payload['email']),
                'password' => Hash::make($payload['password']),
                'is_active' => true,
                'email_verified_at' => now(),
            ]);

            $user->syncRoles([$this->resolveOperationalRole($payload['role'])]);
            $user->sucursales()->syncWithoutDetaching([$branch->id => ['is_default' => true]]);

            $employee = Empleado::create([
                'user_id' => $user->id,
                'name' => $payload['name'],
                'email' => Str::lower($payload['email']),
                'role_title' => $payload['role'],
                'hire_date' => $payload['startDate'],
                'is_active' => true,
            ]);

            $employee->sucursales()->syncWithoutDetaching([$branch->id => ['is_primary' => true, 'role_title' => $payload['role']]]);
        });

        return back()->with('success', 'Empleado creado y habilitado para iniciar sesión.');
    }

    public function updateEmployee(Request $request, Empleado $empleado): RedirectResponse
    {
        $employee = $this->employeeInActiveBranch($empleado);

        $payload = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'role' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:180', Rule::unique('users', 'email')->ignore($employee->usuario?->id)],
            'startDate' => ['required', 'date'],
            'password' => ['nullable', 'string', 'min:4', 'max:120'],
        ]);

        DB::transaction(function () use ($employee, $payload): void {
            $employee->update([
                'name' => $payload['name'],
                'email' => Str::lower($payload['email']),
                'role_title' => $payload['role'],
                'hire_date' => $payload['startDate'],
            ]);

            if ($employee->usuario) {
                $update = [
                    'name' => $payload['name'],
                    'email' => Str::lower($payload['email']),
                ];

                if (! empty($payload['password'])) {
                    $update['password'] = Hash::make($payload['password']);
                }

                $employee->usuario->update($update);

                if (! $employee->usuario->hasRole('super_admin')) {
                    $employee->usuario->syncRoles([$this->resolveOperationalRole($payload['role'])]);
                }
            }
        });

        return back()->with('success', 'Empleado actualizado correctamente.');
    }

    public function destroyEmployee(Empleado $empleado): RedirectResponse
    {
        $employee = $this->employeeInActiveBranch($empleado);

        if ($employee->usuario?->hasRole('super_admin')) {
            return back()->with('error', 'No puedes eliminar al administrador principal desde esta pantalla.');
        }

        DB::transaction(function () use ($employee): void {
            if ($employee->usuario) {
                $employee->usuario->update(['is_active' => false]);
            }

            $employee->update(['is_active' => false]);
            $employee->delete();
        });

        return back()->with('success', 'Empleado eliminado del acceso operativo.');
    }

    public function ajustePreciosAdmin(): Response
    {
        $branch = $this->activeBranch();
        $services = $this->catalogoService->listarActivosParaSucursal($branch)->values();
        $averageAmount = $services->isEmpty()
            ? 0
            : (int) round($services->reduce(fn (int $carry, Servicio $service) => $carry + $this->catalogoService->precioVigente($service, $branch), 0) / $services->count());

        return Inertia::render('Admin/PriceSettings', [
            'title' => 'Ajuste de Precios Admin',
            'services' => $services->map(fn (Servicio $service, int $index) => $this->mapServiceCard($service, $index, $branch))->all(),
            'categoryOptions' => CategoriaServicio::query()->where('is_active', true)->orderBy('sort_order')->orderBy('name')->get()->map(fn (CategoriaServicio $category) => [
                'publicId' => $category->public_id,
                'name' => $category->name,
            ])->values()->all(),
            'summaryCards' => [
                ['label' => 'Total de Servicios Gestionados', 'value' => (string) $services->count()],
                ['label' => 'Ticket Promedio', 'value' => Money::format($averageAmount)],
            ],
            'settingsUrl' => '/configuracion-admin',
        ]);
    }

    public function storeService(Request $request): RedirectResponse
    {
        $branch = $this->activeBranch();
        $payload = $this->validateServicePayload($request);
        $category = $this->resolveCategory($payload['categoryPublicId'] ?? null);

        DB::transaction(function () use ($payload, $category, $branch, $request): void {
            $service = Servicio::create([
                'categoria_servicio_id' => $category->id,
                'name' => $payload['name'],
                'slug' => $this->uniqueServiceSlug($payload['name']),
                'description' => $payload['description'],
                'duration_minutes' => (int) $payload['durationMinutes'],
                'base_price_amount' => $this->toCents($payload['price']),
                'requires_employee' => true,
                'allow_manual_price' => false,
                'is_active' => true,
            ]);

            PrecioServicio::create([
                'servicio_id' => $service->id,
                'sucursal_id' => $branch->id,
                'amount' => $this->toCents($payload['price']),
                'effective_from' => now()->toDateString(),
                'is_active' => true,
            ]);

            HistorialPrecioServicio::create([
                'servicio_id' => $service->id,
                'sucursal_id' => $branch->id,
                'changed_by_user_id' => $request->user()?->id,
                'previous_amount' => 0,
                'new_amount' => $this->toCents($payload['price']),
                'reason' => 'Alta de servicio desde ajuste de precios admin',
                'changed_at' => now(),
            ]);
        });

        return back()->with('success', 'Servicio agregado correctamente.');
    }

    public function updateService(Request $request, Servicio $servicio): RedirectResponse
    {
        $branch = $this->activeBranch();
        $service = Servicio::query()->whereKey($servicio->id)->where('is_active', true)->firstOrFail();
        $payload = $this->validateServicePayload($request);
        $category = $this->resolveCategory($payload['categoryPublicId'] ?? null);
        $newAmount = $this->toCents($payload['price']);
        $previousAmount = $this->catalogoService->precioVigente($service, $branch);

        DB::transaction(function () use ($service, $payload, $category, $branch, $request, $newAmount, $previousAmount): void {
            $service->update([
                'categoria_servicio_id' => $category->id,
                'name' => $payload['name'],
                'description' => $payload['description'],
                'duration_minutes' => (int) $payload['durationMinutes'],
                'base_price_amount' => $newAmount,
            ]);

            PrecioServicio::updateOrCreate(
                [
                    'servicio_id' => $service->id,
                    'sucursal_id' => $branch->id,
                    'effective_from' => now()->toDateString(),
                ],
                [
                    'amount' => $newAmount,
                    'effective_to' => null,
                    'is_active' => true,
                ],
            );

            if ($previousAmount !== $newAmount) {
                HistorialPrecioServicio::create([
                    'servicio_id' => $service->id,
                    'sucursal_id' => $branch->id,
                    'changed_by_user_id' => $request->user()?->id,
                    'previous_amount' => $previousAmount,
                    'new_amount' => $newAmount,
                    'reason' => 'Actualización desde ajuste de precios admin',
                    'changed_at' => now(),
                ]);
            }
        });

        return back()->with('success', 'Servicio actualizado correctamente.');
    }

    public function configuracionAdmin(): Response
    {
        $branch = $this->activeBranch()->loadMissing('configuracion');
        $config = $branch->configuracion;

        return Inertia::render('Admin/AdminSettings', [
            'title' => 'Configuración Admin',
            'branch' => [
                'name' => $branch->name,
                'code' => $branch->code,
                'currencyCode' => $config?->currency_code ?? 'HNL',
                'currencySymbol' => $config?->currency_symbol ?? 'L',
                'taxName' => $config?->impuesto_nombre ?? 'ISV',
                'taxRate' => number_format((float) ($config?->impuesto_porcentaje ?? 15), 2, '.', ''),
                'allowManualPrice' => (bool) ($config?->permitir_precio_manual ?? false),
                'reauthWindowMinutes' => (int) ($config?->ventana_reautenticacion_minutos ?? 15),
            ],
        ]);
    }

    public function updateConfiguracionAdmin(Request $request): RedirectResponse
    {
        $branch = $this->activeBranch();

        $payload = $request->validate([
            'taxName' => ['required', 'string', 'max:80'],
            'taxRate' => ['required', 'numeric', 'min:0', 'max:100'],
            'allowManualPrice' => ['nullable', 'boolean'],
            'reauthWindowMinutes' => ['required', 'integer', 'min:5', 'max:120'],
        ]);

        ConfiguracionSucursal::updateOrCreate(
            ['sucursal_id' => $branch->id],
            [
                'currency_code' => 'HNL',
                'currency_symbol' => 'L',
                'impuesto_nombre' => $payload['taxName'],
                'impuesto_porcentaje' => $payload['taxRate'],
                'permitir_precio_manual' => (bool) ($payload['allowManualPrice'] ?? false),
                'ventana_reautenticacion_minutos' => (int) $payload['reauthWindowMinutes'],
            ],
        );

        return back()->with('success', 'Configuración actualizada.');
    }

    public function rendimientoEmpleado(?Empleado $empleado = null): Response
    {
        $employee = $this->resolveEmployeeOrFallback($empleado);
        $profile = $this->buildEmployeeProfile($employee, $this->employeeIndex($employee));

        return Inertia::render('Admin/EmployeePerformance', [
            'title' => 'Rendimiento por Empleado',
            'employee' => $profile,
        ]);
    }

    public function gananciasEmpleado(Empleado $empleado): Response
    {
        $employee = $this->employeeInActiveBranch($empleado);
        $profile = $this->buildEmployeeProfile($employee, $this->employeeIndex($employee));

        return Inertia::render('Admin/EmployeeEarnings', [
            'title' => 'Ganancias por Empleado',
            'employee' => $profile,
        ]);
    }

    public function historialEmpleado(Empleado $empleado): Response
    {
        $employee = $this->employeeInActiveBranch($empleado);
        $profile = $this->buildEmployeeProfile($employee, $this->employeeIndex($employee));

        return Inertia::render('Admin/EmployeeHistory', [
            'title' => 'Historial Completo por Empleado',
            'employee' => $profile,
        ]);
    }

    public function exportarEmpleado(Empleado $empleado): StreamedResponse
    {
        $employee = $this->employeeInActiveBranch($empleado);
        $profile = $this->buildEmployeeProfile($employee, $this->employeeIndex($employee));
        $filename = 'reporte-'.$employee->public_id.'.csv';

        return response()->streamDownload(function () use ($profile): void {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, ['Empleado', $profile['name']]);
            fputcsv($handle, ['Rol', $profile['role']]);
            fputcsv($handle, ['Ingresos totales', $profile['metrics']['totalRevenue']]);
            fputcsv($handle, ['Tiempo promedio', $profile['metrics']['serviceTime']]);
            fputcsv($handle, ['Calificación', $profile['metrics']['rating']]);
            fputcsv($handle, []);
            fputcsv($handle, ['Fecha', 'Servicio', 'Cliente', 'Estado', 'Monto']);

            foreach ($profile['appointments'] as $appointment) {
                fputcsv($handle, [
                    $appointment['date'],
                    $appointment['service'],
                    $appointment['client'],
                    $appointment['status'],
                    $appointment['revenue'],
                ]);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    protected function validateServicePayload(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'description' => ['required', 'string', 'max:500'],
            'durationMinutes' => ['required', 'integer', 'min:5', 'max:480'],
            'price' => ['required', 'numeric', 'min:0.01', 'max:99999.99'],
            'categoryPublicId' => ['nullable', 'string', 'max:26'],
        ]);
    }

    protected function activeBranch(): Sucursal
    {
        return $this->branchContext->required();
    }

    protected function activeEmployees()
    {
        return Empleado::query()
            ->with('usuario')
            ->where('is_active', true)
            ->whereHas('sucursales', fn ($query) => $query->where('sucursal_id', $this->activeBranch()->id))
            ->orderBy('name')
            ->get();
    }

    protected function employeeInActiveBranch(Empleado $empleado): Empleado
    {
        return Empleado::query()
            ->with('usuario')
            ->whereKey($empleado->id)
            ->whereHas('sucursales', fn ($query) => $query->where('sucursal_id', $this->activeBranch()->id))
            ->firstOrFail();
    }

    protected function resolveEmployeeOrFallback(?Empleado $empleado): Empleado
    {
        if ($empleado) {
            return $this->employeeInActiveBranch($empleado);
        }

        return $this->activeEmployees()->firstOrFail();
    }

    protected function resolveOperationalRole(string $specialization): string
    {
        return Str::contains(Str::lower(Str::ascii($specialization)), 'lider') ? 'recepcionista' : 'tecnica';
    }

    protected function employeeIndex(Empleado $employee): int
    {
        $employees = $this->activeEmployees()->values();
        $index = $employees->search(fn (Empleado $candidate) => $candidate->id === $employee->id);

        return $index === false ? 0 : (int) $index;
    }

    protected function mapEmployeeCard(Empleado $employee, int $index): array
    {
        return [
            'id' => $employee->public_id,
            'name' => $employee->name,
            'role' => $employee->role_title ?: 'Perfil operativo',
            'status' => $employee->is_active ? 'Activo' : 'En pausa',
            'statusVariant' => $employee->is_active ? 'active' : 'paused',
            'image' => $this->employeeImages[$index % count($this->employeeImages)],
            'email' => $employee->email ?: $employee->usuario?->email ?: '',
            'startDate' => $employee->hire_date?->format('Y-m-d') ?? now()->toDateString(),
            'isProtected' => (bool) $employee->usuario?->hasRole('super_admin'),
        ];
    }

    protected function mapServiceCard(Servicio $service, int $index, Sucursal $branch): array
    {
        return [
            'id' => $service->public_id,
            'name' => $service->name,
            'description' => $service->description ?: 'Servicio activo del catálogo operativo.',
            'durationMinutes' => $service->duration_minutes,
            'durationLabel' => $service->duration_minutes.' min',
            'price' => Money::format($this->catalogoService->precioVigente($service, $branch)),
            'priceAmount' => number_format($this->catalogoService->precioVigente($service, $branch) / 100, 2, '.', ''),
            'image' => $this->serviceImages[$index % count($this->serviceImages)],
            'categoryPublicId' => $service->categoria?->public_id,
            'categoryName' => $service->categoria?->name,
        ];
    }

    protected function resolveCategory(?string $categoryPublicId): CategoriaServicio
    {
        if ($categoryPublicId) {
            return CategoriaServicio::query()->where('public_id', $categoryPublicId)->firstOrFail();
        }

        return CategoriaServicio::query()->where('is_active', true)->orderBy('sort_order')->orderBy('name')->firstOrFail();
    }

    protected function toCents(string|float|int $value): int
    {
        return (int) round(((float) $value) * 100);
    }

    protected function uniqueServiceSlug(string $name): string
    {
        $base = Str::slug(Str::lower($name));
        $slug = $base;
        $counter = 2;

        while (Servicio::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$counter;
            $counter++;
        }

        return $slug;
    }

    protected function buildEmployeeProfile(Empleado $employee, int $index): array
    {
        $metrics = [
            ['revenue' => 1482000, 'delta' => '+12%', 'time' => '52 min', 'rating' => '4.98', 'appointments' => '124', 'monthNote' => 'este mes', 'level' => 'V', 'since' => 'Marzo 2021'],
            ['revenue' => 1264000, 'delta' => '+9%', 'time' => '48 min', 'rating' => '4.94', 'appointments' => '109', 'monthNote' => 'este mes', 'level' => 'IV', 'since' => 'Julio 2022'],
            ['revenue' => 1123000, 'delta' => '+7%', 'time' => '55 min', 'rating' => '4.90', 'appointments' => '97', 'monthNote' => 'este mes', 'level' => 'III', 'since' => 'Enero 2023'],
            ['revenue' => 1389000, 'delta' => '+11%', 'time' => '50 min', 'rating' => '4.96', 'appointments' => '118', 'monthNote' => 'este mes', 'level' => 'IV', 'since' => 'Septiembre 2021'],
        ][$index % 4];

        $services = [
            ['service' => 'Retoque Volumen Ruso', 'share' => 45],
            ['service' => 'Set Híbrido Completo', 'share' => 30],
            ['service' => 'Lash Lift y Tinte', 'share' => 15],
        ];

        $appointments = collect([
            ['date' => '12 Oct, 14:00', 'service' => 'Retoque Volumen Ruso', 'client' => 'Sienna Miller', 'status' => 'Confirmada', 'revenue' => 'L 145.00'],
            ['date' => '12 Oct, 11:30', 'service' => 'Lash Lift y Tinte', 'client' => 'Grace Chen', 'status' => 'Confirmada', 'revenue' => 'L 95.00'],
            ['date' => '11 Oct, 16:45', 'service' => 'Set Híbrido Completo', 'client' => 'Eleanor Rigby', 'status' => 'Confirmada', 'revenue' => 'L 210.00'],
            ['date' => '11 Oct, 09:00', 'service' => 'Retoque Clásico', 'client' => 'Marcus Thorne', 'status' => 'Confirmada', 'revenue' => 'L 85.00'],
        ]);

        $monthlyRevenue = collect([
            ['label' => 'Sem 1', 'value' => 680000],
            ['label' => 'Sem 2', 'value' => 940000],
            ['label' => 'Sem 3', 'value' => 1260000],
            ['label' => 'Sem 4', 'value' => 1020000],
        ]);

        return [
            'publicId' => $employee->public_id,
            'name' => $employee->name,
            'role' => $employee->role_title ?: 'Perfil operativo',
            'image' => $this->employeeImages[$index % count($this->employeeImages)],
            'level' => $metrics['level'],
            'since' => $metrics['since'],
            'ratingLabel' => '4.9 / 5.0',
            'overviewUrl' => '/reportes-de-ventas-analytics',
            'performanceUrl' => '/rendimiento-por-empleado/'.$employee->public_id,
            'earningsUrl' => '/rendimiento-por-empleado/'.$employee->public_id.'/ganancias',
            'historyUrl' => '/rendimiento-por-empleado/'.$employee->public_id.'/historial-completo',
            'exportUrl' => '/rendimiento-por-empleado/'.$employee->public_id.'/exportar',
            'teamUrl' => '/gestion-de-empleados-admin',
            'dashboardUrl' => '/reportes-de-ventas-analytics',
            'metrics' => [
                'totalRevenue' => Money::format($metrics['revenue']),
                'revenueDelta' => $metrics['delta'],
                'serviceTime' => $metrics['time'],
                'rating' => $metrics['rating'],
                'appointments' => $metrics['appointments'],
                'appointmentsNote' => $metrics['monthNote'],
            ],
            'chart' => $monthlyRevenue->map(fn (array $entry) => [
                'label' => $entry['label'],
                'value' => Money::format($entry['value']),
                'height' => (int) max(24, round(($entry['value'] / 1260000) * 100)),
            ])->all(),
            'specialties' => $services,
            'appointments' => $appointments->all(),
            'history' => $appointments->map(fn (array $appointment, int $itemIndex) => [
                ...$appointment,
                'folio' => 'FNL-EMP-00'.($itemIndex + 1),
                'paymentMethod' => $itemIndex % 2 === 0 ? 'Tarjeta' : 'Efectivo',
            ])->all(),
            'earningsBreakdown' => [
                ['label' => 'Servicios premium', 'value' => 'L 6,850.00'],
                ['label' => 'Mantenimientos', 'value' => 'L 4,120.00'],
                ['label' => 'Complementos', 'value' => 'L 3,850.00'],
            ],
            'insight' => 'La precisión del especialista mantiene una conversión fuerte en servicios de alto ticket y una retención estable durante el trimestre.',
        ];
    }
}
