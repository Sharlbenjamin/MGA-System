<?php

namespace App\Http\Controllers;

use App\Models\Patient;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class PatientController extends Controller
{
    /**
     * Search for similar patients
     */
    public function searchSimilar(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|min:2',
            'client_id' => 'nullable|integer|exists:clients,id'
        ]);

        $name = $request->input('name');
        $clientId = $request->input('client_id');

        $similarPatients = Patient::findSimilar($name, $clientId, 10);

        return response()->json([
            'success' => true,
            'data' => $similarPatients->map(function ($patient) {
                return [
                    'id' => $patient->id,
                    'name' => $patient->name,
                    'client_name' => $patient->client->company_name,
                    'dob' => $patient->dob?->format('d/m/Y'),
                    'gender' => $patient->gender,
                    'files_count' => $patient->files_count
                ];
            })
        ]);
    }

    /**
     * Check if a patient is a duplicate
     */
    public function checkDuplicate(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string',
            'client_id' => 'required|integer|exists:clients,id'
        ]);

        $name = $request->input('name');
        $clientId = $request->input('client_id');

        $duplicate = Patient::findDuplicate($name, $clientId);

        return response()->json([
            'success' => true,
            'is_duplicate' => $duplicate !== null,
            'duplicate_patient' => $duplicate ? [
                'id' => $duplicate->id,
                'name' => $duplicate->name,
                'client_name' => $duplicate->client->company_name,
                'dob' => $duplicate->dob?->format('d/m/Y'),
                'gender' => $duplicate->gender,
                'files_count' => $duplicate->files_count
            ] : null
        ]);
    }
} 