<?php

use App\Modules\Caja\Http\Controllers\Api\V1\CashSessionController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'active.branch'])->group(function (): void {
    Route::get('/caja/sesion-activa', [CashSessionController::class, 'current'])->middleware('permission:caja.ver');
    Route::post('/caja/sesiones', [CashSessionController::class, 'open'])->middleware(['permission:caja.abrir', 'throttle:sensitive-actions']);
    Route::post('/caja/sesiones/{sesionCaja}/cerrar', [CashSessionController::class, 'close'])
        ->middleware(['permission:caja.cerrar', 'admin.reauth', 'throttle:sensitive-actions']);
});
