<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class IndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'search' => ['sometimes', 'nullable', 'string', 'max:255'],
            'status' => ['sometimes', 'nullable', 'string', 'max:100'],
            'sort' => ['sometimes', 'string', 'in:id,created_at,updated_at,name,mga_reference,last_contact_date,service_date'],
            'direction' => ['sometimes', 'string', 'in:asc,desc'],
            'ids' => ['sometimes', 'array'],
            'ids.*' => ['integer'],
            'city_id' => ['sometimes', 'nullable', 'integer', 'exists:cities,id'],
            'country_id' => ['sometimes', 'nullable', 'integer', 'exists:countries,id'],
            'service_type_id' => ['sometimes', 'nullable', 'integer', 'exists:service_types,id'],
            'client_id' => ['sometimes', 'nullable', 'integer', 'exists:clients,id'],
            'provider_id' => ['sometimes', 'nullable', 'integer', 'exists:providers,id'],
        ];
    }

    public function perPage(): int
    {
        return min((int) $this->input('per_page', 20), 100);
    }

    public function sortColumn(): string
    {
        return $this->input('sort', 'id');
    }

    public function sortDirection(): string
    {
        return $this->input('direction', 'desc');
    }
}
