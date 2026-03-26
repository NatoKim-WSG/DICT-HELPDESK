<?php

namespace App\Http\Requests\Admin\Tickets;

use App\Http\Requests\Concerns\NormalizesAssignedToInput;
use App\Http\Requests\Concerns\ResolvesAssignableAgentRule;
use App\Models\Ticket;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class QuickUpdateTicketRequest extends FormRequest
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
            'assigned_to' => ['nullable', 'array'],
            'assigned_to.*' => [$this->assignableAgentRule()],
            'status' => ['required', Rule::in(Ticket::STATUSES)],
            'priority' => ['required', Rule::in(Ticket::PRIORITIES)],
            'close_reason' => [
                Rule::requiredIf(fn () => $this->string('status')->toString() === 'closed'),
                'nullable',
                'string',
                'max:1000',
            ],
        ];
    }
}
