<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class FileRequestAppointmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $file = \App\Models\File::find($this->route('id'));
        return $file && $this->user()?->can('update', $file);
    }

    public function rules(): array
    {
        return [
            'provider_branch_id' => ['required', 'integer', 'exists:provider_branches,id'],
            'service_date' => ['required', 'date'],
            'service_time' => ['nullable', 'string'],
        ];
    }
}
