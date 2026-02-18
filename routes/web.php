<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\TicketController as AdminTicketController;
use App\Http\Controllers\Admin\UserManagementController;
use App\Http\Controllers\Client\DashboardController as ClientDashboardController;
use App\Http\Controllers\Client\TicketController as ClientTicketController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect('/login');
});

// Authentication Routes
Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login']);
Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
Route::post('/register', [AuthController::class, 'register']);
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

// Account Settings (available to all authenticated users)
Route::middleware(['auth'])->group(function () {
    Route::get('/account/settings', [AuthController::class, 'accountSettings'])->name('account.settings');
    Route::put('/account/settings', [AuthController::class, 'updateAccountSettings'])->name('account.settings.update');
});

// Client Routes
Route::middleware(['auth'])->prefix('client')->name('client.')->group(function () {
    Route::get('/dashboard', [ClientDashboardController::class, 'index'])->name('dashboard');

    Route::get('/tickets', [ClientTicketController::class, 'index'])->name('tickets.index');
    Route::get('/tickets/create', [ClientTicketController::class, 'create'])->name('tickets.create');
    Route::post('/tickets', [ClientTicketController::class, 'store'])->name('tickets.store');
    Route::get('/tickets/{ticket}', [ClientTicketController::class, 'show'])->name('tickets.show');
    Route::post('/tickets/{ticket}/reply', [ClientTicketController::class, 'reply'])->name('tickets.reply');
    Route::post('/tickets/{ticket}/close', [ClientTicketController::class, 'close'])->name('tickets.close');
    Route::post('/tickets/{ticket}/rate', [ClientTicketController::class, 'rate'])->name('tickets.rate');
});

// Admin Routes
Route::middleware(['auth', 'role:admin,super_admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/dashboard', [AdminDashboardController::class, 'index'])->name('dashboard');

    Route::get('/tickets', [AdminTicketController::class, 'index'])->name('tickets.index');
    Route::get('/tickets/{ticket}', [AdminTicketController::class, 'show'])->name('tickets.show');
    Route::post('/tickets/{ticket}/assign', [AdminTicketController::class, 'assign'])->name('tickets.assign');
    Route::post('/tickets/{ticket}/status', [AdminTicketController::class, 'updateStatus'])->name('tickets.status');
    Route::post('/tickets/{ticket}/priority', [AdminTicketController::class, 'updatePriority'])->name('tickets.priority');
    Route::post('/tickets/{ticket}/reply', [AdminTicketController::class, 'reply'])->name('tickets.reply');
    Route::post('/tickets/{ticket}/due-date', [AdminTicketController::class, 'setDueDate'])->name('tickets.due-date');

    // User Management Routes
    Route::get('/users', [UserManagementController::class, 'index'])->name('users.index');
    Route::get('/users/create', [UserManagementController::class, 'create'])->name('users.create');
    Route::post('/users', [UserManagementController::class, 'store'])->name('users.store');
    Route::get('/users/{user}', [UserManagementController::class, 'show'])->name('users.show');
    Route::get('/users/{user}/edit', [UserManagementController::class, 'edit'])->name('users.edit');
    Route::put('/users/{user}', [UserManagementController::class, 'update'])->name('users.update');
    Route::delete('/users/{user}', [UserManagementController::class, 'destroy'])->name('users.destroy');
    Route::post('/users/{user}/toggle-status', [UserManagementController::class, 'toggleStatus'])->name('users.toggle-status');
});

// Download attachments
Route::get('/attachments/{attachment}/download', function ($attachmentId) {
    $attachment = \App\Models\Attachment::findOrFail($attachmentId);

    if (!auth()->check()) {
        abort(403);
    }

    $user = auth()->user();
    $attachable = $attachment->attachable;

    if ($attachable instanceof \App\Models\Ticket) {
        if ($user->role === 'client' && $attachable->user_id !== $user->id) {
            abort(403);
        }
    } elseif ($attachable instanceof \App\Models\TicketReply) {
        $ticket = $attachable->ticket;
        if ($user->role === 'client' && $ticket->user_id !== $user->id) {
            abort(403);
        }
    }

    return response()->download(storage_path('app/public/' . $attachment->file_path), $attachment->original_filename);
})->name('attachments.download');