<?php

use App\Modules\Catalogo\Http\Controllers\Api\V1\CatalogController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'active.branch', 'permission:catalogo.ver'])->group(function (): void {
    Route::get('/catalogo/servicios', [CatalogController::class, 'services']);
});
