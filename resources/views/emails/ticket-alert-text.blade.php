{{ $headline }}

{{ $messageLine }}

Ticket Number: {{ $ticket->ticket_number }}
@foreach($details as $label => $value)
@if($label !== 'Ticket Number')
{{ $label }}: {{ $value }}
@endif
@endforeach

@if($actionUrl)
{{ $actionLabel ?: 'Open Ticket' }}: {{ $actionUrl }}
@endif
