<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\IndexRequest;
use App\Http\Requests\Api\StoreLeadRequest;
use App\Http\Requests\Api\UpdateLeadRequest;
use App\Models\Lead;
use Illuminate\Http\JsonResponse;

class LeadApiController extends Controller
{
    public function index(IndexRequest $request): JsonResponse
    {
        $this->authorize('viewAny', Lead::class);

        $query = Lead::query()->with('client');

        if ($request->filled('search')) {
            $term = $request->input('search');
            $query->where(function ($q) use ($term) {
                $q->where('first_name', 'like', "%{$term}%")
                    ->orWhere('email', 'like', "%{$term}%")
                    ->orWhereHas('client', fn ($q) => $q->where('company_name', 'like', "%{$term}%"));
            });
        }
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }
        if ($request->filled('ids')) {
            $query->whereIn('id', $request->input('ids'));
        }
        if ($request->filled('client_id')) {
            $query->where('client_id', $request->input('client_id'));
        }

        $sort = $request->sortColumn();
        $dir = $request->sortDirection();
        $allowedSorts = ['id', 'first_name', 'created_at', 'updated_at', 'last_contact_date'];
        if (!in_array($sort, $allowedSorts)) {
            $sort = 'id';
        }
        $query->orderBy($sort, $dir);

        $paginator = $query->paginate($request->perPage())->withQueryString();

        return response()->json($paginator);
    }

    public function show(int $id): JsonResponse
    {
        $lead = Lead::with('client')->find($id);
        if (!$lead) {
            return response()->json(['message' => 'Lead not found'], 404);
        }
        $this->authorize('view', $lead);
        return response()->json($lead);
    }

    public function store(StoreLeadRequest $request): JsonResponse
    {
        $lead = Lead::create($request->validated());
        return response()->json($lead->load('client'), 201);
    }

    public function update(UpdateLeadRequest $request, int $id): JsonResponse
    {
        $lead = Lead::find($id);
        if (!$lead) {
            return response()->json(['message' => 'Lead not found'], 404);
        }
        $lead->update($request->validated());
        return response()->json($lead->fresh('client'));
    }

    public function destroy(int $id): JsonResponse
    {
        $lead = Lead::find($id);
        if (!$lead) {
            return response()->json(['message' => 'Lead not found'], 404);
        }
        $this->authorize('delete', $lead);
        $lead->delete();
        return response()->json(['message' => 'Lead deleted']);
    }
}
