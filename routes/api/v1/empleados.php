<?php

use App\Modules\Empleados\Http\Controllers\Api\V1\EmployeeController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'active.branch', 'permission:empleados.ver'])->group(function (): void {
    Route::get('/empleados', [EmployeeController::class, 'index']);
});
