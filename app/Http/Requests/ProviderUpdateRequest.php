<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ProviderUpdateRequest extends FormRequest
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
            'country' => ['required', 'string', 'max:255'],
            'status' => ['required', 'in:Active,Hold,Potential,Black'],
            'type' => ['required', 'in:Doctor,Hospital,Clinic,Dental,Agency'],
            'name' => ['required', 'string', 'max:255', 'unique:providers,name'],
            'payment_due' => ['nullable', 'integer'],
            'payment_method' => ['nullable', 'in:Online'],
            'comment' => ['nullable', 'string'],
        ];
    }
}
