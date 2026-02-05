<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class StoreLeadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', \App\Models\Lead::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'client_id' => ['required', 'integer', 'exists:clients,id'],
            'first_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'status' => ['required', 'string', 'max:255'],
            'last_contact_date' => ['nullable', 'date'],
            'linked_in' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:255'],
            'contact_method' => ['nullable', 'string', 'max:255'],
        ];
    }
}
