<?php

use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\ReportController as AdminReportController;
use App\Http\Controllers\Admin\SystemLogController;
use App\Http\Controllers\Admin\TicketController as AdminTicketController;
use App\Http\Controllers\Admin\UserManagementController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\Client\DashboardController as ClientDashboardController;
use App\Http\Controllers\Client\TicketController as ClientTicketController;
use App\Http\Controllers\LegalController;
use App\Models\Ticket;
use App\Models\TicketUserState;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

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
    Route::post('/tickets/{ticket}/close', [ClientTicketController::class, 'close'])
        ->middleware('throttle:15,1')
        ->name('tickets.close');
    Route::post('/tickets/{ticket}/rate', [ClientTicketController::class, 'rate'])
        ->middleware('throttle:15,1')
        ->name('tickets.rate');

    Route::post('/notifications/dismiss', function (Request $request) {
        $request->validate([
            'ticket_id' => 'required|integer|exists:tickets,id',
            'activity_at' => 'required|date',
        ]);

        $ticket = Ticket::findOrFail($request->integer('ticket_id'));
        if ((int) $ticket->user_id !== (int) auth()->id()) {
            abort(403);
        }

        $activityAt = Carbon::parse($request->string('activity_at')->toString());
        $state = TicketUserState::query()->firstOrNew([
            'ticket_id' => $ticket->id,
            'user_id' => (int) auth()->id(),
        ]);

        if (! $state->exists || ! $state->hasViewedActivity($activityAt)) {
            return back()->with('error', 'You can dismiss notifications only after viewing them.');
        }

        $state->dismissed_at = $activityAt;
        $state->save();

        return back();
    })->middleware('throttle:60,1')->name('notifications.dismiss');

    Route::post('/notifications/clear', function (Request $request) {
        $ticketIds = Ticket::query()
            ->where('user_id', (int) auth()->id())
            ->pluck('id');

        if ($ticketIds->isEmpty()) {
            return back();
        }

        $now = now();
        $rows = $ticketIds->map(fn ($ticketId) => [
            'ticket_id' => (int) $ticketId,
            'user_id' => (int) auth()->id(),
            'last_seen_at' => $now,
            'dismissed_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ])->all();
        TicketUserState::upsert(
            $rows,
            ['ticket_id', 'user_id'],
            ['last_seen_at', 'dismissed_at', 'updated_at']
        );

        if ($request->expectsJson()) {
            return response()->json(['ok' => true]);
        }

        return back();
    })->middleware('throttle:30,1')->name('notifications.clear');

    Route::post('/notifications/seen/{ticket}', function (Request $request, Ticket $ticket) {
        if ((int) $ticket->user_id !== (int) auth()->id()) {
            abort(403);
        }

        $seenAt = TicketUserState::resolveSeenAt($ticket, $request->input('activity_at'));
        $state = TicketUserState::markSeen($ticket, (int) auth()->id(), $seenAt);
        if (! $state->dismissed_at || $state->dismissed_at->lt($seenAt)) {
            $state->dismissed_at = $seenAt;
            $state->save();
        }

        return response()->json([
            'ok' => true,
            'seen_at' => $seenAt->toIso8601String(),
        ]);
    })->middleware('throttle:120,1')->name('notifications.seen');

    Route::get('/notifications/open/{ticket}', function (Request $request, Ticket $ticket) {
        if ((int) $ticket->user_id !== (int) auth()->id()) {
            abort(403);
        }

        $seenAt = TicketUserState::resolveSeenAt($ticket, $request->query('activity_at'));
        $state = TicketUserState::markSeen($ticket, (int) auth()->id(), $seenAt);
        if (! $state->dismissed_at || $state->dismissed_at->lt($seenAt)) {
            $state->dismissed_at = $seenAt;
            $state->save();
        }

        return redirect()->route('client.tickets.show', $ticket);
    })->name('notifications.open');
});

