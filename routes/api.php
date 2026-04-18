<?php

use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    require __DIR__.'/api/v1/auth.php';
    require __DIR__.'/api/v1/sucursales.php';
    require __DIR__.'/api/v1/clientes.php';
    require __DIR__.'/api/v1/catalogo.php';
    require __DIR__.'/api/v1/agenda.php';
    require __DIR__.'/api/v1/empleados.php';
    require __DIR__.'/api/v1/ventas.php';
    require __DIR__.'/api/v1/pagos.php';
    require __DIR__.'/api/v1/facturas.php';
    require __DIR__.'/api/v1/caja.php';
    require __DIR__.'/api/v1/reportes.php';
});
