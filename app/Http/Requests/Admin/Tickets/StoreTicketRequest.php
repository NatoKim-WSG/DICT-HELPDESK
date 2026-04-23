<?php

namespace App\Http\Requests\Admin\Tickets;

use App\Http\Requests\Concerns\NormalizesAssignedToInput;
use App\Http\Requests\Concerns\ResolvesAssignableAgentRule;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTicketRequest extends FormRequest
{
    use NormalizesAssignedToInput;
    use ResolvesAssignableAgentRule;

    public function authorize(): bool
    {
        return (bool) $this->user()?->canCreateClientTickets();
    }

    public function rules(): array
    {
        $ticketType = Ticket::normalizeTicketTypeValue($this->input('ticket_type'));
        $requesterRoles = $ticketType === Ticket::TYPE_INTERNAL
            ? User::TICKET_ASSIGNABLE_ROLES
            : [User::ROLE_CLIENT];

        return [
            'user_id' => [
                'required',
                Rule::exists('users', 'id')->where(fn ($query) => $query
                    ->whereIn('role', $requesterRoles)
                    ->where('is_active', true)),
            ],
            'name' => ['required', 'string', 'max:255'],
            'contact_number' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'province' => ['required', 'string', 'max:120'],
            'municipality' => ['required', 'string', 'max:120'],
            'subject' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string'],
            'category_id' => ['required', 'exists:categories,id'],
            'ticket_type' => ['required', Rule::in(Ticket::TYPES)],
            'assigned_to' => $ticketType === Ticket::TYPE_INTERNAL
                ? ['required', 'array', 'min:1']
                : ['prohibited'],
            'assigned_to.*' => $ticketType === Ticket::TYPE_INTERNAL
                ? [$this->assignableAgentRule()]
                : ['prohibited'],
            'attachments' => ['nullable', 'array'],
            'attachments.*' => ['file', 'max:10240', 'mimes:jpg,jpeg,png,pdf,doc,docx,txt,xls,xlsx'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->normalizeAssignedToInput();

        $contactNumber = trim((string) $this->input('contact_number', ''));
        $email = trim((string) $this->input('email', ''));

        if ($this->has('ticket_type')) {
            $this->merge([
                'ticket_type' => Ticket::normalizeTicketTypeValue($this->input('ticket_type')),
                'contact_number' => $contactNumber !== '' ? $contactNumber : null,
                'email' => $email !== '' ? $email : null,
            ]);

            return;
        }

        $this->merge([
            'contact_number' => $contactNumber !== '' ? $contactNumber : null,
            'email' => $email !== '' ? $email : null,
        ]);
    }
}
