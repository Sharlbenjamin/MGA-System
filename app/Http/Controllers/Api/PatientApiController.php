<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Patient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PatientApiController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Patient::class);

        $query = Patient::query()->with('client:id,company_name')->orderBy('name');
        if ($request->filled('search')) {
            $query->where('name', 'like', '%'.$request->input('search').'%');
        }
        if ($request->filled('client_id')) {
            $query->where('client_id', $request->integer('client_id'));
        }

        return response()->json($query->limit(40)->get());
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', Patient::class);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'client_id' => ['required', 'integer', 'exists:clients,id'],
            'dob' => ['nullable', 'date'],
            'gender' => ['nullable', 'string', 'in:Male,Female'],
            'country_id' => ['nullable', 'integer', 'exists:countries,id'],
        ]);

        $patient = Patient::create($data);

        return response()->json($patient->load('client:id,company_name'), 201);
    }
}
