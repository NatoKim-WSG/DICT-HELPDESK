<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Attachment;
use App\Models\Category;
use App\Models\Ticket;
use App\Models\TicketReply;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class TicketController extends Controller
{
    public function index(Request $request)
    {
        $regions = [
            'NCR',
            'CAR',
            'Region I',
            'Region II',
            'Region III',
            'Region IV-A',
            'Region IV-B',
            'Region V',
            'Region VI',
            'Region VII',
            'Region VIII',
            'Region IX',
            'Region X',
            'Region XI',
            'Region XII',
            'Region XIII',
            'BARMM',
        ];

        $accountOptions = User::where('role', User::ROLE_CLIENT)
            ->where('is_active', true)
            ->orderBy('name')
            ->pluck('name')
            ->values();

        $activeTab = $request->string('tab')->toString();
        if ($activeTab === 'scheduled') {
            $activeTab = 'attention';
        }
        if (!in_array($activeTab, ['tickets', 'attention', 'history'], true)) {
            $activeTab = 'tickets';
        }

        $query = Ticket::with(['user', 'category', 'assignedUser']);

        if ($activeTab === 'history') {
            $query->whereIn('status', Ticket::CLOSED_STATUSES);
        } elseif ($activeTab === 'attention') {
            $query->whereNotIn('status', Ticket::CLOSED_STATUSES)
                ->where('created_at', '<=', now()->subHours(16));
        } else {
            $query->whereNotIn('status', Ticket::CLOSED_STATUSES);
        }

        $query
            ->when($request->filled('status') && $request->status !== 'all', function ($builder) use ($request) {
                $builder->where('status', $request->string('status')->toString());
            })
            ->when($request->filled('priority') && $request->priority !== 'all', function ($builder) use ($request) {
                $builder->where('priority', $request->string('priority')->toString());
            })
            ->when($request->filled('category') && $request->category !== 'all', function ($builder) use ($request) {
                $builder->where('category_id', $request->integer('category'));
            });

        if ($request->filled('assigned_to') && $request->assigned_to !== 'all') {
            if ($request->assigned_to === 'unassigned') {
                $query->whereNull('assigned_to');
            } else {
                $query->where('assigned_to', $request->integer('assigned_to'));
            }
        }

        if ($request->region && $request->region !== 'all') {
            $selectedRegion = strtolower($request->region);
            $query->whereHas('user', function ($userQuery) use ($selectedRegion) {
                $userQuery->whereRaw("LOWER(COALESCE(department, '')) LIKE ?", ['%' . $selectedRegion . '%']);
            });
        }

        if ($request->filled('account') && $request->account !== 'all') {
            $selectedAccount = strtolower(trim((string) $request->account));
            $query->whereHas('user', function ($userQuery) use ($selectedAccount) {
                $userQuery->whereRaw("LOWER(COALESCE(name, '')) LIKE ?", ['%' . $selectedAccount . '%']);
            });
        }

        $query->when($request->filled('search'), function ($builder) use ($request) {
            $search = $request->string('search')->toString();
            $builder->where(function ($q) use ($search) {
                $q->where('subject', 'like', '%' . $search . '%')
                    ->orWhere('ticket_number', 'like', '%' . $search . '%')
                    ->orWhereHas('user', function ($userQuery) use ($search) {
                        $userQuery->where('name', 'like', '%' . $search . '%')
                            ->orWhere('email', 'like', '%' . $search . '%');
                    });
            });
        });

        $tickets = $query->latest()->paginate(15);

        $categories = Category::active()->get();
        $agents = User::whereIn('role', User::TICKET_CONSOLE_ROLES)
            ->where('is_active', true)
            ->get();

        return view('admin.tickets.index', compact('tickets', 'categories', 'agents', 'regions', 'accountOptions', 'activeTab'));
    }

    public function show(Ticket $ticket)
    {
        $viewedTicketIds = collect(session('admin_viewed_ticket_ids', []))
            ->map(fn ($id) => (int) $id)
            ->push((int) $ticket->id)
            ->unique()
            ->values()
            ->all();
        session(['admin_viewed_ticket_ids' => $viewedTicketIds]);

        $ticket->load(['user', 'category', 'assignedUser', 'replies.user', 'replies.attachments', 'replies.replyTo', 'attachments']);
        $agents = User::whereIn('role', User::TICKET_CONSOLE_ROLES)
            ->where('is_active', true)
            ->get();

        return view('admin.tickets.show', compact('ticket', 'agents'));
    }

    public function replies(Ticket $ticket): JsonResponse
    {
        $replies = $ticket->replies()
            ->with(['user', 'attachments', 'replyTo'])
            ->orderBy('created_at')
            ->get()
            ->map(fn (TicketReply $reply) => $this->formatReplyForChat($reply))
            ->values();

        return response()->json([
            'replies' => $replies,
        ]);
    }

    public function assign(Request $request, Ticket $ticket)
    {
        $request->validate([
            'assigned_to' => [
                'nullable',
                $this->assignableAgentRule(),
            ],
        ]);

        $previousAssignedTo = $ticket->assigned_to ? (int) $ticket->assigned_to : null;
        $newAssignedTo = $request->filled('assigned_to') ? $request->integer('assigned_to') : null;

        $ticket->update([
            'assigned_to' => $newAssignedTo,
        ]);

        $this->recordAssignmentHandoff($ticket, $previousAssignedTo, $newAssignedTo);

        return redirect()->back()->with('success', 'Ticket assignment updated successfully!');
    }

    public function updateStatus(Request $request, Ticket $ticket)
    {
        $request->validate(['status' => 'required|in:' . implode(',', Ticket::STATUSES)]);

        $updateData = ['status' => $request->status];
        $this->applyLifecycleTimestamps($ticket, $updateData);

        $ticket->update($updateData);

        return redirect()->back()->with('success', 'Ticket status updated successfully!');
    }

    public function updatePriority(Request $request, Ticket $ticket)
    {
        $request->validate(['priority' => 'required|in:' . implode(',', Ticket::PRIORITIES)]);

        $ticket->update(['priority' => $request->priority]);

        return redirect()->back()->with('success', 'Ticket priority updated successfully!');
    }

    public function reply(Request $request, Ticket $ticket)
    {
        $request->validate([
            'message' => 'required|string',
            'is_internal' => 'boolean',
            'reply_to_id' => 'nullable|integer|exists:ticket_replies,id',
            'attachments.*' => 'file|max:10240|mimes:jpg,jpeg,png,pdf,doc,docx,txt',
        ]);

        if ($request->filled('reply_to_id')) {
            $replyTo = TicketReply::where('ticket_id', $ticket->id)->find($request->integer('reply_to_id'));
            if (!$replyTo) {
                return redirect()->back()->with('error', 'Invalid reply target.');
            }
        }

        $reply = TicketReply::create([
            'ticket_id' => $ticket->id,
            'user_id' => auth()->id(),
            'reply_to_id' => $request->integer('reply_to_id') ?: null,
            'message' => $request->message,
            'is_internal' => $request->boolean('is_internal'),
        ]);

        if ($request->hasFile('attachments')) {
            foreach ($request->file('attachments') as $file) {
                $path = $file->store('attachments', 'public');

                $reply->attachments()->create([
                    'filename' => basename($path),
                    'original_filename' => $file->getClientOriginalName(),
                    'file_path' => $path,
                    'mime_type' => $file->getMimeType(),
                    'file_size' => $file->getSize(),
                ]);
            }
        }

        if ($ticket->status === 'open' && !$request->boolean('is_internal')) {
            $ticket->update(['status' => 'in_progress']);
        }

        if ($request->expectsJson()) {
            $reply->loadMissing(['user', 'attachments', 'replyTo']);

            return response()->json([
                'message' => 'Reply added successfully.',
                'reply' => $this->formatReplyForChat($reply),
            ]);
        }

        return redirect()->back()->with('success', 'Reply added successfully!');
    }

    public function updateReply(Request $request, Ticket $ticket, TicketReply $reply): JsonResponse
    {
        if ($reply->ticket_id !== $ticket->id || $reply->user_id !== auth()->id()) {
            abort(403);
        }

        if ($reply->deleted_at) {
            return response()->json(['message' => 'Deleted messages cannot be edited.'], 422);
        }

        $request->validate([
            'message' => 'required|string',
        ]);

        $reply->update([
            'message' => $request->string('message')->toString(),
            'edited_at' => now(),
        ]);

        return response()->json([
            'message' => 'Message edited',
            'reply' => $this->formatReplyForChat($reply->fresh(['user', 'attachments', 'replyTo'])),
        ]);
    }

    public function deleteReply(Ticket $ticket, TicketReply $reply): JsonResponse
    {
        if ($reply->ticket_id !== $ticket->id || $reply->user_id !== auth()->id()) {
            abort(403);
        }

        if (!$reply->deleted_at) {
            $reply->update([
                'message' => 'This message was deleted.',
                'deleted_at' => now(),
            ]);
        }

        return response()->json([
            'message' => 'Message deleted',
            'reply' => $this->formatReplyForChat($reply->fresh(['user', 'attachments', 'replyTo'])),
        ]);
    }

    public function setDueDate(Request $request, Ticket $ticket)
    {
        $request->validate([
            'due_date' => 'required|date|after:now',
        ]);

        $ticket->update(['due_date' => $request->due_date]);

        return redirect()->back()->with('success', 'Due date set successfully!');
    }

    public function quickUpdate(Request $request, Ticket $ticket)
    {
        $request->validate([
            'assigned_to' => [
                'nullable',
                $this->assignableAgentRule(),
            ],
            'status' => 'required|in:' . implode(',', Ticket::STATUSES),
            'priority' => 'required|in:' . implode(',', Ticket::PRIORITIES),
        ]);

        $updateData = [
            'assigned_to' => $request->filled('assigned_to') ? $request->integer('assigned_to') : null,
            'status' => $request->string('status')->toString(),
            'priority' => $request->string('priority')->toString(),
        ];
        $previousAssignedTo = $ticket->assigned_to ? (int) $ticket->assigned_to : null;
        $newAssignedTo = $updateData['assigned_to'];

        $this->applyLifecycleTimestamps($ticket, $updateData);

        $ticket->update($updateData);
        $this->recordAssignmentHandoff($ticket, $previousAssignedTo, $newAssignedTo);

        return redirect()->back()->with('success', 'Ticket updated successfully.');
    }

    public function destroy(Ticket $ticket)
    {
        if (!$this->canRunDestructiveAction()) {
            abort(403, 'Only super users or super admins can delete tickets.');
        }

        DB::transaction(function () use ($ticket) {
            $ticket->attachments()->get()->each->delete();
            $ticket->replies()->with('attachments')->get()->each(function ($reply) {
                $reply->attachments()->get()->each->delete();
                $reply->delete();
            });
            $ticket->delete();
        });

        return redirect()->route('admin.tickets.index')->with('success', 'Ticket deleted successfully.');
    }

    public function bulkAction(Request $request)
    {
        $request->validate([
            'action' => 'required|in:delete,assign,status,priority,merge',
            'selected_ids' => 'required|array|min:1',
            'selected_ids.*' => 'integer|exists:tickets,id',
            'assigned_to' => [
                'nullable',
                $this->assignableAgentRule(),
            ],
            'status' => 'nullable|in:' . implode(',', Ticket::STATUSES),
            'priority' => 'nullable|in:' . implode(',', Ticket::PRIORITIES),
        ]);

        $selectedIds = collect($request->input('selected_ids', []))
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        if ($selectedIds->isEmpty()) {
            return redirect()->back()->with('error', 'No tickets selected.');
        }

        $action = $request->string('action')->toString();
        $tickets = Ticket::whereIn('id', $selectedIds)->get();

        if ($tickets->isEmpty()) {
            return redirect()->back()->with('error', 'Selected tickets were not found.');
        }

        if ($this->isDestructiveBulkAction($action) && !$this->canRunDestructiveAction()) {
            return redirect()->back()->with('error', 'Only super users or super admins can run delete/merge actions.');
        }

        if ($action === 'delete') {
            DB::transaction(function () use ($tickets) {
                $tickets->each(function ($ticket) {
                    $ticket->attachments()->get()->each->delete();
                    $ticket->replies()->with('attachments')->get()->each(function ($reply) {
                        $reply->attachments()->get()->each->delete();
                        $reply->delete();
                    });
                    $ticket->delete();
                });
            });

            return redirect()->back()->with('success', 'Selected tickets deleted successfully.');
        }

        if ($action === 'assign') {
            if (!$request->filled('assigned_to')) {
                return redirect()->back()->with('error', 'Please choose a technical user.');
            }

            $newAssignedTo = $request->integer('assigned_to');
            Ticket::whereIn('id', $selectedIds)->get()->each(function (Ticket $ticket) use ($newAssignedTo) {
                $previousAssignedTo = $ticket->assigned_to ? (int) $ticket->assigned_to : null;
                $ticket->update(['assigned_to' => $newAssignedTo]);
                $this->recordAssignmentHandoff($ticket, $previousAssignedTo, $newAssignedTo);
            });
            return redirect()->back()->with('success', 'Selected tickets assigned successfully.');
        }

        if ($action === 'status') {
            if (!$request->filled('status')) {
                return redirect()->back()->with('error', 'Please choose a status.');
            }

            $newStatus = $request->string('status')->toString();
            $updateData = ['status' => $newStatus];
            $this->applyLifecycleTimestamps(null, $updateData);

            Ticket::whereIn('id', $selectedIds)->update($updateData);
            return redirect()->back()->with('success', 'Selected ticket statuses updated.');
        }

        if ($action === 'priority') {
            if (!$request->filled('priority')) {
                return redirect()->back()->with('error', 'Please choose a priority.');
            }

            Ticket::whereIn('id', $selectedIds)->update(['priority' => $request->string('priority')->toString()]);
            return redirect()->back()->with('success', 'Selected ticket priorities updated.');
        }

        if ($action === 'merge') {
            if ($selectedIds->count() < 2) {
                return redirect()->back()->with('error', 'Select at least two tickets to merge.');
            }

            $orderedTickets = Ticket::whereIn('id', $selectedIds)->orderBy('created_at')->get();
            $primary = $orderedTickets->first();
            $others = $orderedTickets->slice(1);

            DB::transaction(function () use ($primary, $others) {
                foreach ($others as $ticket) {
                    TicketReply::create([
                        'ticket_id' => $primary->id,
                        'user_id' => auth()->id(),
                        'message' => "Merged ticket {$ticket->ticket_number}: {$ticket->subject}",
                        'is_internal' => true,
                    ]);

                    TicketReply::where('ticket_id', $ticket->id)->update(['ticket_id' => $primary->id]);
                    Attachment::where('attachable_type', Ticket::class)
                        ->where('attachable_id', $ticket->id)
                        ->update(['attachable_id' => $primary->id]);

                    $ticket->update([
                        'status' => 'closed',
                        'closed_at' => now(),
                    ]);
                }
            });

            return redirect()->route('admin.tickets.show', $primary)->with('success', 'Tickets merged successfully.');
        }

        return redirect()->back()->with('error', 'Invalid bulk action.');
    }

    private function formatReplyForChat(TicketReply $reply): array
    {
        $fromSupport = in_array(optional($reply->user)->role, User::TICKET_CONSOLE_ROLES, true);

        return [
            'id' => $reply->id,
            'message' => $reply->message,
            'is_internal' => (bool) $reply->is_internal,
            'created_at_iso' => optional($reply->created_at)->toIso8601String(),
            'created_at_human' => optional($reply->created_at)->diffForHumans(),
            'created_at_label' => optional($reply->created_at)?->greaterThan(now()->subDay())
                ? optional($reply->created_at)?->format('g:i A')
                : optional($reply->created_at)?->format('M j, Y'),
            'from_support' => $fromSupport,
            'avatar_logo' => $this->departmentLogoForUser($reply->user, $fromSupport),
            'can_manage' => (bool) ($reply->user_id === auth()->id()),
            'edited' => (bool) $reply->edited_at,
            'deleted' => (bool) $reply->deleted_at,
            'reply_to_id' => $reply->reply_to_id,
            'reply_to_message' => $reply->replyTo ? Str::limit($reply->replyTo->message, 120) : null,
            'reply_to_excerpt' => $reply->replyTo ? Str::limit($reply->replyTo->message, 120) : null,
            'attachments' => $reply->attachments->map(function ($attachment) {
                return [
                    'download_url' => $attachment->download_url,
                    'preview_url' => $attachment->preview_url,
                    'original_filename' => $attachment->original_filename,
                    'mime_type' => $attachment->mime_type,
                    'is_image' => str_starts_with((string) $attachment->mime_type, 'image/'),
                ];
            })->values(),
        ];
    }

    private function departmentLogoForUser(?User $user, bool $fromSupport): string
    {
        if ($fromSupport) {
            return asset('images/ione-logo.png');
        }

        $department = strtolower((string) optional($user)->department);

        if (str_contains($department, 'deped')) {
            return asset('images/deped-logo.png');
        }

        if (str_contains($department, 'dict')) {
            return asset('images/DICT-logo.png');
        }

        if (str_contains($department, 'dar')) {
            return asset('images/dar-logo.png');
        }

        return asset('images/ione-logo.png');
    }

    private function assignableAgentRule(): \Illuminate\Validation\Rules\Exists
    {
        return Rule::exists('users', 'id')->where(function ($query) {
            $query->whereIn('role', User::TICKET_CONSOLE_ROLES)
                ->where('is_active', true);
        });
    }

    private function applyLifecycleTimestamps(?Ticket $ticket, array &$updateData): void
    {
        $status = $updateData['status'] ?? null;

        if (in_array($status, ['open', 'in_progress', 'pending'], true)) {
            $updateData['resolved_at'] = null;
            $updateData['closed_at'] = null;
            return;
        }

        if ($status === 'resolved' && (!$ticket || !$ticket->resolved_at)) {
            $updateData['resolved_at'] = now();
        }

        if ($status === 'resolved') {
            $updateData['closed_at'] = null;
        }

        if ($status === 'closed' && (!$ticket || !$ticket->closed_at)) {
            $updateData['closed_at'] = now();
        }

        if ($status === 'closed' && (!$ticket || !$ticket->resolved_at)) {
            $updateData['resolved_at'] = now();
        }
    }

    private function canRunDestructiveAction(): bool
    {
        $user = auth()->user();

        return $user && $user->isAdminLevel();
    }

    private function isDestructiveBulkAction(string $action): bool
    {
        return in_array($action, ['delete', 'merge'], true);
    }

    private function recordAssignmentHandoff(Ticket $ticket, ?int $previousAssignedTo, ?int $newAssignedTo): void
    {
        if ($previousAssignedTo === $newAssignedTo) {
            return;
        }

        $actorName = optional(auth()->user())->name ?? 'System';
        $previousAssigneeName = $previousAssignedTo ? optional(User::find($previousAssignedTo))->name : null;
        $newAssigneeName = $newAssignedTo ? optional(User::find($newAssignedTo))->name : null;

        $message = match (true) {
            $previousAssignedTo === null && $newAssigneeName !== null => "Ticket was assigned to {$newAssigneeName} by {$actorName}.",
            $previousAssigneeName !== null && $newAssigneeName !== null => "Ticket handoff: {$previousAssigneeName} -> {$newAssigneeName} by {$actorName}.",
            $previousAssigneeName !== null && $newAssignedTo === null => "Ticket was unassigned from {$previousAssigneeName} by {$actorName}.",
            default => null,
        };

        if (!$message) {
            return;
        }

        TicketReply::create([
            'ticket_id' => $ticket->id,
            'user_id' => auth()->id(),
            'message' => $message . ' Previous conversation remains available for continuity.',
            'is_internal' => true,
        ]);
    }
}
