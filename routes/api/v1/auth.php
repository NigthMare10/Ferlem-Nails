<?php

use App\Modules\IdentidadAcceso\Http\Controllers\Api\V1\AuthController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function (): void {
    Route::get('/auth/me', [AuthController::class, 'me'])->name('api.v1.auth.me');
    Route::post('/auth/reautenticar-admin', [AuthController::class, 'reauthenticate'])
        ->middleware('throttle:sensitive-actions')
        ->name('api.v1.auth.reauthenticate');
});
