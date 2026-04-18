<?php

use App\Modules\Facturacion\Http\Controllers\Api\V1\InvoiceController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'active.branch'])->group(function (): void {
    Route::get('/facturas', [InvoiceController::class, 'index'])->middleware('permission:facturas.ver');
    Route::get('/facturas/{factura}', [InvoiceController::class, 'show'])->middleware('permission:facturas.ver');
    Route::post('/ventas/ordenes/{orden}/factura', [InvoiceController::class, 'store'])
        ->middleware(['permission:facturas.emitir', 'throttle:sensitive-actions']);
});
