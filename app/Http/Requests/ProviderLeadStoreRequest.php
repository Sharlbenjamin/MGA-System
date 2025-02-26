<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ProviderLeadStoreRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'city' => ['required', 'string', 'max:255'],
            'service_types' => ['required', 'string'],
            'type' => ['required', 'in:Doctor,Clinic,Hospital,Dental'],
            'provider_id' => ['nullable', 'integer', 'exists:providers,id'],
            'name' => ['required', 'string', 'max:255', 'unique:provider_leads,name'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:20'],
            'communication_method' => ['required', 'string', 'max:50'],
            'status' => ['required', 'in:Pending'],
            'last_contact_date' => ['nullable', 'date'],
            'comment' => ['nullable', 'string'],
        ];
    }
}
