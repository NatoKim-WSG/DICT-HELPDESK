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

<div class="bg-white shadow sm:rounded-lg">
    <div class="px-4 py-5 sm:px-6">
        <h3 class="text-lg leading-6 font-medium text-gray-900">Ticket Details</h3>
    </div>
    <div class="border-t border-gray-200 px-4 py-5 sm:px-6">
        <dl class="space-y-4">
            <div>
                <dt class="text-sm font-medium text-gray-500">Name</dt>
                <dd class="text-sm text-gray-900">{{ $ticket->name ?? auth()->user()->name }}</dd>
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
                <dt class="text-sm font-medium text-gray-500">Status</dt>
                <dd class="text-sm text-gray-900">
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $ticket->status_color }}">
                        {{ ucfirst(str_replace('_', ' ', $ticket->status)) }}
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
        </dl>
    </div>
</div>
