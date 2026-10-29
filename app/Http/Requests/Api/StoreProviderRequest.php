<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class StoreProviderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', \App\Models\Provider::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'string', 'in:Doctor,Hospital,Clinic,Dental,Agency'],
            'country_id' => ['required', 'integer', 'exists:countries,id'],
            'status' => ['required', 'string', 'in:Active,Hold,Potential,Searching,Black List,Black list'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:255'],
            'payment_due' => ['nullable', 'integer', 'min:0'],
            'payment_method' => ['nullable', 'string', 'in:Online Link,Bank Transfer,AEAT'],
            'needs_payment' => ['nullable', 'boolean'],
            'comment' => ['nullable', 'string'],
            'request_comment' => ['nullable', 'string'],
            'assigned_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'gop_contact_id' => ['nullable', 'exists:contacts,id'],
            'operation_contact_id' => ['nullable', 'exists:contacts,id'],
            'financial_contact_id' => ['nullable', 'exists:contacts,id'],
        ];
    }
}
