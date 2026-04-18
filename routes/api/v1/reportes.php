<?php

use App\Modules\Reportes\Http\Controllers\Api\V1\ReportController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'active.branch', 'permission:reportes.ver_sucursal'])->group(function (): void {
    Route::get('/reportes/resumen', [ReportController::class, 'summary']);
});
