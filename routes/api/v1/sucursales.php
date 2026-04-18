<?php

use App\Modules\Sucursales\Http\Controllers\Api\V1\BranchController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function (): void {
    Route::get('/sucursales', [BranchController::class, 'index'])->name('api.v1.sucursales.index');
    Route::post('/sucursales/activar', [BranchController::class, 'activate'])->name('api.v1.sucursales.activate');
});
