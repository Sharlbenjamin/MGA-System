<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ContactStoreRequest extends FormRequest
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
            'contactable_id' => ['required'],
            'contactable_type' => ['required', 'string'],
            'type' => ['required', 'in:client,provider,branch,patient'],
            'name' => ['required', 'string'],
            'title' => ['nullable', 'string'],
            'email' => ['nullable', 'email', 'unique:contacts,email'],
            'second_email' => ['nullable', 'string', 'unique:contacts,second_email'],
            'phone_number' => ['nullable', 'string'],
            'second_phone' => ['nullable', 'string'],
            'country_id' => ['required', 'integer', 'exists:countries,id'],
            'city_id' => ['required', 'integer', 'exists:cities,id'],
            'address' => ['nullable', 'string'],
            'preferred_contact' => ['required', 'in:phone,second'],
            'status' => ['required', 'in:active,inactive'],
        ];
    }
}
