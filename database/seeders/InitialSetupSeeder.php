<?php

namespace Database\Seeders;

use App\Models\User;
use App\Modules\Catalogo\Models\CategoriaServicio;
use App\Modules\Catalogo\Models\PrecioServicio;
use App\Modules\Catalogo\Models\Servicio;
use App\Modules\Empleados\Models\Empleado;
use App\Modules\Facturacion\Models\SecuenciaDocumento;
use App\Modules\Sucursales\Models\ConfiguracionSucursal;
use App\Modules\Sucursales\Models\Sucursal;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class InitialSetupSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            'clientes.ver',
            'clientes.crear',
            'clientes.editar',
            'catalogo.ver',
            'catalogo.gestionar',
            'catalogo.cambiar_precio',
            'agenda.ver',
            'agenda.crear',
            'agenda.editar',
            'agenda.cancelar',
            'pos.usar',
            'pos.aplicar_descuento_extraordinario',
            'pos.precio_manual',
            'pagos.registrar',
            'facturas.ver',
            'facturas.emitir',
            'facturas.anular',
            'facturas.exportar',
            'caja.ver',
            'caja.abrir',
            'caja.cerrar',
            'caja.cerrar_ajena',
            'caja.reabrir',
            'empleados.ver',
            'empleados.gestionar',
            'reportes.ver_sucursal',
            'reportes.ver_global',
            'auditoria.ver',
            'configuracion.ver',
            'configuracion.gestionar',
            'roles.gestionar',
            'sucursales.seleccionar',
            'sucursales.ver_global',
        ];

        foreach ($permissions as $permissionName) {
            Permission::findOrCreate($permissionName, 'web');
        }

        $roles = [
            'super_admin' => $permissions,
            'admin_negocio' => $permissions,
            'gerente_sucursal' => [
                'clientes.ver', 'clientes.crear', 'clientes.editar',
                'catalogo.ver', 'catalogo.gestionar', 'catalogo.cambiar_precio',
                'agenda.ver', 'agenda.crear', 'agenda.editar', 'agenda.cancelar',
                'pos.usar', 'pos.aplicar_descuento_extraordinario',
                'pagos.registrar',
                'facturas.ver', 'facturas.emitir', 'facturas.exportar',
                'caja.ver', 'caja.abrir', 'caja.cerrar', 'caja.cerrar_ajena', 'caja.reabrir',
                'empleados.ver', 'empleados.gestionar',
                'reportes.ver_sucursal',
                'auditoria.ver',
                'configuracion.ver',
                'sucursales.seleccionar',
            ],
            'cajero' => [
                'pos.usar',
                'pagos.registrar',
                'facturas.emitir',
                'sucursales.seleccionar',
            ],
            'recepcionista' => [
                'pos.usar',
                'pagos.registrar',
                'facturas.emitir',
                'sucursales.seleccionar',
            ],
            'tecnica' => [
                'pos.usar',
                'pagos.registrar',
                'facturas.emitir',
                'sucursales.seleccionar',
            ],
            'auditor' => [
                'facturas.ver',
                'caja.ver',
                'reportes.ver_sucursal',
                'reportes.ver_global',
                'auditoria.ver',
                'sucursales.seleccionar',
                'sucursales.ver_global',
            ],
            // Compatibilidad temporal con nombres previos.
            'administrador' => $permissions,
            'gerencia' => [
                'clientes.ver', 'clientes.crear', 'clientes.editar',
                'catalogo.ver', 'catalogo.gestionar', 'catalogo.cambiar_precio',
                'agenda.ver', 'agenda.crear', 'agenda.editar', 'agenda.cancelar',
                'pos.usar', 'pos.aplicar_descuento_extraordinario',
                'pagos.registrar',
                'facturas.ver', 'facturas.emitir', 'facturas.exportar',
                'caja.ver', 'caja.abrir', 'caja.cerrar', 'caja.cerrar_ajena', 'caja.reabrir',
                'empleados.ver', 'empleados.gestionar',
                'reportes.ver_sucursal',
                'auditoria.ver',
                'configuracion.ver',
                'sucursales.seleccionar',
            ],
        ];

        foreach ($roles as $roleName => $rolePermissions) {
            $role = Role::findOrCreate($roleName, 'web');
            $role->syncPermissions($rolePermissions);
        }

        $branch = Sucursal::firstOrCreate(
            ['code' => 'CM-01'],
            [
                'name' => 'Casa Matriz',
                'razon_social' => 'FERLEM NAILS',
                'timezone' => 'America/Tegucigalpa',
                'currency_code' => 'HNL',
                'is_active' => true,
            ],
        );

        ConfiguracionSucursal::updateOrCreate(
            ['sucursal_id' => $branch->id],
            [
                'currency_code' => 'HNL',
                'currency_symbol' => 'L',
                'impuesto_nombre' => 'ISV',
                'impuesto_porcentaje' => 15.00,
                'ventana_reautenticacion_minutos' => 15,
                'descuento_sin_reautenticacion_porcentaje' => 10,
                'permitir_precio_manual' => false,
            ],
        );

        SecuenciaDocumento::updateOrCreate(
            ['sucursal_id' => $branch->id, 'document_type' => 'factura'],
            ['prefix' => 'FNL-CM', 'padding' => 8, 'current_number' => 0, 'is_active' => true],
        );

        $adminPassword = env('SEED_ADMIN_PASSWORD');

        if (! $adminPassword) {
            $adminPassword = Str::password(16);
            $this->command?->warn('Contrasena temporal del administrador de desarrollo: '.$adminPassword);
        }

        $admin = User::updateOrCreate(
            ['email' => env('SEED_ADMIN_EMAIL', 'admin@ferlemnails.local')],
            [
                'name' => env('SEED_ADMIN_NAME', 'Administrador FERLEM'),
                'phone' => '+50400000000',
                'password' => Hash::make($adminPassword),
                'email_verified_at' => now(),
                'is_active' => true,
            ],
        );

        $admin->syncRoles(['super_admin']);
        $admin->sucursales()->syncWithoutDetaching([$branch->id => ['is_default' => true]]);

        Empleado::updateOrCreate(
            ['user_id' => $admin->id],
            [
                'name' => 'Administracion General',
                'email' => $admin->email,
                'phone' => $admin->phone,
                'role_title' => 'Superadministracion',
                'is_active' => true,
            ],
        );

        $categories = collect([
            ['name' => 'Unas', 'slug' => 'unas'],
            ['name' => 'Pestanas', 'slug' => 'pestanas'],
        ])->map(fn (array $category) => CategoriaServicio::firstOrCreate(['slug' => $category['slug']], $category));

        $services = [
            ['categoria' => 'unas', 'name' => 'Manicura rusa', 'slug' => 'manicura-rusa', 'minutes' => 90, 'price' => 45000],
            ['categoria' => 'unas', 'name' => 'Retiro de gel', 'slug' => 'retiro-gel', 'minutes' => 30, 'price' => 15000],
            ['categoria' => 'pestanas', 'name' => 'Pestanas clasicas', 'slug' => 'pestanas-clasicas', 'minutes' => 120, 'price' => 85000],
            ['categoria' => 'pestanas', 'name' => 'Retoque volumen', 'slug' => 'retoque-volumen', 'minutes' => 75, 'price' => 50000],
        ];

        foreach ($services as $serviceData) {
            $category = $categories->firstWhere('slug', $serviceData['categoria']);
            $service = Servicio::firstOrCreate(
                ['slug' => $serviceData['slug']],
                [
                    'categoria_servicio_id' => $category->id,
                    'name' => $serviceData['name'],
                    'description' => 'Servicio base del MVP operativo para FERLEM NAILS.',
                    'duration_minutes' => $serviceData['minutes'],
                    'base_price_amount' => $serviceData['price'],
                    'is_active' => true,
                ],
            );

            PrecioServicio::updateOrCreate(
                ['servicio_id' => $service->id, 'sucursal_id' => $branch->id, 'effective_from' => now()->toDateString()],
                ['amount' => $serviceData['price'], 'is_active' => true],
            );
        }

        $testPassword = env('SEED_STAFF_PASSWORD', $adminPassword);

        if (! env('SEED_STAFF_PASSWORD')) {
            $this->command?->warn('Contrasena temporal del perfil demo prueba: '.$testPassword);
        }

        $testUser = User::updateOrCreate(
            ['email' => 'prueba@ferlemnails.local'],
            [
                'name' => 'Prueba',
                'phone' => '+50422220002',
                'password' => Hash::make($testPassword),
                'email_verified_at' => now(),
                'is_active' => true,
            ],
        );

        $testUser->syncRoles(['cajero']);
        $testUser->sucursales()->syncWithoutDetaching([$branch->id => ['is_default' => true]]);

        $testEmployee = Empleado::updateOrCreate(
            ['user_id' => $testUser->id],
            [
                'name' => 'Prueba',
                'email' => 'prueba@ferlemnails.local',
                'phone' => '+50422220002',
                'role_title' => 'Perfil de prueba',
                'is_active' => true,
            ],
        );

        $testEmployee->sucursales()->syncWithoutDetaching([$branch->id => ['is_primary' => true, 'role_title' => 'Perfil de prueba']]);
    }
}
