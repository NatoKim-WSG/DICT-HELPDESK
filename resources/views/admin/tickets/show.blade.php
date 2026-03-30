@extends('layouts.app')

@section('title', 'Ticket #' . $ticket->ticket_number . ' - ' . config('app.name'))

@section('content')
@php
    $departmentLogo = static function (?string $department, bool $isSupport = false): string {
        if ($isSupport) return \App\Models\User::supportLogoUrl();
        return \App\Models\User::departmentBrandAssets($department)['logo_url'];
    };
    $clientCompanyLogo = $departmentLogo(data_get($ticket, 'user.department'));
    $supportCompanyLogo = \App\Models\User::supportLogoUrl();
    $actor = auth()->user();
    $requiresDelayedClose = $actor && in_array($actor->normalizedRole(), [
        \App\Models\User::ROLE_TECHNICAL,
        \App\Models\User::ROLE_SUPER_USER,
    ], true);
    $closeAvailableAt = $ticket->resolved_at ? $ticket->resolved_at->copy()->addDay() : null;
    $canCloseNow = ! $requiresDelayedClose || ($closeAvailableAt && now()->gte($closeAvailableAt));
    $showDelayedCloseAction = $requiresDelayedClose && $ticket->status !== 'closed';
    $closedRevertWindowDays = 7;
    $revertDeadline = $ticket->closed_at ? $ticket->closed_at->copy()->addDays($closedRevertWindowDays) : null;
    $canRevertTicket = $ticket->status === 'resolved'
        || ($ticket->status === 'closed' && (! $revertDeadline || now()->lte($revertDeadline)));
    $canAcknowledgeTicket = $actor && in_array($actor->normalizedRole(), [
        \App\Models\User::ROLE_SUPER_USER,
        \App\Models\User::ROLE_ADMIN,
        \App\Models\User::ROLE_SHADOW,
    ], true);
    $acknowledgedAt = optional($currentUserState)->acknowledged_at;
@endphp
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8" data-admin-ticket-show-page>
    <div class="mb-6">
        <a href="{{ route('admin.tickets.index') }}" class="inline-flex items-center text-sm text-gray-500 hover:text-gray-700">
            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
            </svg>
            Back to All Tickets
        </a>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2 space-y-6">
            @include('admin.tickets.partials.show-header')
            @include('admin.tickets.partials.show-conversation')
        </div>

        @include('admin.tickets.partials.show-sidebar')
    </div>
</div>

@include('admin.tickets.partials.show-modals')
@endsection
