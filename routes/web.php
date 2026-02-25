<?php

use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\TicketController as AdminTicketController;
use App\Http\Controllers\Admin\UserManagementController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\Client\DashboardController as ClientDashboardController;
use App\Http\Controllers\Client\TicketController as ClientTicketController;
use App\Models\Ticket;
use App\Models\TicketUserState;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Route;
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

// Account Settings
Route::middleware(['auth', 'active', 'role:super_user,super_admin,technical'])->group(function () {
    Route::get('/account/settings', [AuthController::class, 'accountSettings'])->name('account.settings');
    Route::put('/account/settings', [AuthController::class, 'updateAccountSettings'])->name('account.settings.update');
});

// Client Routes
Route::middleware(['auth', 'active', 'role:client'])->prefix('client')->name('client.')->group(function () {
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
    Route::post('/tickets/{ticket}/close', [ClientTicketController::class, 'close'])
        ->middleware('throttle:15,1')
        ->name('tickets.close');
    Route::post('/tickets/{ticket}/rate', [ClientTicketController::class, 'rate'])
        ->middleware('throttle:15,1')
        ->name('tickets.rate');

    Route::get('/notifications/open/{ticket}', function (Request $request, Ticket $ticket) {
        if ((int) $ticket->user_id !== (int) auth()->id()) {
            abort(403);
        }

        $activityAt = $request->query('activity_at');
        $seenAt = now();
        if (is_string($activityAt) && trim($activityAt) !== '') {
            try {
                $seenAt = Carbon::parse($activityAt);
            } catch (\Throwable $e) {
                $seenAt = now();
            }
        } else {
            $seenAt = $ticket->updated_at ?? now();
        }

        TicketUserState::updateOrCreate(
            [
                'ticket_id' => $ticket->id,
                'user_id' => auth()->id(),
            ],
            [
                'last_seen_at' => $seenAt,
                'dismissed_at' => null,
            ]
        );

        return redirect()->route('client.tickets.show', $ticket);
    })->name('notifications.open');
});

