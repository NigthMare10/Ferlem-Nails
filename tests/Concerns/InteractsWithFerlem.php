<?php

namespace Tests\Concerns;

use App\Models\User;
use App\Modules\Facturacion\Models\SecuenciaDocumento;
use App\Modules\Sucursales\Models\ConfiguracionSucursal;
use App\Modules\Sucursales\Models\Sucursal;
use Database\Seeders\InitialSetupSeeder;
use Illuminate\Support\Facades\Hash;

trait InteractsWithFerlem
{
    protected function seedBaseData(): Sucursal
    {
        $this->seed(InitialSetupSeeder::class);

        return Sucursal::query()->where('code', 'CM-01')->firstOrFail();
    }

    protected function createUserWithRole(string $role, ?Sucursal $branch = null): User
    {
        $user = User::factory()->create([
            'password' => Hash::make('password'),
        ]);

        $user->assignRole($role);

        if ($branch) {
            $user->sucursales()->syncWithoutDetaching([$branch->id => ['is_default' => true]]);
        }

        return $user;
    }

    protected function createBranch(string $code, string $name): Sucursal
    {
        $branch = Sucursal::create([
            'code' => $code,
            'name' => $name,
            'currency_code' => 'HNL',
            'timezone' => 'America/Tegucigalpa',
            'is_active' => true,
        ]);

        ConfiguracionSucursal::create([
            'sucursal_id' => $branch->id,
            'currency_code' => 'HNL',
            'currency_symbol' => 'L',
            'impuesto_nombre' => 'ISV',
            'impuesto_porcentaje' => 15.00,
            'ventana_reautenticacion_minutos' => 15,
            'descuento_sin_reautenticacion_porcentaje' => 10,
            'permitir_precio_manual' => false,
        ]);

        SecuenciaDocumento::create([
            'sucursal_id' => $branch->id,
            'document_type' => 'factura',
            'prefix' => 'FNL-'.$code,
            'current_number' => 0,
            'padding' => 8,
            'is_active' => true,
        ]);

        return $branch;
    }
}
