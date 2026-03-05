<?php

namespace App\Http\Requests\Admin\Tickets;

use Illuminate\Foundation\Http\FormRequest;

class SetTicketDueDateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'due_date' => ['required', 'date', 'after:now'],
        ];
    }
}
