<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Ticket;
use App\Models\TicketReply;
use App\Models\User;
use Illuminate\Http\Request;

class TicketController extends Controller
{
    public function index(Request $request)
    {
        $query = Ticket::with(['user', 'category', 'assignedUser']);

        if ($request->status && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        if ($request->priority && $request->priority !== 'all') {
            $query->where('priority', $request->priority);
        }

        if ($request->category && $request->category !== 'all') {
            $query->where('category_id', $request->category);
        }

        if ($request->assigned_to && $request->assigned_to !== 'all') {
            if ($request->assigned_to === 'unassigned') {
                $query->whereNull('assigned_to');
            } else {
                $query->where('assigned_to', $request->assigned_to);
            }
        }

        if ($request->search) {
            $query->where(function($q) use ($request) {
                $q->where('subject', 'like', '%' . $request->search . '%')
                  ->orWhere('ticket_number', 'like', '%' . $request->search . '%')
                  ->orWhereHas('user', function($userQuery) use ($request) {
                      $userQuery->where('name', 'like', '%' . $request->search . '%')
                               ->orWhere('email', 'like', '%' . $request->search . '%');
                  });
            });
        }

        $tickets = $query->latest()->paginate(15);

        $categories = Category::active()->get();
        $agents = User::where('role', 'agent')->orWhere('role', 'admin')->get();

        return view('admin.tickets.index', compact('tickets', 'categories', 'agents'));
    }

    public function show(Ticket $ticket)
    {
        $ticket->load(['user', 'category', 'assignedUser', 'replies.user', 'replies.attachments', 'attachments']);
        $agents = User::where('role', 'agent')->orWhere('role', 'admin')->get();

        return view('admin.tickets.show', compact('ticket', 'agents'));
    }

    public function assign(Request $request, Ticket $ticket)
    {
        $request->validate([
            'assigned_to' => 'required|exists:users,id',
        ]);

        $ticket->update(['assigned_to' => $request->assigned_to]);

        return redirect()->back()->with('success', 'Ticket assigned successfully!');
    }

    public function updateStatus(Request $request, Ticket $ticket)
    {
        $request->validate([
            'status' => 'required|in:open,in_progress,pending,resolved,closed',
        ]);

        $updateData = ['status' => $request->status];

        if ($request->status === 'resolved' && !$ticket->resolved_at) {
            $updateData['resolved_at'] = now();
        }

        if ($request->status === 'closed' && !$ticket->closed_at) {
            $updateData['closed_at'] = now();
        }

        $ticket->update($updateData);

        return redirect()->back()->with('success', 'Ticket status updated successfully!');
    }

    public function updatePriority(Request $request, Ticket $ticket)
    {
        $request->validate([
            'priority' => 'required|in:low,medium,high,urgent',
        ]);

        $ticket->update(['priority' => $request->priority]);

        return redirect()->back()->with('success', 'Ticket priority updated successfully!');
    }

    public function reply(Request $request, Ticket $ticket)
    {
        $request->validate([
            'message' => 'required|string',
            'is_internal' => 'boolean',
            'attachments.*' => 'file|max:10240|mimes:jpg,jpeg,png,pdf,doc,docx,txt',
        ]);

        $reply = TicketReply::create([
            'ticket_id' => $ticket->id,
            'user_id' => auth()->id(),
            'message' => $request->message,
            'is_internal' => $request->boolean('is_internal'),
        ]);

        if ($request->hasFile('attachments')) {
            foreach ($request->file('attachments') as $file) {
                $filename = time() . '_' . $file->getClientOriginalName();
                $path = $file->storeAs('attachments', $filename, 'public');

                $reply->attachments()->create([
                    'filename' => $filename,
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

        return redirect()->back()->with('success', 'Reply added successfully!');
    }

    public function setDueDate(Request $request, Ticket $ticket)
    {
        $request->validate([
            'due_date' => 'required|date|after:now',
        ]);

        $ticket->update(['due_date' => $request->due_date]);

        return redirect()->back()->with('success', 'Due date set successfully!');
    }
}