<?php

namespace App\Http\Requests\Admin\Tickets;

use App\Models\Ticket;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTicketStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in(Ticket::STATUSES)],
            'close_reason' => [
                Rule::requiredIf(fn () => $this->string('status')->toString() === 'closed'),
                'nullable',
                'string',
                'max:1000',
            ],
        ];
    }
}
