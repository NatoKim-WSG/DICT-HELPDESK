@php
    $formatTicketDuration = static function ($startAt, $endAt): ?string {
        if (! $startAt || ! $endAt || $endAt->lt($startAt)) {
            return null;
        }

        $minutes = (int) $startAt->diffInMinutes($endAt);
        $days = intdiv($minutes, 1440);
        $hours = intdiv($minutes % 1440, 60);
        $remainingMinutes = $minutes % 60;
        $parts = [];

        if ($days > 0) {
            $parts[] = $days.'d';
        }

        if ($hours > 0) {
            $parts[] = $hours.'h';
        }

        if ($remainingMinutes > 0 || $parts === []) {
            $parts[] = $remainingMinutes.'m';
        }

        return implode(' ', $parts);
    };
    $resolutionEndedAt = $ticket->resolved_at ?? $ticket->closed_at;
    $resolutionTimeDisplay = $formatTicketDuration($ticket->created_at, $resolutionEndedAt);
@endphp

<div class="space-y-6">
    <div class="bg-white shadow sm:rounded-lg">
        <div class="px-4 py-5 sm:px-6">
            <h3 class="text-lg leading-6 font-medium text-gray-900">Ticket Details</h3>
        </div>
        <div class="border-t border-gray-200 px-4 py-5 sm:px-6">
            <dl class="space-y-4">
                <div>
                    <dt class="text-sm font-medium text-gray-500">Name</dt>
                    <dd class="text-sm text-gray-900">{{ $ticket->name ?? $ticket->user->name }}</dd>
                </div>
                <div>
                    <dt class="text-sm font-medium text-gray-500">Contact Number</dt>
                    <dd class="text-sm text-gray-900">{{ $ticket->contact_number ?? ($ticket->user->phone ?? 'Not provided') }}</dd>
                </div>
                <div>
                    <dt class="text-sm font-medium text-gray-500">Email</dt>
                    <dd class="text-sm text-gray-900">{{ $ticket->email ?? $ticket->user->email }}</dd>
                </div>
                <div>
                    <dt class="text-sm font-medium text-gray-500">Province</dt>
                    <dd class="text-sm text-gray-900">{{ $ticket->province ?? 'Not provided' }}</dd>
                </div>
                <div>
                    <dt class="text-sm font-medium text-gray-500">Municipality</dt>
                    <dd class="text-sm text-gray-900">{{ $ticket->municipality ?? 'Not provided' }}</dd>
                </div>
                <div>
                    <dt class="text-sm font-medium text-gray-500">Category</dt>
                    <dd class="text-sm text-gray-900">{{ $ticket->category->name }}</dd>
                </div>
                <div>
                    <dt class="text-sm font-medium text-gray-500">Ticket Type</dt>
                    <dd class="text-sm text-gray-900">
                        <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold {{ $ticket->ticket_type_badge_class }}">
                            {{ $ticket->ticket_type_label }}
                        </span>
                    </dd>
                </div>
                <div>
                    <dt class="text-sm font-medium text-gray-500">Assigned Staff</dt>
                    <dd class="text-sm text-gray-900">{{ $ticket->assigned_users_label }}</dd>
                </div>
                @if($ticket->resolved_at)
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Resolved At</dt>
                        <dd class="text-sm text-gray-900">{{ $ticket->resolved_at->format('M j, Y \a\t g:i A') }}</dd>
                    </div>
                @endif
                @if($resolutionTimeDisplay)
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Resolution Time</dt>
                        <dd class="text-sm text-gray-900">{{ $resolutionTimeDisplay }}</dd>
                    </div>
                @endif
                @if($ticket->closed_at)
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Closed At</dt>
                        <dd class="text-sm text-gray-900">{{ $ticket->closed_at->format('M j, Y \a\t g:i A') }}</dd>
                    </div>
                @endif
                @if($ticket->satisfaction_rating)
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Client Rating</dt>
                        <dd class="mt-2 rounded-2xl border border-slate-200 bg-slate-50 p-4">
                            <div class="flex items-center gap-3">
                                <div class="flex items-center">
                                    @for($i = 1; $i <= 5; $i++)
                                        <svg class="h-4 w-4 {{ $i <= $ticket->satisfaction_rating ? 'text-yellow-400' : 'text-gray-300' }}" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
                                            <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
                                        </svg>
                                    @endfor
                                </div>
                                <span class="text-sm font-medium text-slate-700">{{ $ticket->satisfaction_rating }} / 5</span>
                            </div>
                            @if($ticket->satisfaction_comment)
                                <p class="mt-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Comment / Suggestion / Complaint</p>
                                <p class="mt-1 text-sm text-slate-600">{{ $ticket->satisfaction_comment }}</p>
                            @endif
                        </dd>
                    </div>
                @endif
            </dl>
        </div>
    </div>

    <div class="bg-white shadow sm:rounded-lg">
        <div class="px-4 py-5 sm:px-6">
            <h3 class="text-lg leading-6 font-medium text-gray-900">Actions</h3>
        </div>
        <div class="border-t border-gray-200 px-4 py-5 sm:px-6 space-y-4">
            @if(in_array($ticket->status, ['resolved', 'closed'], true))
                @if($canRevertTicket)
                    <button type="button" id="open-revert-modal-btn" class="btn-secondary w-full">Revert to In Progress</button>
                @else
                    <button type="button" class="btn-secondary w-full opacity-60 cursor-not-allowed" disabled>Revert expired</button>
                    <p class="text-xs text-slate-500">
                        This ticket can no longer be reverted because it has been closed for more than {{ $closedRevertWindowDays }} days.
                    </p>
                @endif
            @endif

            <form action="{{ route('admin.tickets.assign', $ticket) }}" method="POST">
                @csrf
                <input type="hidden" name="return_to" value="{{ request()->getRequestUri() }}">
                @php($selectedAssignedIds = collect(old('assigned_to', $ticket->assigned_user_ids))->map(fn ($id) => (string) $id)->all())
                <div>
                    <span class="form-label">Assign Staff</span>
                    <select
                        name="assigned_to[]"
                        id="assigned_to"
                        class="form-input"
                        multiple
                        data-enhanced-multiselect="1"
                        data-placeholder="Select support staff"
                    >
                        @foreach($assignees as $assignee)
                            <option
                                value="{{ $assignee->id }}"
                                {{ in_array((string) $assignee->id, $selectedAssignedIds, true) ? 'selected' : '' }}
                            >
                                {{ $assignee->publicDisplayName() }}
                            </option>
                        @endforeach
                    </select>
                    @error('assigned_to')
                        <p class="mt-1 text-sm text-rose-600">{{ $message }}</p>
                    @enderror
                    <button type="submit" class="mt-2 btn-secondary w-full">Update Assignment</button>
                </div>
            </form>

            <form action="{{ route('admin.tickets.status', $ticket) }}" method="POST">
                @csrf
                <input type="hidden" name="return_to" value="{{ request()->getRequestUri() }}">
                <div>
                    <label for="status" class="form-label">Status</label>
                    <select name="status" id="status" class="form-input">
                        <option value="open" {{ old('status', $ticket->status) === 'open' ? 'selected' : '' }} {{ $ticket->status === 'closed' && ! $canRevertTicket ? 'disabled' : '' }}>Open</option>
                        <option value="in_progress" {{ old('status', $ticket->status) === 'in_progress' ? 'selected' : '' }} {{ $ticket->status === 'closed' && ! $canRevertTicket ? 'disabled' : '' }}>In Progress</option>
                        <option value="pending" {{ old('status', $ticket->status) === 'pending' ? 'selected' : '' }} {{ $ticket->status === 'closed' && ! $canRevertTicket ? 'disabled' : '' }}>Pending</option>
                        <option value="resolved" {{ old('status', $ticket->status) === 'resolved' ? 'selected' : '' }} {{ $ticket->status === 'closed' && ! $canRevertTicket ? 'disabled' : '' }}>Resolved</option>
                        <option value="closed" {{ old('status', $ticket->status) === 'closed' ? 'selected' : '' }}>Closed</option>
                    </select>
                    <div id="status-close-reason-wrap" class="mt-2 hidden">
                        <label for="status_close_reason" class="form-label">Close Reason <span class="text-rose-500">*</span></label>
                        <textarea
                            name="close_reason"
                            id="status_close_reason"
                            rows="3"
                            class="form-input @error('close_reason') border-rose-300 focus:border-rose-400 focus:ring-rose-200 @enderror"
                            placeholder="Provide a reason for closing this ticket..."
                        >{{ old('close_reason') }}</textarea>
                        @error('close_reason')
                            <p class="mt-1 text-sm text-rose-600">{{ $message }}</p>
                        @enderror
                    </div>
                    @if($ticket->status === 'closed' && ! $canRevertTicket)
                        <p class="mt-2 text-xs text-rose-700">
                            Closed tickets cannot be reverted after {{ $closedRevertWindowDays }} days.
                        </p>
                    @endif
                    <button type="submit" class="mt-2 btn-secondary w-full">Update Status</button>
                </div>
            </form>

            <form action="{{ route('admin.tickets.severity', $ticket) }}" method="POST">
                @csrf
                <input type="hidden" name="return_to" value="{{ request()->getRequestUri() }}">
                <div>
                    <label for="severity" class="form-label">Severity</label>
                    <select name="severity" id="severity" class="form-input">
                        @if($ticket->priority === null)
                            <option value="" selected disabled>Pending review</option>
                        @endif
                        <option value="severity_1" {{ $ticket->priority === 'severity_1' ? 'selected' : '' }}>Severity 1</option>
                        <option value="severity_2" {{ $ticket->priority === 'severity_2' ? 'selected' : '' }}>Severity 2</option>
                        <option value="severity_3" {{ $ticket->priority === 'severity_3' ? 'selected' : '' }}>Severity 3</option>
                    </select>
                    <button type="submit" class="mt-2 btn-secondary w-full">Update Severity</button>
                </div>
            </form>

            @if($showCloseAction)
                <form action="{{ route('admin.tickets.status', $ticket) }}" method="POST">
                    @csrf
                    <input type="hidden" name="return_to" value="{{ request()->getRequestUri() }}">
                    <input type="hidden" name="status" value="closed">
                    <div>
                        <label for="timed_close_reason" class="form-label">Close Ticket</label>
                        <textarea
                            id="timed_close_reason"
                            name="close_reason"
                            rows="3"
                            class="form-input"
                            placeholder="Provide a reason for closing this ticket..."
                            required
                        >{{ old('status') === 'closed' ? old('close_reason') : '' }}</textarea>
                        <button type="submit" class="mt-2 btn-danger w-full">Close Ticket</button>
                    </div>
                </form>
            @endif

            @if(auth()->user()->canManageTicketType())
                <form action="{{ route('admin.tickets.type', $ticket) }}" method="POST">
                    @csrf
                    <input type="hidden" name="return_to" value="{{ request()->getRequestUri() }}">
                    <div>
                        <label for="ticket_type" class="form-label">Ticket Type</label>
                        <select name="ticket_type" id="ticket_type" class="form-input">
                            <option value="{{ \App\Models\Ticket::TYPE_EXTERNAL }}" {{ old('ticket_type', $ticket->ticket_type) === \App\Models\Ticket::TYPE_EXTERNAL ? 'selected' : '' }}>External</option>
                            <option value="{{ \App\Models\Ticket::TYPE_INTERNAL }}" {{ old('ticket_type', $ticket->ticket_type) === \App\Models\Ticket::TYPE_INTERNAL ? 'selected' : '' }}>Internal</option>
                        </select>
                        @error('ticket_type')
                            <p class="mt-1 text-sm text-rose-600">{{ $message }}</p>
                        @enderror
                        <button type="submit" class="mt-2 btn-secondary w-full">Update Ticket Type</button>
                    </div>
                </form>
            @endif
        </div>
    </div>
</div>
