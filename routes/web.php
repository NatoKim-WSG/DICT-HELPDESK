<?php

use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\ManagedUserAccountController;
use App\Http\Controllers\Admin\ManagedUserCredentialController;
use App\Http\Controllers\Admin\ManagedUserProfileController;
use App\Http\Controllers\Admin\ReportController as AdminReportController;
use App\Http\Controllers\Admin\SystemLogController;
use App\Http\Controllers\Admin\TicketActionController;
use App\Http\Controllers\Admin\TicketController as AdminTicketController;
use App\Http\Controllers\Admin\TicketReplyController as AdminTicketReplyController;
use App\Http\Controllers\Admin\UserManagementController;
use App\Http\Controllers\AttachmentController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\Client\DashboardController as ClientDashboardController;
use App\Http\Controllers\Client\TicketController as ClientTicketController;
use App\Http\Controllers\LegalController;
use App\Http\Controllers\NotificationController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect('/login');
});

Route::prefix('legal')->name('legal.')->group(function () {
    Route::get('/terms', [LegalController::class, 'terms'])->name('terms');
    Route::get('/privacy', [LegalController::class, 'privacy'])->name('privacy');
    Route::get('/ticket-consent', [LegalController::class, 'ticketConsent'])->name('ticket-consent');
});

// Authentication Routes
Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:5,1');
Route::get('/register', fn () => redirect('/login'));
Route::post('/register', fn () => redirect('/login'));
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

Route::middleware(['auth', 'active'])->prefix('legal')->name('legal.')->group(function () {
    Route::get('/acceptance', [LegalController::class, 'showAcceptance'])->name('acceptance.show');
    Route::post('/acceptance', [LegalController::class, 'accept'])->name('acceptance.store');
});

// Account Settings
Route::middleware(['auth', 'active', 'consent.accepted', 'role:super_user,admin,shadow,technical'])->group(function () {
    Route::get('/account/settings', [AuthController::class, 'accountSettings'])->name('account.settings');
    Route::put('/account/settings', [AuthController::class, 'updateAccountSettings'])->name('account.settings.update');
});

// Client Routes
Route::middleware(['auth', 'active', 'consent.accepted', 'role:client'])->prefix('client')->name('client.')->group(function () {
    Route::get('/dashboard', [ClientDashboardController::class, 'index'])->name('dashboard');

    Route::get('/tickets', [ClientTicketController::class, 'index'])->name('tickets.index');
    Route::get('/tickets/create', [ClientTicketController::class, 'create'])->name('tickets.create');
    Route::post('/tickets', [ClientTicketController::class, 'store'])
        ->middleware('throttle:10,1')
        ->name('tickets.store');
    Route::get('/tickets/{ticket}', [ClientTicketController::class, 'show'])->name('tickets.show');
    Route::get('/tickets/{ticket}/replies', [ClientTicketController::class, 'replies'])->name('tickets.replies.feed');
    Route::post('/tickets/{ticket}/reply', [ClientTicketController::class, 'reply'])
        ->middleware('throttle:30,1')
        ->name('tickets.reply');
    Route::patch('/tickets/{ticket}/replies/{reply}', [ClientTicketController::class, 'updateReply'])
        ->middleware('throttle:30,1')
        ->name('tickets.replies.update');
    Route::delete('/tickets/{ticket}/replies/{reply}', [ClientTicketController::class, 'deleteReply'])
        ->middleware('throttle:30,1')
        ->name('tickets.replies.delete');
    Route::post('/tickets/{ticket}/resolve', [ClientTicketController::class, 'resolve'])
        ->middleware('throttle:15,1')
        ->name('tickets.resolve');
    Route::post('/tickets/{ticket}/rate', [ClientTicketController::class, 'rate'])
        ->middleware('throttle:15,1')
        ->name('tickets.rate');

    Route::post('/notifications/dismiss', [NotificationController::class, 'clientDismiss'])
        ->middleware('throttle:60,1')
        ->name('notifications.dismiss');
    Route::post('/notifications/clear', [NotificationController::class, 'clientClear'])
        ->middleware('throttle:30,1')
        ->name('notifications.clear');
    Route::post('/notifications/seen/{ticket}', [NotificationController::class, 'clientSeen'])
        ->middleware('throttle:120,1')
        ->name('notifications.seen');
    Route::get('/notifications/open/{ticket}', [NotificationController::class, 'clientOpen'])
        ->name('notifications.open');
});

