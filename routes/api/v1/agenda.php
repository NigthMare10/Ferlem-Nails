<?php

use App\Modules\Agenda\Http\Controllers\Api\V1\AppointmentController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'active.branch'])->group(function (): void {
    Route::get('/agenda/citas', [AppointmentController::class, 'index'])->middleware('permission:agenda.ver');
    Route::post('/agenda/citas', [AppointmentController::class, 'store'])->middleware('permission:agenda.crear');
});
