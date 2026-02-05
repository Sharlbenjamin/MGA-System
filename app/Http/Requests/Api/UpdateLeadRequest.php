<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class UpdateLeadRequest extends FormRequest
{
    public function authorize(): bool
    {
        $id = $this->route('id');
        $model = $id ? \App\Models\Lead::find($id) : null;
        return $model && $this->user()?->can('update', $model);
    }

    public function rules(): array
    {
        return [
            'client_id' => ['sometimes', 'integer', 'exists:clients,id'],
            'first_name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'email', 'max:255'],
            'status' => ['sometimes', 'string', 'max:255'],
            'last_contact_date' => ['nullable', 'date'],
            'linked_in' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:255'],
            'contact_method' => ['nullable', 'string', 'max:255'],
        ];
    }
}
