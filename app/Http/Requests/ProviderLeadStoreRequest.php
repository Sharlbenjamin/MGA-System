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
        $rules = [
            'name' => ['required', 'string', 'max:255', 'unique:provider_leads,name'],
            'type' => ['required', 'in:Doctor,Clinic,Hospital,Dental'],
            'city_id' => ['required', 'integer', 'exists:cities,id'],
            'service_types' => ['required', 'array'],
            'service_types.*' => ['string', 'exists:service_types,name'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:20'],
            'communication_method' => ['required', 'string', 'max:50'],
            'status' => ['required', 'string'],
            'last_contact_date' => ['nullable', 'date'],
            'comment' => ['nullable', 'string'],
            'provider_selection' => ['required', 'in:existing,new'],
        ];

        // If selecting existing provider
        if ($this->input('provider_selection') === 'existing') {
            $rules['provider_id'] = ['required', 'integer', 'exists:providers,id'];
        }

        // If creating new provider
        if ($this->input('provider_selection') === 'new') {
            $rules['new_provider_name'] = ['required', 'string', 'max:255', 'unique:providers,name'];
            $rules['new_provider_type'] = ['required', 'in:Doctor,Hospital,Clinic,Dental,Agency'];
            $rules['new_provider_country'] = ['required', 'integer', 'exists:countries,id'];
            $rules['new_provider_status'] = ['required', 'in:Active,Hold,Potential,Black list'];
            $rules['new_provider_email'] = ['nullable', 'email', 'max:255', 'unique:providers,email'];
            $rules['new_provider_phone'] = ['nullable', 'string', 'max:255'];
            $rules['new_provider_payment_due'] = ['nullable', 'integer', 'min:0'];
            $rules['new_provider_payment_method'] = ['nullable', 'in:Online Link,Bank Transfer,AEAT'];
            $rules['new_provider_comment'] = ['nullable', 'string'];
        }

        return $rules;
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'provider_selection.required' => 'Please select whether to use an existing provider or create a new one.',
            'provider_id.required' => 'Please select an existing provider.',
            'new_provider_name.required' => 'Provider name is required when creating a new provider.',
            'new_provider_name.unique' => 'A provider with this name already exists.',
            'new_provider_type.required' => 'Provider type is required when creating a new provider.',
            'new_provider_country.required' => 'Country is required when creating a new provider.',
            'new_provider_status.required' => 'Status is required when creating a new provider.',
            'new_provider_email.unique' => 'A provider with this email already exists.',
            'service_types.required' => 'Please select at least one service type.',
        ];
    }
}
