<?php

namespace App\Http\Requests\Admin\Tickets;

use App\Http\Requests\Concerns\NormalizesAssignedToInput;
use App\Http\Requests\Concerns\ResolvesAssignableAgentRule;
use App\Models\Ticket;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BulkTicketActionRequest extends FormRequest
{
    use NormalizesAssignedToInput;
    use ResolvesAssignableAgentRule;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'action' => ['required', Rule::in(['delete', 'assign', 'status', 'priority', 'merge'])],
            'selected_ids' => ['required', 'array', 'min:1'],
            'selected_ids.*' => ['integer', 'exists:tickets,id'],
            'assigned_to' => ['nullable', 'array'],
            'assigned_to.*' => [$this->assignableAgentRule()],
            'status' => ['nullable', Rule::in(Ticket::STATUSES)],
            'priority' => ['nullable', Rule::in(Ticket::PRIORITIES)],
            'close_reason' => [
                Rule::requiredIf(fn () => $this->string('action')->toString() === 'status' && $this->string('status')->toString() === 'closed'),
                'nullable',
                'string',
                'max:1000',
            ],
        ];
    }
}
