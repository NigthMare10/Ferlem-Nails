<?php

use App\Modules\VentasPOS\Http\Controllers\Api\V1\OrderController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'active.branch', 'permission:pos.usar'])->group(function (): void {
    Route::get('/ventas/ordenes/{orden}', [OrderController::class, 'show']);
    Route::post('/ventas/ordenes', [OrderController::class, 'store']);
});
