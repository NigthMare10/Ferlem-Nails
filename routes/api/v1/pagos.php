<?php

use App\Modules\Pagos\Http\Controllers\Api\V1\PaymentController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'active.branch', 'permission:pagos.registrar'])->group(function (): void {
    Route::post('/ventas/ordenes/{orden}/pagos', [PaymentController::class, 'store'])->middleware('throttle:sensitive-actions');
});
