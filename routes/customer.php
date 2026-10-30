<?php

use App\Http\Controllers\Customer\AuthController;
use App\Http\Controllers\Customer\PortalController;
use App\Http\Middleware\CustomerAccess;
use Illuminate\Support\Facades\Route;

Route::prefix('clientes')->group(function () {
    foreach (['login' => 'login', 'registro' => 'register', 'olvide-contrasena' => 'forgot', 'restablecer-contrasena/{token}' => 'reset'] as $uri => $mode) {
        Route::get($uri, [AuthController::class, 'form'])->defaults('mode', $mode);
    }
    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:60,1');
    Route::post('registro', [AuthController::class, 'register'])->middleware('throttle:3,10');
    Route::post('olvide-contrasena', [AuthController::class, 'forgot'])->middleware('throttle:3,10');
    Route::post('restablecer-contrasena', [AuthController::class, 'reset'])->middleware('throttle:5,1');
    Route::middleware(CustomerAccess::class)->group(function () {
        Route::post('logout', [AuthController::class, 'logout']);
        Route::post('verificar-email', [AuthController::class, 'resend'])->middleware('throttle:3,10');
        Route::get('verificar-email/{id}/{hash}', [AuthController::class, 'verify'])->middleware('signed')->name('customer.verification.verify');
        foreach (['' => 'home', 'perfil' => 'profile', 'progreso' => 'progress', 'movimientos' => 'progress', 'citas' => 'appointments', 'recompensas' => 'rewards', 'canjes' => 'rewards'] as $uri => $section) {
            Route::get($uri, [PortalController::class, 'index'])->defaults('section', $section);
        }
        Route::patch('perfil', [PortalController::class, 'update']);
        Route::post('foto', [PortalController::class, 'uploadAvatar'])->middleware('throttle:5,1');
        Route::get('foto', [PortalController::class, 'avatar']);
        Route::post('qr', [PortalController::class, 'qr'])->middleware('throttle:10,1');
        Route::post('recompensas/{reward}/canjes', [PortalController::class, 'reserve'])->middleware('throttle:10,1');
        Route::put('favoritos/{service}', [PortalController::class, 'favorite']);
        Route::delete('favoritos/{service}', [PortalController::class, 'favorite']);
    });
});
