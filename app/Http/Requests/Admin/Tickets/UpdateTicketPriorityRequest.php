<?php

namespace App\Http\Requests\Admin\Tickets;

use App\Models\Ticket;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTicketPriorityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'priority' => ['required', Rule::in(Ticket::PRIORITIES)],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'priority' => Ticket::normalizePriorityValue($this->input('priority')),
        ]);
    }
}
