<?php

use App\Http\Controllers\AppointmentController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CashController;
use App\Http\Controllers\ConfigurationController;
use App\Http\Controllers\EarningsController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\SalesController;
use App\Http\Controllers\ServiceController;
use App\Http\Controllers\UserController;
use App\Support\Permissions;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'create'])->name('login');
    Route::post('/login', [AuthController::class, 'store'])->name('login.store');
});

Route::middleware(['auth', 'active'])->group(function () {
    Route::get('/', HomeController::class)->name('home');
    Route::get('/cash', [CashController::class, 'index'])
        ->middleware([
            'permission:'.Permissions::SALES_ACCESS,
            'permission:'.Permissions::SALES_CREATE,
        ])
        ->name('cash.index');
    Route::get('/sales/new', [SalesController::class, 'create'])
        ->middleware([
            'permission:'.Permissions::SALES_ACCESS,
            'permission:'.Permissions::SALES_CREATE,
        ])
        ->name('sales.create');
    Route::post('/sales', [SalesController::class, 'store'])
        ->middleware([
            'permission:'.Permissions::SALES_ACCESS,
            'permission:'.Permissions::SALES_CREATE,
        ])
        ->name('sales.store');
    Route::get('/sales/{sale}/receipt', [SalesController::class, 'receipt'])
        ->middleware('permission:'.Permissions::SALES_REPRINT)
        ->name('sales.receipt');
    Route::get('/earnings', EarningsController::class)
        ->middleware('permission:'.Permissions::REPORTS_SALES_VIEW)
        ->name('earnings.index');
    Route::get('/appointments', [AppointmentController::class, 'index'])
        ->middleware('permission:'.Permissions::APPOINTMENTS_ACCESS)
        ->name('appointments.index');
    Route::post('/appointments', [AppointmentController::class, 'store'])
        ->middleware([
            'permission:'.Permissions::APPOINTMENTS_ACCESS,
            'permission:'.Permissions::APPOINTMENTS_CREATE,
        ])
        ->name('appointments.store');
    Route::post('/appointments/availability', [AppointmentController::class, 'availability'])
        ->middleware('permission:'.Permissions::APPOINTMENTS_ACCESS)
        ->name('appointments.availability');
    Route::get('/appointments/{appointment}', [AppointmentController::class, 'show'])
        ->middleware('permission:'.Permissions::APPOINTMENTS_ACCESS)
        ->name('appointments.show');
    Route::put('/appointments/{appointment}', [AppointmentController::class, 'update'])
        ->middleware([
            'permission:'.Permissions::APPOINTMENTS_ACCESS,
            'permission:'.Permissions::APPOINTMENTS_UPDATE,
        ])
        ->name('appointments.update');
    Route::post('/appointments/{appointment}/reschedule', [AppointmentController::class, 'reschedule'])
        ->middleware([
            'permission:'.Permissions::APPOINTMENTS_ACCESS,
            'permission:'.Permissions::APPOINTMENTS_UPDATE,
        ])
        ->name('appointments.reschedule');
    Route::post('/appointments/{appointment}/cancel', [AppointmentController::class, 'cancel'])
        ->middleware([
            'permission:'.Permissions::APPOINTMENTS_ACCESS,
            'permission:'.Permissions::APPOINTMENTS_CANCEL,
        ])
        ->name('appointments.cancel');
    Route::post('/appointments/{appointment}/no-show', [AppointmentController::class, 'markNoShow'])
        ->middleware([
            'permission:'.Permissions::APPOINTMENTS_ACCESS,
            'permission:'.Permissions::APPOINTMENTS_MARK_NO_SHOW,
        ])
        ->name('appointments.no-show');
    Route::post('/logout', [AuthController::class, 'destroy'])->name('logout');
    Route::prefix('configuration')->name('configuration.')->middleware('permission:'.Permissions::SETTINGS_ACCESS)->group(function () {
        Route::get('/', ConfigurationController::class)->name('index');
        Route::get('/users', [UserController::class, 'index'])->middleware('permission:'.Permissions::USERS_VIEW)->name('users.index');
        Route::post('/users', [UserController::class, 'store'])->middleware('permission:'.Permissions::USERS_CREATE)->name('users.store');
        Route::put('/users/{user}', [UserController::class, 'update'])->middleware('permission:'.Permissions::USERS_UPDATE)->name('users.update');
        Route::patch('/users/{user}/status', [UserController::class, 'status'])->middleware('permission:'.Permissions::USERS_TOGGLE_STATUS)->name('users.status');
        Route::patch('/users/{user}/password', [UserController::class, 'password'])->middleware('permission:'.Permissions::USERS_RESET_PASSWORD)->name('users.password');
        Route::get('/services', [ServiceController::class, 'index'])->middleware('permission:'.Permissions::SERVICES_VIEW)->name('services.index');
        Route::post('/services', [ServiceController::class, 'store'])->middleware('permission:'.Permissions::SERVICES_CREATE)->name('services.store');
        Route::put('/services/{service}', [ServiceController::class, 'update'])->middleware('permission:'.Permissions::SERVICES_UPDATE)->name('services.update');
        Route::patch('/services/{service}/status', [ServiceController::class, 'status'])->middleware('permission:'.Permissions::SERVICES_TOGGLE_STATUS)->name('services.status');
        Route::delete('/services/{service}', [ServiceController::class, 'destroy'])->middleware('permission:'.Permissions::SERVICES_DELETE)->name('services.destroy');
    });
});