// Admin Routes
Route::middleware(['auth', 'active', 'role:super_user,super_admin,technical'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/dashboard', [AdminDashboardController::class, 'index'])
        ->middleware('role:super_user,super_admin,technical')
        ->name('dashboard');

    Route::get('/tickets', [AdminTicketController::class, 'index'])->name('tickets.index');
    Route::get('/tickets/{ticket}', [AdminTicketController::class, 'show'])->name('tickets.show');
    Route::get('/tickets/{ticket}/replies', [AdminTicketController::class, 'replies'])->name('tickets.replies.feed');
    Route::post('/tickets/bulk-action', [AdminTicketController::class, 'bulkAction'])
        ->middleware('throttle:20,1')
        ->name('tickets.bulk-action');
    Route::post('/tickets/{ticket}/quick-update', [AdminTicketController::class, 'quickUpdate'])
        ->middleware('throttle:60,1')
        ->name('tickets.quick-update');
    Route::post('/tickets/{ticket}/assign', [AdminTicketController::class, 'assign'])
        ->middleware('throttle:60,1')
        ->name('tickets.assign');
    Route::post('/tickets/{ticket}/status', [AdminTicketController::class, 'updateStatus'])
        ->middleware('throttle:60,1')
        ->name('tickets.status');
    Route::post('/tickets/{ticket}/priority', [AdminTicketController::class, 'updatePriority'])
        ->middleware('throttle:60,1')
        ->name('tickets.priority');
    Route::delete('/tickets/{ticket}', [AdminTicketController::class, 'destroy'])
        ->middleware(['throttle:20,1', 'role:super_admin'])
        ->name('tickets.destroy');
    Route::post('/tickets/{ticket}/reply', [AdminTicketController::class, 'reply'])
        ->middleware('throttle:60,1')
        ->name('tickets.reply');
    Route::patch('/tickets/{ticket}/replies/{reply}', [AdminTicketController::class, 'updateReply'])
        ->middleware('throttle:60,1')
        ->name('tickets.replies.update');
    Route::delete('/tickets/{ticket}/replies/{reply}', [AdminTicketController::class, 'deleteReply'])
        ->middleware('throttle:60,1')
        ->name('tickets.replies.delete');
    Route::post('/tickets/{ticket}/due-date', [AdminTicketController::class, 'setDueDate'])
        ->middleware('throttle:60,1')
        ->name('tickets.due-date');

    Route::post('/notifications/dismiss', function (Request $request) {
        $request->validate([
            'ticket_id' => 'required|integer|exists:tickets,id',
            'activity_at' => 'required|date',
        ]);

        $ticket = Ticket::findOrFail($request->integer('ticket_id'));
        $authUser = auth()->user();
        if ($authUser && $authUser->isTechnician() && (int) $ticket->assigned_to !== (int) $authUser->id) {
            abort(403);
        }

        TicketUserState::updateOrCreate(
            [
                'ticket_id' => $ticket->id,
                'user_id' => auth()->id(),
            ],
            [
                'dismissed_at' => Carbon::parse($request->string('activity_at')->toString()),
            ]
        );

        return back();
    })->middleware('throttle:60,1')->name('notifications.dismiss');

    Route::get('/notifications/open/{ticket}', function (Request $request, \App\Models\Ticket $ticket) {
        $authUser = auth()->user();
        if ($authUser && $authUser->isTechnician() && (int) $ticket->assigned_to !== (int) $authUser->id) {
            abort(403);
        }

        $activityAt = $request->query('activity_at');
        $seenAt = now();
        if (is_string($activityAt) && trim($activityAt) !== '') {
            try {
                $seenAt = Carbon::parse($activityAt);
            } catch (\Throwable $e) {
                $seenAt = now();
            }
        } else {
            $seenAt = $ticket->updated_at ?? now();
        }

        TicketUserState::updateOrCreate(
            [
                'ticket_id' => $ticket->id,
                'user_id' => auth()->id(),
            ],
            [
                'last_seen_at' => $seenAt,
                'dismissed_at' => null,
            ]
        );

        return redirect()->route('admin.tickets.show', $ticket);
    })->name('notifications.open');

    // User Management Routes
    Route::middleware('role:super_user,super_admin')->group(function () {
        Route::get('/users', [UserManagementController::class, 'index'])->name('users.index');
        Route::get('/users/clients', [UserManagementController::class, 'clients'])->name('users.clients');
        Route::get('/users/create', [UserManagementController::class, 'create'])->name('users.create');
        Route::post('/users', [UserManagementController::class, 'store'])
            ->middleware('throttle:20,1')
            ->name('users.store');
        Route::get('/users/{user}', [UserManagementController::class, 'show'])->name('users.show');
        Route::get('/users/{user}/edit', [UserManagementController::class, 'edit'])->name('users.edit');
        Route::put('/users/{user}', [UserManagementController::class, 'update'])
            ->middleware('throttle:20,1')
            ->name('users.update');
        Route::delete('/users/{user}', [UserManagementController::class, 'destroy'])
            ->middleware('throttle:10,1')
            ->name('users.destroy');
        Route::post('/users/{user}/toggle-status', [UserManagementController::class, 'toggleStatus'])
            ->middleware('throttle:30,1')
            ->name('users.toggle-status');
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
        if ($user->isTechnician() && (int) $attachable->assigned_to !== (int) $user->id) {
            abort(403);
        }
    } elseif ($attachable instanceof \App\Models\TicketReply) {
        $ticket = $attachable->ticket;
        if ($user->isClient() && $ticket->user_id !== $user->id) {
            abort(403);
        }
        if ($user->isTechnician() && (int) $ticket->assigned_to !== (int) $user->id) {
            abort(403);
        }
    } else {
        abort(403);
    }

    if (! Storage::disk('public')->exists($attachment->file_path)) {
        abort(404);
    }

    if ($request->boolean('preview')) {
        return response()->file(
            storage_path('app/public/'.$attachment->file_path),
            [
                'Content-Disposition' => 'inline; filename="'.$attachment->original_filename.'"',
                'Content-Type' => $attachment->mime_type,
            ]
        );
    }

    return Storage::disk('public')->download($attachment->file_path, $attachment->original_filename);
})->middleware(['auth', 'active'])->name('attachments.download');
