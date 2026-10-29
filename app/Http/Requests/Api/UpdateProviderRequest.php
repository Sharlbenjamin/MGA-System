<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProviderRequest extends FormRequest
{
    public function authorize(): bool
    {
        $id = $this->route('id');
        $model = $id ? \App\Models\Provider::find($id) : null;
        return $model && $this->user()?->can('update', $model);
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'type' => ['sometimes', 'string', 'in:Doctor,Hospital,Clinic,Dental,Agency'],
            'country_id' => ['sometimes', 'integer', 'exists:countries,id'],
            'status' => ['sometimes', 'string', 'in:Active,Hold,Potential,Searching,Black List,Black list'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:255'],
            'payment_due' => ['nullable', 'integer', 'min:0'],
            'payment_method' => ['nullable', 'string', 'in:Online Link,Bank Transfer,AEAT'],
            'needs_payment' => ['sometimes', 'boolean'],
            'comment' => ['nullable', 'string'],
            'request_comment' => ['nullable', 'string'],
            'assigned_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'gop_contact_id' => ['nullable', 'exists:contacts,id'],
            'operation_contact_id' => ['nullable', 'exists:contacts,id'],
            'financial_contact_id' => ['nullable', 'exists:contacts,id'],
        ];
    }
}