// Admin Routes
Route::middleware(['auth', 'active', 'consent.accepted', 'role:super_user,admin,shadow,technical', 'password.change.required'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/dashboard', [AdminDashboardController::class, 'index'])
        ->name('dashboard');
    Route::get('/reports', [AdminReportController::class, 'index'])
        ->middleware('role:super_user,admin,shadow')
        ->name('reports.index');
    Route::get('/reports/monthly/pdf', [AdminReportController::class, 'monthlyPdf'])
        ->middleware('role:super_user,admin,shadow')
        ->name('reports.monthly.pdf');

    Route::get('/tickets', [AdminTicketController::class, 'index'])->name('tickets.index');
    Route::get('/tickets/create', [AdminTicketController::class, 'create'])
        ->middleware('role:super_user')
        ->name('tickets.create');
    Route::post('/tickets', [AdminTicketController::class, 'store'])
        ->middleware(['throttle:20,1', 'role:super_user'])
        ->name('tickets.store');
    Route::get('/tickets/{ticket}', [AdminTicketController::class, 'show'])->name('tickets.show');
    Route::get('/tickets/{ticket}/replies', [AdminTicketReplyController::class, 'replies'])->name('tickets.replies.feed');
    Route::post('/tickets/{ticket}/acknowledge', [TicketActionController::class, 'acknowledge'])
        ->middleware('throttle:60,1')
        ->name('tickets.acknowledge');
    Route::post('/tickets/bulk-action', [TicketActionController::class, 'bulkAction'])
        ->middleware('throttle:20,1')
        ->name('tickets.bulk-action');
    Route::post('/tickets/{ticket}/quick-update', [TicketActionController::class, 'quickUpdate'])
        ->middleware('throttle:60,1')
        ->name('tickets.quick-update');
    Route::post('/tickets/{ticket}/assign', [TicketActionController::class, 'assign'])
        ->middleware('throttle:60,1')
        ->name('tickets.assign');
    Route::post('/tickets/{ticket}/status', [TicketActionController::class, 'updateStatus'])
        ->middleware('throttle:60,1')
        ->name('tickets.status');
    Route::post('/tickets/{ticket}/severity', [TicketActionController::class, 'updateSeverity'])
        ->middleware('throttle:60,1')
        ->name('tickets.severity');
    Route::post('/tickets/{ticket}/type', [TicketActionController::class, 'updateType'])
        ->middleware(['throttle:60,1', 'role:super_user'])
        ->name('tickets.type');
    Route::delete('/tickets/{ticket}', [TicketActionController::class, 'destroy'])
        ->middleware(['throttle:20,1', 'role:admin,shadow'])
        ->name('tickets.destroy');
    Route::post('/tickets/{ticket}/reply', [AdminTicketReplyController::class, 'reply'])
        ->middleware('throttle:60,1')
        ->name('tickets.reply');
    Route::patch('/tickets/{ticket}/replies/{reply}', [AdminTicketReplyController::class, 'updateReply'])
        ->middleware('throttle:60,1')
        ->name('tickets.replies.update');
    Route::delete('/tickets/{ticket}/replies/{reply}', [AdminTicketReplyController::class, 'deleteReply'])
        ->middleware('throttle:60,1')
        ->name('tickets.replies.delete');
    Route::post('/notifications/dismiss', [NotificationController::class, 'adminDismiss'])
        ->middleware('throttle:60,1')
        ->name('notifications.dismiss');
    Route::post('/notifications/clear', [NotificationController::class, 'adminClear'])
        ->middleware('throttle:30,1')
        ->name('notifications.clear');
    Route::post('/notifications/seen/{ticket}', [NotificationController::class, 'adminSeen'])
        ->middleware('throttle:120,1')
        ->name('notifications.seen');
    Route::get('/notifications/open/{ticket}', [NotificationController::class, 'adminOpen'])
        ->name('notifications.open');

    Route::middleware('role:shadow')->prefix('system-logs')->name('system-logs.')->group(function () {
        Route::get('/unlock', [SystemLogController::class, 'showUnlockForm'])->name('unlock.show');
        Route::post('/unlock', [SystemLogController::class, 'unlock'])
            ->middleware('throttle:10,1')
            ->name('unlock.store');
        Route::post('/lock', [SystemLogController::class, 'lock'])->name('lock');
        Route::get('/', [SystemLogController::class, 'index'])
            ->middleware('system_logs.unlocked')
            ->name('index');
    });

    // User Management Routes
    Route::middleware('role:super_user,admin,shadow')->group(function () {
        Route::get('/users', [UserManagementController::class, 'index'])->name('users.index');
        Route::get('/users/clients', [UserManagementController::class, 'clients'])->name('users.clients');
        Route::get('/users/create', [UserManagementController::class, 'create'])->name('users.create');
        Route::post('/users', [UserManagementController::class, 'store'])
            ->middleware('throttle:20,1')
            ->name('users.store');
        Route::get('/users/{user}', [ManagedUserProfileController::class, 'show'])->name('users.show');
        Route::get('/users/{user}/edit', [ManagedUserProfileController::class, 'edit'])->name('users.edit');
        Route::put('/users/{user}', [ManagedUserProfileController::class, 'update'])
            ->middleware('throttle:20,1')
            ->name('users.update');
        Route::delete('/users/{user}', [ManagedUserAccountController::class, 'destroy'])
            ->middleware('throttle:10,1')
            ->name('users.destroy');
        Route::post('/users/{user}/toggle-status', [ManagedUserAccountController::class, 'toggleStatus'])
            ->middleware('throttle:30,1')
            ->name('users.toggle-status');
        Route::post('/users/{user}/password/reset-default', [ManagedUserCredentialController::class, 'resetManagedUserPassword'])
            ->middleware(['throttle:20,1', 'role:shadow'])
            ->name('users.password.reset-default');
        Route::post('/users/{user}/password/reveal-temporary', [ManagedUserCredentialController::class, 'revealManagedUserPassword'])
            ->middleware(['throttle:20,1', 'role:shadow'])
            ->name('users.password.reveal-temporary');
    });
});

// Download attachments
Route::get('/attachments/{attachment}/download', [AttachmentController::class, 'download'])
    ->middleware(['auth', 'active', 'consent.accepted'])
    ->name('attachments.download');
