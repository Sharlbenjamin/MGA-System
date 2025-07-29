<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class LeadStoreRequest extends FormRequest
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
            'first_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:leads,email'],
            'status' => ['required', 'string'],
            'last_contact_date' => ['nullable', 'date'],
            'linked_in' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:255'],
            'contact_method' => ['nullable', 'string', 'max:255'],
            'client_selection' => ['required', 'in:existing,new'],
        ];

        // If selecting existing client
        if ($this->input('client_selection') === 'existing') {
            $rules['client_id'] = ['required', 'integer', 'exists:clients,id'];
        }

        // If creating new client
        if ($this->input('client_selection') === 'new') {
            $rules['new_client_company_name'] = ['required', 'string', 'max:255'];
            $rules['new_client_type'] = ['required', 'in:Assistance,Insurance,Agency'];
            $rules['new_client_status'] = ['required', 'in:Searching,Interested,Sent,Rejected,Active,On Hold,Closed,Broker,No Reply'];
            $rules['new_client_initials'] = ['required', 'string', 'max:10'];
            $rules['new_client_number_requests'] = ['nullable', 'integer', 'min:0'];
            $rules['new_client_email'] = ['nullable', 'email', 'max:255', 'unique:clients,email'];
            $rules['new_client_phone'] = ['nullable', 'string', 'max:255'];
        }

        return $rules;
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'client_selection.required' => 'Please select whether to use an existing client or create a new one.',
            'client_id.required' => 'Please select an existing client.',
            'new_client_company_name.required' => 'Company name is required when creating a new client.',
            'new_client_type.required' => 'Client type is required when creating a new client.',
            'new_client_status.required' => 'Status is required when creating a new client.',
            'new_client_initials.required' => 'Initials are required when creating a new client.',
            'new_client_email.unique' => 'A client with this email already exists.',
            'email.unique' => 'A lead with this email already exists.',
        ];
    }
} 