// Admin Routes
Route::middleware(['auth', 'active', 'consent.accepted', 'role:super_user,admin,shadow,technical'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/dashboard', [AdminDashboardController::class, 'index'])
        ->middleware('role:super_user,admin,shadow,technical')
        ->name('dashboard');
    Route::get('/reports', [AdminReportController::class, 'index'])
        ->middleware('role:super_user,admin,shadow')
        ->name('reports.index');
    Route::get('/reports/monthly/pdf', [AdminReportController::class, 'monthlyPdf'])
        ->middleware('role:super_user,admin,shadow')
        ->name('reports.monthly.pdf');

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
        ->middleware(['throttle:20,1', 'role:admin,shadow'])
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

        $activityAt = Carbon::parse($request->string('activity_at')->toString());
        $state = TicketUserState::query()->firstOrNew([
            'ticket_id' => $ticket->id,
            'user_id' => (int) auth()->id(),
        ]);

        if (! $state->exists || ! $state->hasViewedActivity($activityAt)) {
            return back()->with('error', 'You can dismiss notifications only after viewing them.');
        }

        $state->dismissed_at = $activityAt;
        $state->save();

        return back();
    })->middleware('throttle:60,1')->name('notifications.dismiss');

    Route::post('/notifications/clear', function (Request $request) {
        $authUser = auth()->user();
        $ticketsQuery = Ticket::query()->open();

        if ($authUser && $authUser->isTechnician()) {
            $ticketsQuery->where('assigned_to', $authUser->id);
        }

        $ticketIds = $ticketsQuery->pluck('id');
        if ($ticketIds->isEmpty()) {
            return back();
        }

        $now = now();
        $rows = $ticketIds->map(fn ($ticketId) => [
            'ticket_id' => (int) $ticketId,
            'user_id' => (int) auth()->id(),
            'last_seen_at' => $now,
            'dismissed_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ])->all();
        TicketUserState::upsert(
            $rows,
            ['ticket_id', 'user_id'],
            ['last_seen_at', 'dismissed_at', 'updated_at']
        );

        if ($request->expectsJson()) {
            return response()->json(['ok' => true]);
        }

        return back();
    })->middleware('throttle:30,1')->name('notifications.clear');

    Route::post('/notifications/seen/{ticket}', function (Request $request, Ticket $ticket) {
        $authUser = auth()->user();
        if ($authUser && $authUser->isTechnician() && (int) $ticket->assigned_to !== (int) $authUser->id) {
            abort(403);
        }

        $seenAt = TicketUserState::resolveSeenAt($ticket, $request->input('activity_at'));
        $state = TicketUserState::markSeen($ticket, (int) auth()->id(), $seenAt);
        if (! $state->dismissed_at || $state->dismissed_at->lt($seenAt)) {
            $state->dismissed_at = $seenAt;
            $state->save();
        }

        return response()->json([
            'ok' => true,
            'seen_at' => $seenAt->toIso8601String(),
        ]);
    })->middleware('throttle:120,1')->name('notifications.seen');

    Route::get('/notifications/open/{ticket}', function (Request $request, \App\Models\Ticket $ticket) {
        $authUser = auth()->user();
        if ($authUser && $authUser->isTechnician() && (int) $ticket->assigned_to !== (int) $authUser->id) {
            abort(403);
        }

        $seenAt = TicketUserState::resolveSeenAt($ticket, $request->query('activity_at'));
        $state = TicketUserState::markSeen($ticket, (int) auth()->id(), $seenAt);
        if (! $state->dismissed_at || $state->dismissed_at->lt($seenAt)) {
            $state->dismissed_at = $seenAt;
            $state->save();
        }

        return redirect()->route('admin.tickets.show', $ticket);
    })->name('notifications.open');

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
        Route::post('/users/{user}/password/reset-default', [UserManagementController::class, 'resetManagedUserPassword'])
            ->middleware(['throttle:20,1', 'role:shadow'])
            ->name('users.password.reset-default');
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

    $storageDisk = $attachment->resolvedDisk();
    if (! Storage::disk($storageDisk)->exists($attachment->file_path)) {
        abort(404);
    }

    if ($request->boolean('preview')) {
        $stream = Storage::disk($storageDisk)->readStream($attachment->file_path);
        if (! is_resource($stream)) {
            abort(404);
        }

        return response()->stream(function () use ($stream) {
            fpassthru($stream);
            if (is_resource($stream)) {
                fclose($stream);
            }
        }, 200, [
            'Content-Disposition' => 'inline; filename="'.$attachment->original_filename.'"',
            'Content-Type' => $attachment->mime_type,
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    return Storage::disk($storageDisk)->download($attachment->file_path, $attachment->original_filename);
})->middleware(['auth', 'active', 'consent.accepted'])->name('attachments.download');

