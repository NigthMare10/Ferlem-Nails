<?php

use App\Modules\IdentidadAcceso\Http\Controllers\Web\AdminReauthenticationController;
use App\Modules\Stitch\Http\Controllers\Web\AdminConsoleController;
use App\Modules\Stitch\Http\Controllers\Web\StitchScreenController;
use App\Modules\Sucursales\Http\Controllers\Web\SucursalSelectionController;
use App\Modules\VentasPOS\Http\Controllers\Web\PosController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/login');

Route::middleware('auth')->group(function (): void {
    Route::get('/sucursales/seleccionar', [SucursalSelectionController::class, 'index'])->name('sucursales.selector');
    Route::post('/sucursales/seleccionar', [SucursalSelectionController::class, 'update'])->middleware('throttle:branch-switch')->name('sucursales.activate');

    Route::get('/confirmar-admin', [AdminReauthenticationController::class, 'show'])->name('auth.reautenticacion');
    Route::post('/confirmar-admin', [AdminReauthenticationController::class, 'store'])->middleware('throttle:sensitive-actions')->name('auth.reautenticacion.store');
});

Route::middleware(['auth', 'active.branch'])->group(function (): void {
    Route::get('/inicio-de-cobro', [PosController::class, 'index'])->middleware('permission:pos.usar')->name('pos.index');
    Route::get('/detalle-de-cobro-y-pago/{categoria}', [PosController::class, 'detail'])->middleware('permission:pos.usar')->name('pos.detail');
    Route::post('/pos/cobrar', [PosController::class, 'checkout'])->middleware(['permission:pos.usar', 'permission:pagos.registrar', 'permission:facturas.emitir', 'throttle:sensitive-actions'])->name('pos.checkout');
    Route::get('/detalle-de-factura-digital/{factura?}', [StitchScreenController::class, 'detalleFacturaDigital'])
        ->middleware('role_or_permission:facturas.emitir|facturas.ver')
        ->name('facturas.show');

    Route::middleware('role_or_permission:super_admin|admin_negocio|gerente_sucursal|auditor|administrador|gerencia')->group(function (): void {
        Route::redirect('/dashboard', '/reportes-de-ventas-analytics')->name('dashboard');
        Route::get('/reportes-de-ventas-analytics', [AdminConsoleController::class, 'reportes'])->name('reportes.index');
        Route::get('/historial-de-facturas', [StitchScreenController::class, 'historialFacturas'])->name('facturas.index');
        Route::get('/detalle-de-factura-premium/{factura?}', [StitchScreenController::class, 'detalleFacturaPremium'])->name('facturas.premium');
        Route::get('/cierre-de-caja-diario', [StitchScreenController::class, 'cierreCaja'])->name('caja.index');
        Route::get('/lista-de-empleados', [StitchScreenController::class, 'listaEmpleados'])->name('empleados.index');
        Route::prefix('/rendimiento-por-empleado')->group(function (): void {
            Route::get('/{empleado}/ganancias', [AdminConsoleController::class, 'gananciasEmpleado'])->name('empleados.ganancias');
            Route::get('/{empleado}/historial-completo', [AdminConsoleController::class, 'historialEmpleado'])->name('empleados.historial');
            Route::get('/{empleado}/exportar', [AdminConsoleController::class, 'exportarEmpleado'])->name('empleados.exportar');
            Route::get('/{empleado?}', [AdminConsoleController::class, 'rendimientoEmpleado'])->name('empleados.rendimiento');
        });
        Route::get('/gestion-de-empleados-admin', [AdminConsoleController::class, 'gestionEmpleadosAdmin'])->name('empleados.gestion');
        Route::post('/gestion-de-empleados-admin/empleados', [AdminConsoleController::class, 'storeEmployee'])->name('empleados.admin.store');
        Route::put('/gestion-de-empleados-admin/empleados/{empleado}', [AdminConsoleController::class, 'updateEmployee'])->name('empleados.admin.update');
        Route::delete('/gestion-de-empleados-admin/empleados/{empleado}', [AdminConsoleController::class, 'destroyEmployee'])->name('empleados.admin.destroy');
        Route::get('/ajuste-de-precios-admin', [AdminConsoleController::class, 'ajustePreciosAdmin'])->name('catalogo.index');
        Route::post('/ajuste-de-precios-admin/servicios', [AdminConsoleController::class, 'storeService'])->name('catalogo.admin.store');
        Route::put('/ajuste-de-precios-admin/servicios/{servicio}', [AdminConsoleController::class, 'updateService'])->name('catalogo.admin.update');
        Route::get('/configuracion-admin', [AdminConsoleController::class, 'configuracionAdmin'])->name('admin.settings');
        Route::put('/configuracion-admin', [AdminConsoleController::class, 'updateConfiguracionAdmin'])->name('admin.settings.update');
    });
});

require __DIR__.'/auth.php';
