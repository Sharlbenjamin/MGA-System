<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class StoreFileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', \App\Models\File::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'patient_id' => ['required', 'integer', 'exists:patients,id'],
            'mga_reference' => ['required', 'string', 'max:255', 'unique:files,mga_reference'],
            'service_type_id' => ['required', 'integer', 'exists:service_types,id'],
            'status' => ['required', 'string', 'in:New,Handling,Available,Confirmed,Assisted,Hold,Waiting MR,Refund,Cancelled,Void'],
            'client_reference' => ['nullable', 'string', 'max:255'],
            'country_id' => ['nullable', 'integer', 'exists:countries,id'],
            'city_id' => ['nullable', 'integer', 'exists:cities,id'],
            'provider_branch_id' => ['nullable', 'integer', 'exists:provider_branches,id'],
            'service_date' => ['nullable', 'date'],
            'service_time' => ['nullable', 'string'],
            'email' => ['nullable', 'email'],
            'phone' => ['nullable', 'string'],
            'address' => ['nullable', 'string'],
            'symptoms' => ['nullable', 'string'],
            'diagnosis' => ['nullable', 'string'],
            'contact_patient' => ['nullable', 'string', 'in:Client,MGA,Ask'],
        ];
    }
}
