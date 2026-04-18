<?php

use App\Modules\Clientes\Http\Controllers\Api\V1\ClientController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'active.branch'])->group(function (): void {
    Route::get('/clientes', [ClientController::class, 'index'])->middleware('permission:clientes.ver');
    Route::post('/clientes', [ClientController::class, 'store'])->middleware('permission:clientes.crear');
    Route::post('/clientes/alta-rapida', [ClientController::class, 'quickStore'])->middleware('permission:pos.usar');
    Route::put('/clientes/{cliente}', [ClientController::class, 'update'])->middleware('permission:clientes.editar');
});
