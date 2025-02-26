<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ProviderBranchUpdateRequest extends FormRequest
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
            'provider_id' => ['required', 'integer', 'exists:providers,id'],
            'city' => ['required', 'string', 'max:255'],
            'status' => ['required', 'in:Active,Hold'],
            'priority' => ['required', 'integer'],
            'service_type_id' => ['required', 'integer', 'exists:service_types,id'],
            'communication_method' => ['required', 'string', 'max:50'],
            'day_cost' => ['required', 'numeric', 'between:-999999.99,999999.99'],
            'night_cost' => ['required', 'numeric', 'between:-999999.99,999999.99'],
            'weekend_cost' => ['required', 'numeric', 'between:-999999.99,999999.99'],
            'weekend_night_cost' => ['required', 'numeric', 'between:-999999.99,999999.99'],
        ];
    }
}
