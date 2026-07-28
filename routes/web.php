<?php

use App\Http\Controllers\AppointmentController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BusinessHourController;
use App\Http\Controllers\CashController;
use App\Http\Controllers\ConfigurationController;
use App\Http\Controllers\DailyCloseController;
use App\Http\Controllers\EarningsController;
use App\Http\Controllers\ExpenseCategoryController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\SalesController;
use App\Http\Controllers\ServiceController;
use App\Http\Controllers\UserController;
use App\Http\Middleware\AuthorizeAppointmentDepositResolution;
use App\Support\Permissions;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'create'])->name('login');
    Route::post('/login', [AuthController::class, 'store'])->name('login.store');
});

Route::middleware(['auth', 'active'])->group(function () {
    Route::get('/', HomeController::class)->name('home');
    Route::middleware('permission:'.Permissions::NOTIFICATIONS_ACCESS)->group(function () {
        Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
        Route::get('/notifications/recent', [NotificationController::class, 'recent'])->name('notifications.recent');
        Route::patch('/notifications/{notification}/read', [NotificationController::class, 'read'])->name('notifications.read');
        Route::patch('/notifications/read-all', [NotificationController::class, 'readAll'])->name('notifications.read-all');
    });
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
    Route::get('/invoices', [InvoiceController::class, 'index'])->name('invoices.index');
    Route::get('/invoices/{sale}', [InvoiceController::class, 'show'])->name('invoices.show');
    Route::post('/invoices/{sale}/cancel', [InvoiceController::class, 'cancel'])
        ->middleware('permission:'.Permissions::SALES_CANCEL)
        ->name('invoices.cancel');
    Route::post('/invoices/{sale}/payments/{payment}/proof', [InvoiceController::class, 'storeProof'])
        ->middleware('permission:'.Permissions::SALES_UPLOAD_TRANSFER_PROOF)
        ->name('invoices.payments.proof.store');
    Route::get('/invoices/{sale}/payments/{payment}/proof', [SalesController::class, 'proof'])
        ->middleware('permission:'.Permissions::SALES_VIEW_TRANSFER_PROOF)
        ->name('invoices.payments.proof.show');
    Route::get('/sales/{sale}/receipt', [SalesController::class, 'receipt'])->name('sales.receipt');
    Route::get('/sales/{sale}/payments/{payment}/proof', [SalesController::class, 'proof'])
        ->middleware('permission:'.Permissions::SALES_VIEW_TRANSFER_PROOF)
        ->name('sales.payments.proof');
    Route::post('/sales/{sale}/cancel', [SalesController::class, 'cancel'])
        ->middleware('permission:'.Permissions::SALES_CANCEL)
        ->name('sales.cancel');
    Route::middleware([
        'permission:'.Permissions::EXPENSES_ACCESS,
        'permission:'.Permissions::EXPENSES_VIEW,
    ])->group(function () {
        Route::get('/expenses', [ExpenseController::class, 'index'])->name('expenses.index');
        Route::get('/expenses/categories', [ExpenseCategoryController::class, 'index'])
            ->middleware('permission:'.Permissions::EXPENSES_MANAGE_CATEGORIES)
            ->name('expenses.categories.index');
        Route::post('/expenses/categories', [ExpenseCategoryController::class, 'store'])
            ->middleware('permission:'.Permissions::EXPENSES_MANAGE_CATEGORIES)
            ->name('expenses.categories.store');
        Route::put('/expenses/categories/{expenseCategory}', [ExpenseCategoryController::class, 'update'])
            ->middleware('permission:'.Permissions::EXPENSES_MANAGE_CATEGORIES)
            ->name('expenses.categories.update');
        Route::patch('/expenses/categories/{expenseCategory}/status', [ExpenseCategoryController::class, 'status'])
            ->middleware('permission:'.Permissions::EXPENSES_MANAGE_CATEGORIES)
            ->name('expenses.categories.status');
        Route::post('/expenses', [ExpenseController::class, 'store'])
            ->middleware('permission:'.Permissions::EXPENSES_CREATE)
            ->name('expenses.store');
        Route::get('/expenses/{expense}', [ExpenseController::class, 'show'])->middleware('expense.visible')->name('expenses.show');
        Route::put('/expenses/{expense}', [ExpenseController::class, 'update'])
            ->middleware(['expense.visible', 'permission:'.Permissions::EXPENSES_UPDATE])
            ->name('expenses.update');
        Route::post('/expenses/{expense}/cancel', [ExpenseController::class, 'cancel'])
            ->middleware(['expense.visible', 'permission:'.Permissions::EXPENSES_CANCEL])
            ->name('expenses.cancel');
        Route::get('/expenses/{expense}/attachment', [ExpenseController::class, 'attachment'])
            ->middleware(['expense.visible', 'permission:'.Permissions::EXPENSES_VIEW_ATTACHMENT])
            ->name('expenses.attachment');
    });
    Route::get('/earnings', EarningsController::class)
        ->middleware('permission:'.Permissions::REPORTS_SALES_VIEW.'|'.Permissions::REPORTS_EXPENSES_VIEW)
        ->name('earnings.index');
    Route::get('/daily-close/download', [DailyCloseController::class, 'generateDownload'])
        ->middleware('permission:'.Permissions::DAILY_CLOSE_VIEW)
        ->name('daily-close.generate-download');
    Route::post('/daily-close/send', [DailyCloseController::class, 'send'])
        ->middleware(['permission:'.Permissions::DAILY_CLOSE_SEND, 'throttle:3,10'])
        ->name('daily-close.send');
    Route::get('/daily-close/reports/{dailyCloseReport}/download', [DailyCloseController::class, 'download'])
        ->middleware('permission:'.Permissions::DAILY_CLOSE_VIEW)
        ->name('daily-close.reports.download');
    Route::post('/daily-close/reports/{dailyCloseReport}/retry', [DailyCloseController::class, 'retry'])
        ->middleware(['permission:'.Permissions::DAILY_CLOSE_SEND, 'throttle:3,10'])
        ->name('daily-close.reports.retry');
    Route::get('/payroll', fn () => redirect()->route('expenses.index'))
        ->middleware(['permission:'.Permissions::PAYROLL_VIEW, 'permission:'.Permissions::EXPENSES_ACCESS])
        ->name('payroll.index');
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
    Route::get('/appointments/history', [AppointmentController::class, 'history'])
        ->middleware('permission:'.Permissions::APPOINTMENTS_ACCESS)
        ->name('appointments.history');
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
    Route::post('/appointments/{appointment}/deposit', [AppointmentController::class, 'storeDeposit'])
        ->middleware([
            'permission:'.Permissions::APPOINTMENTS_ACCESS,
            'permission:'.Permissions::APPOINTMENTS_MANAGE_DEPOSIT,
        ])
        ->name('appointments.deposit');
    Route::post('/appointments/{appointment}/deposit/refund-excess', [AppointmentController::class, 'refundDepositExcess'])
        ->middleware([
            'permission:'.Permissions::APPOINTMENTS_ACCESS,
            'permission:'.Permissions::APPOINTMENTS_RESOLVE_DEPOSIT,
        ])
        ->name('appointments.deposit.refund-excess');
    Route::post('/appointments/{appointment}/checkout', [AppointmentController::class, 'checkout'])
        ->middleware([
            'permission:'.Permissions::APPOINTMENTS_ACCESS,
            'permission:'.Permissions::APPOINTMENTS_CONVERT_TO_SALE,
            'permission:'.Permissions::SALES_ACCESS,
            'permission:'.Permissions::SALES_CREATE,
        ])
        ->name('appointments.checkout');
    Route::post('/appointments/{appointment}/cancel', [AppointmentController::class, 'cancel'])
        ->middleware([
            'permission:'.Permissions::APPOINTMENTS_ACCESS,
            'permission:'.Permissions::APPOINTMENTS_CANCEL,
            AuthorizeAppointmentDepositResolution::class,
        ])
        ->name('appointments.cancel');
    Route::post('/appointments/{appointment}/no-show', [AppointmentController::class, 'markNoShow'])
        ->middleware([
            'permission:'.Permissions::APPOINTMENTS_ACCESS,
            'permission:'.Permissions::APPOINTMENTS_MARK_NO_SHOW,
            AuthorizeAppointmentDepositResolution::class,
        ])
        ->name('appointments.no-show');
    Route::post('/logout', [AuthController::class, 'destroy'])->name('logout');
    Route::prefix('configuration')->name('configuration.')->middleware('permission:'.Permissions::SETTINGS_ACCESS)->group(function () {
        Route::get('/', ConfigurationController::class)->name('index');
        Route::get('/business-hours', [BusinessHourController::class, 'index'])->middleware('permission:'.Permissions::SETTINGS_BUSINESS_HOURS_MANAGE)->name('business-hours.index');
        Route::put('/business-hours', [BusinessHourController::class, 'update'])->middleware('permission:'.Permissions::SETTINGS_BUSINESS_HOURS_MANAGE)->name('business-hours.update');
        Route::get('/daily-close', [DailyCloseController::class, 'index'])->middleware('permission:'.Permissions::DAILY_CLOSE_VIEW)->name('daily-close.index');
        Route::put('/daily-close', [DailyCloseController::class, 'update'])->middleware('permission:'.Permissions::DAILY_CLOSE_MANAGE)->name('daily-close.update');
        Route::post('/daily-close/test', [DailyCloseController::class, 'test'])->middleware(['permission:'.Permissions::DAILY_CLOSE_SEND, 'throttle:3,10'])->name('daily-close.test');
        Route::get('/users', [UserController::class, 'index'])->middleware('permission:'.Permissions::USERS_VIEW)->name('users.index');
        Route::post('/users', [UserController::class, 'store'])->middleware('permission:'.Permissions::USERS_CREATE)->name('users.store');
        Route::put('/users/{user}', [UserController::class, 'update'])->middleware('permission:'.Permissions::USERS_UPDATE)->name('users.update');
        Route::patch('/users/{user}/status', [UserController::class, 'status'])->middleware('permission:'.Permissions::USERS_TOGGLE_STATUS)->name('users.status');
        Route::patch('/users/{user}/password', [UserController::class, 'password'])->middleware('permission:'.Permissions::USERS_RESET_PASSWORD)->name('users.password');
        Route::get('/users/{user}/compensation', [UserController::class, 'compensation'])->middleware('permission:'.Permissions::PAYROLL_CONFIGURE)->name('users.compensation');
        Route::post('/users/{user}/compensation', [UserController::class, 'storeCompensation'])->middleware('permission:'.Permissions::PAYROLL_CONFIGURE)->name('users.compensation.store');
        Route::get('/services', [ServiceController::class, 'index'])->middleware('permission:'.Permissions::SERVICES_VIEW)->name('services.index');
        Route::post('/services', [ServiceController::class, 'store'])->middleware('permission:'.Permissions::SERVICES_CREATE)->name('services.store');
        Route::put('/services/{service}', [ServiceController::class, 'update'])->middleware('permission:'.Permissions::SERVICES_UPDATE)->name('services.update');
        Route::patch('/services/{service}/status', [ServiceController::class, 'status'])->middleware('permission:'.Permissions::SERVICES_TOGGLE_STATUS)->name('services.status');
        Route::delete('/services/{service}', [ServiceController::class, 'destroy'])->middleware('permission:'.Permissions::SERVICES_DELETE)->name('services.destroy');
    });
});
