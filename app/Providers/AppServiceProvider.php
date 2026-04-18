<?php

namespace App\Providers;

use App\Modules\Agenda\Models\Cita;
use App\Modules\Agenda\Policies\CitaPolicy;
use App\Modules\Caja\Models\SesionCaja;
use App\Modules\Caja\Policies\SesionCajaPolicy;
use App\Modules\Clientes\Models\Cliente;
use App\Modules\Clientes\Policies\ClientePolicy;
use App\Modules\Empleados\Models\Empleado;
use App\Modules\Empleados\Policies\EmpleadoPolicy;
use App\Modules\Facturacion\Models\Factura;
use App\Modules\Facturacion\Policies\FacturaPolicy;
use App\Modules\Sucursales\Support\SucursalContext;
use App\Modules\Sucursales\Models\Sucursal;
use App\Modules\Sucursales\Policies\SucursalPolicy;
use App\Modules\VentasPOS\Models\Orden;
use App\Modules\VentasPOS\Policies\OrdenPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(SucursalContext::class, fn () => new SucursalContext());
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);

        Gate::policy(Sucursal::class, SucursalPolicy::class);
        Gate::policy(Cliente::class, ClientePolicy::class);
        Gate::policy(Cita::class, CitaPolicy::class);
        Gate::policy(Empleado::class, EmpleadoPolicy::class);
        Gate::policy(Orden::class, OrdenPolicy::class);
        Gate::policy(Factura::class, FacturaPolicy::class);
        Gate::policy(SesionCaja::class, SesionCajaPolicy::class);

        RateLimiter::for('login', function (Request $request) {
            return [
                Limit::perMinute(5)->by($request->ip()),
                Limit::perMinute(5)->by($request->input('email', 'guest')),
            ];
        });

        RateLimiter::for('sensitive-actions', function (Request $request) {
            return Limit::perMinute(10)->by((string) $request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('branch-switch', function (Request $request) {
            return Limit::perMinute(20)->by((string) $request->user()?->id ?: $request->ip());
        });
    }
}
