<?php

namespace App\Http\Requests\Client\Tickets;

use App\Models\Ticket;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'contact_number' => ['required', 'string', 'max:30'],
            'email' => ['required', 'email', 'max:255'],
            'province' => ['required', 'string', 'max:120'],
            'municipality' => ['required', 'string', 'max:120'],
            'subject' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string'],
            'category_id' => ['required', 'exists:categories,id'],
            'priority' => ['required', Rule::in(Ticket::PRIORITIES)],
            'ticket_consent' => ['accepted'],
            'attachments' => ['required', 'array', 'min:1'],
            'attachments.*' => ['file', 'max:10240', 'mimes:jpg,jpeg,png,pdf,doc,docx,txt,xls,xlsx'],
        ];
    }
}
