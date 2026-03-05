<?php

namespace App\Http\Requests\Admin\Tickets;

use Illuminate\Foundation\Http\FormRequest;

class StoreTicketReplyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'message' => ['nullable', 'string', 'required_without:attachments'],
            'is_internal' => ['boolean'],
            'reply_to_id' => ['nullable', 'integer', 'exists:ticket_replies,id'],
            'attachments' => ['nullable', 'array', 'min:1', 'required_without:message'],
            'attachments.*' => ['file', 'max:10240', 'mimes:jpg,jpeg,png,pdf,doc,docx,txt,xls,xlsx'],
        ];
    }
}
