<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class StoreProviderLeadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', \App\Models\ProviderLead::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'in:Doctor,Clinic,Hospital,Dental'],
            'city_id' => ['nullable', 'integer', 'exists:cities,id'],
            'provider_id' => ['nullable', 'integer', 'exists:providers,id'],
            'service_types' => ['nullable', 'string', 'max:255'],
            'status' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:20'],
            'communication_method' => ['nullable', 'string', 'max:50'],
            'last_contact_date' => ['nullable', 'date'],
            'comment' => ['nullable', 'string'],
        ];
    }
}
