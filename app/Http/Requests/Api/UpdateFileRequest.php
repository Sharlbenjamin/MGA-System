<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class UpdateFileRequest extends FormRequest
{
    public function authorize(): bool
    {
        $id = $this->route('id');
        $model = $id ? \App\Models\File::find($id) : null;
        return $model && $this->user()?->can('update', $model);
    }

    public function rules(): array
    {
        return [
            'status' => ['sometimes', 'string', 'in:New,Handling,Available,Confirmed,Assisted,Hold,Waiting MR,Refund,Cancelled,Void'],
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
