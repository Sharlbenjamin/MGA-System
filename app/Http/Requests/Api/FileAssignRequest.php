<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class FileAssignRequest extends FormRequest
{
    public function authorize(): bool
    {
        $file = \App\Models\File::find($this->route('id'));
        return $file && $this->user()?->can('update', $file);
    }

    public function rules(): array
    {
        return [
            'user_id' => ['required', 'integer', 'exists:users,id'],
        ];
    }
}
