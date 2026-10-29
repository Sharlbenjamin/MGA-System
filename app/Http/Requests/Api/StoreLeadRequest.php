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
        $creatingClient = $this->boolean('create_new_client');

        return [
            'create_new_client' => ['sometimes', 'boolean'],
            'client_id' => [$creatingClient ? 'nullable' : 'required', 'integer', 'exists:clients,id'],
            'first_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:leads,email'],
            'status' => ['required', 'string', 'max:255'],
            'last_contact_date' => ['nullable', 'date'],
            'linked_in' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:255'],
            'contact_method' => ['nullable', 'string', 'max:255'],
            'new_client_company_name' => [$creatingClient ? 'required' : 'nullable', 'string', 'max:255', 'unique:clients,company_name'],
            'new_client_type' => [$creatingClient ? 'required' : 'nullable', 'string', 'in:Assistance,Insurance,Agency'],
            'new_client_status' => [$creatingClient ? 'required' : 'nullable', 'string', 'max:50'],
            'new_client_initials' => ['nullable', 'string', 'max:10'],
        ];
    }
}
