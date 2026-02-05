<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\IndexRequest;
use App\Http\Requests\Api\StoreProviderLeadRequest;
use App\Http\Requests\Api\UpdateProviderLeadRequest;
use App\Models\ProviderLead;
use Illuminate\Http\JsonResponse;

class ProviderLeadApiController extends Controller
{
    public function index(IndexRequest $request): JsonResponse
    {
        $this->authorize('viewAny', ProviderLead::class);

        $query = ProviderLead::query()->with(['provider', 'city']);

        if ($request->filled('search')) {
            $term = $request->input('search');
            $query->where(function ($q) use ($term) {
                $q->where('name', 'like', "%{$term}%")
                    ->orWhere('email', 'like', "%{$term}%")
                    ->orWhereHas('provider', fn ($q) => $q->where('name', 'like', "%{$term}%"));
            });
        }
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }
        if ($request->filled('ids')) {
            $query->whereIn('id', $request->input('ids'));
        }
        if ($request->filled('city_id')) {
            $query->where('city_id', $request->input('city_id'));
        }
        if ($request->filled('provider_id')) {
            $query->where('provider_id', $request->input('provider_id'));
        }

        $sort = $request->sortColumn();
        $dir = $request->sortDirection();
        $allowedSorts = ['id', 'name', 'created_at', 'updated_at', 'last_contact_date'];
        if (!in_array($sort, $allowedSorts)) {
            $sort = 'id';
        }
        $query->orderBy($sort, $dir);

        $paginator = $query->paginate($request->perPage())->withQueryString();

        return response()->json($paginator);
    }

    public function show(int $id): JsonResponse
    {
        $lead = ProviderLead::with(['provider', 'city'])->find($id);
        if (!$lead) {
            return response()->json(['message' => 'Provider lead not found'], 404);
        }
        $this->authorize('view', $lead);
        return response()->json($lead);
    }

    public function store(StoreProviderLeadRequest $request): JsonResponse
    {
        $data = $request->validated();
        $lead = ProviderLead::create($data);
        return response()->json($lead->load(['provider', 'city']), 201);
    }

    public function update(UpdateProviderLeadRequest $request, int $id): JsonResponse
    {
        $lead = ProviderLead::find($id);
        if (!$lead) {
            return response()->json(['message' => 'Provider lead not found'], 404);
        }
        $lead->update($request->validated());
        return response()->json($lead->fresh(['provider', 'city']));
    }

    public function destroy(int $id): JsonResponse
    {
        $lead = ProviderLead::find($id);
        if (!$lead) {
            return response()->json(['message' => 'Provider lead not found'], 404);
        }
        $this->authorize('delete', $lead);
        $lead->delete();
        return response()->json(['message' => 'Provider lead deleted']);
    }

    /** POST /api/provider-leads/{id}/convert – mark as converted / create provider from lead (stub). */
    public function convert(int $id): JsonResponse
    {
        $lead = ProviderLead::with(['provider', 'city'])->find($id);
        if (!$lead) {
            return response()->json(['message' => 'Provider lead not found'], 404);
        }
        $this->authorize('update', $lead);
        // Stub: in a full implementation you would create a Provider from the lead or set a converted_at.
        return response()->json([
            'message' => 'Convert action acknowledged. Use provider creation for full conversion.',
            'provider_lead' => $lead,
        ]);
    }
}
