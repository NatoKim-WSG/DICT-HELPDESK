<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\TicketController as AdminTicketController;
use App\Http\Controllers\Admin\UserManagementController;
use App\Http\Controllers\Client\DashboardController as ClientDashboardController;
use App\Http\Controllers\Client\TicketController as ClientTicketController;
use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

Route::get('/', function () {
    return redirect('/login');
});

// Authentication Routes
Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:5,1');
Route::get('/register', fn () => redirect('/login'));
Route::post('/register', fn () => redirect('/login'));
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

// Account Settings (available to all authenticated users)
Route::middleware(['auth'])->group(function () {
    Route::get('/account/settings', [AuthController::class, 'accountSettings'])->name('account.settings');
    Route::put('/account/settings', [AuthController::class, 'updateAccountSettings'])->name('account.settings.update');
});

// Client Routes
Route::middleware(['auth', 'role:client'])->prefix('client')->name('client.')->group(function () {
    Route::get('/dashboard', [ClientDashboardController::class, 'index'])->name('dashboard');

    Route::get('/tickets', [ClientTicketController::class, 'index'])->name('tickets.index');
    Route::get('/tickets/create', [ClientTicketController::class, 'create'])->name('tickets.create');
    Route::post('/tickets', [ClientTicketController::class, 'store'])->name('tickets.store');
    Route::get('/tickets/{ticket}', [ClientTicketController::class, 'show'])->name('tickets.show');
    Route::get('/tickets/{ticket}/replies', [ClientTicketController::class, 'replies'])->name('tickets.replies.feed');
    Route::post('/tickets/{ticket}/reply', [ClientTicketController::class, 'reply'])->name('tickets.reply');
    Route::patch('/tickets/{ticket}/replies/{reply}', [ClientTicketController::class, 'updateReply'])->name('tickets.replies.update');
    Route::delete('/tickets/{ticket}/replies/{reply}', [ClientTicketController::class, 'deleteReply'])->name('tickets.replies.delete');
    Route::post('/tickets/{ticket}/resolve', [ClientTicketController::class, 'resolve'])->name('tickets.resolve');
    Route::post('/tickets/{ticket}/close', [ClientTicketController::class, 'close'])->name('tickets.close');
    Route::post('/tickets/{ticket}/rate', [ClientTicketController::class, 'rate'])->name('tickets.rate');
});

// Admin Routes
Route::middleware(['auth', 'role:admin,super_admin,technician'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/dashboard', [AdminDashboardController::class, 'index'])
        ->middleware('role:admin,super_admin')
        ->name('dashboard');

    Route::get('/tickets', [AdminTicketController::class, 'index'])->name('tickets.index');
    Route::get('/tickets/{ticket}', [AdminTicketController::class, 'show'])->name('tickets.show');
    Route::get('/tickets/{ticket}/replies', [AdminTicketController::class, 'replies'])->name('tickets.replies.feed');
    Route::post('/tickets/bulk-action', [AdminTicketController::class, 'bulkAction'])->name('tickets.bulk-action');
    Route::post('/tickets/{ticket}/quick-update', [AdminTicketController::class, 'quickUpdate'])->name('tickets.quick-update');
    Route::post('/tickets/{ticket}/assign', [AdminTicketController::class, 'assign'])->name('tickets.assign');
    Route::post('/tickets/{ticket}/status', [AdminTicketController::class, 'updateStatus'])->name('tickets.status');
    Route::post('/tickets/{ticket}/priority', [AdminTicketController::class, 'updatePriority'])->name('tickets.priority');
    Route::delete('/tickets/{ticket}', [AdminTicketController::class, 'destroy'])->name('tickets.destroy');
    Route::post('/tickets/{ticket}/reply', [AdminTicketController::class, 'reply'])->name('tickets.reply');
    Route::patch('/tickets/{ticket}/replies/{reply}', [AdminTicketController::class, 'updateReply'])->name('tickets.replies.update');
    Route::delete('/tickets/{ticket}/replies/{reply}', [AdminTicketController::class, 'deleteReply'])->name('tickets.replies.delete');
    Route::post('/tickets/{ticket}/due-date', [AdminTicketController::class, 'setDueDate'])->name('tickets.due-date');

    Route::post('/notifications/dismiss', function (Request $request) {
        $request->validate([
            'notification_key' => 'required|string|max:255',
        ]);

        $dismissedNotifications = session('dismissed_notifications', []);
        if (!in_array($request->notification_key, $dismissedNotifications, true)) {
            $dismissedNotifications[] = $request->notification_key;
        }

        if (count($dismissedNotifications) > 500) {
            $dismissedNotifications = array_slice($dismissedNotifications, -500);
        }

        session(['dismissed_notifications' => $dismissedNotifications]);

        return back();
    })->name('notifications.dismiss');

    Route::get('/notifications/open/{ticket}', function (Request $request, \App\Models\Ticket $ticket) {
        $notificationKey = $request->query('notification_key');

        if (is_string($notificationKey) && $notificationKey !== '') {
            $dismissedNotifications = session('dismissed_notifications', []);
            if (!in_array($notificationKey, $dismissedNotifications, true)) {
                $dismissedNotifications[] = $notificationKey;
            }

            if (count($dismissedNotifications) > 500) {
                $dismissedNotifications = array_slice($dismissedNotifications, -500);
            }

            session(['dismissed_notifications' => $dismissedNotifications]);
        }

        return redirect()->route('admin.tickets.show', $ticket);
    })->name('notifications.open');

    // User Management Routes
    Route::middleware('role:admin,super_admin')->group(function () {
        Route::get('/users', [UserManagementController::class, 'index'])->name('users.index');
        Route::get('/users/clients', [UserManagementController::class, 'clients'])->name('users.clients');
        Route::get('/users/create', [UserManagementController::class, 'create'])->name('users.create');
        Route::post('/users', [UserManagementController::class, 'store'])->name('users.store');
        Route::get('/users/{user}', [UserManagementController::class, 'show'])->name('users.show');
        Route::get('/users/{user}/edit', [UserManagementController::class, 'edit'])->name('users.edit');
        Route::put('/users/{user}', [UserManagementController::class, 'update'])->name('users.update');
        Route::delete('/users/{user}', [UserManagementController::class, 'destroy'])->name('users.destroy');
        Route::post('/users/{user}/toggle-status', [UserManagementController::class, 'toggleStatus'])->name('users.toggle-status');
    });
});

// Download attachments
Route::get('/attachments/{attachment}/download', function (Request $request, \App\Models\Attachment $attachment) {
    $user = auth()->user();
    $attachable = $attachment->attachable;

    if ($attachable instanceof \App\Models\Ticket) {
        if ($user->isClient() && $attachable->user_id !== $user->id) {
            abort(403);
        }
    } elseif ($attachable instanceof \App\Models\TicketReply) {
        $ticket = $attachable->ticket;
        if ($user->isClient() && $ticket->user_id !== $user->id) {
            abort(403);
        }
    } else {
        abort(403);
    }

    if (!Storage::disk('public')->exists($attachment->file_path)) {
        abort(404);
    }

    if ($request->boolean('preview')) {
        return response()->file(
            storage_path('app/public/' . $attachment->file_path),
            [
                'Content-Disposition' => 'inline; filename="' . $attachment->original_filename . '"',
                'Content-Type' => $attachment->mime_type,
            ]
        );
    }

    return Storage::disk('public')->download($attachment->file_path, $attachment->original_filename);
})->middleware('auth')->name('attachments.download');
