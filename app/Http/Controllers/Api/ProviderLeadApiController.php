<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ProviderLead;
use Illuminate\Http\JsonResponse;

class ProviderLeadApiController extends Controller
{
    /**
     * List provider leads (with provider, city).
     * Note: service_types is a string column on provider_leads; pivot provider_lead_service_type may not exist on all envs.
     */
    public function index(): JsonResponse
    {
        $leads = ProviderLead::with(['provider', 'city'])
            ->orderBy('id', 'desc')
            ->get();
        return response()->json($leads);
    }

    /**
     * Show a single provider lead.
     */
    public function show(int $id): JsonResponse
    {
        $lead = ProviderLead::with(['provider', 'city'])->find($id);
        if (!$lead) {
            return response()->json(['message' => 'Provider lead not found'], 404);
        }
        return response()->json($lead);
    }
}
