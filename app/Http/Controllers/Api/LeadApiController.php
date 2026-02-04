<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Lead;
use Illuminate\Http\JsonResponse;

class LeadApiController extends Controller
{
    /**
     * List client leads (with client).
     */
    public function index(): JsonResponse
    {
        $leads = Lead::with('client')->orderBy('id', 'desc')->get();
        return response()->json($leads);
    }

    /**
     * Show a single client lead.
     */
    public function show(int $id): JsonResponse
    {
        $lead = Lead::with('client')->find($id);
        if (!$lead) {
            return response()->json(['message' => 'Lead not found'], 404);
        }
        return response()->json($lead);
    }
}
