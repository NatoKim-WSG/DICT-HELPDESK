@extends('layouts.app')

@section('title', 'Ticket #' . $ticket->ticket_number . ' - ' . config('app.name'))

@section('content')
@php
    $departmentLogo = static function (?string $department, bool $isSupport = false): string {
        if ($isSupport) {
            return \App\Models\User::supportLogoUrl();
        }

        return \App\Models\User::departmentBrandAssets($department)['logo_url'];
    };
    $clientCompanyLogo = $departmentLogo(auth()->user()->department);
    $supportCompanyLogo = \App\Models\User::supportLogoUrl();
    $hasResolveValidationErrors = $errors->has('resolve_confirmation') || $errors->has('rating') || $errors->has('comment');
@endphp
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8" data-client-ticket-show-page data-resolve-modal-open="{{ $hasResolveValidationErrors ? 'true' : 'false' }}">
    <div class="mb-3">
        <a href="{{ route('client.tickets.index') }}" class="inline-flex items-center text-sm text-gray-500 hover:text-gray-700">
            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
            </svg>
            Back to My Tickets
        </a>
    </div>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            @include('client.tickets.partials.show-header')
            @include('client.tickets.partials.show-conversation')
        </div>

        <div class="space-y-6">
            @include('client.tickets.partials.show-sidebar-details')
            @include('client.tickets.partials.show-sidebar-actions')
        </div>
    </div>
</div>

@if(!in_array($ticket->status, ['resolved', 'closed']))
    @include('client.tickets.partials.show-resolve-modal')
@endif

@include('client.tickets.partials.show-attachment-modal')
@include('client.tickets.partials.show-delete-reply-modal')
@endsection
