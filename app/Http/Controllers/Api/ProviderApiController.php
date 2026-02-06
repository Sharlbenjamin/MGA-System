<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\IndexRequest;
use App\Http\Requests\Api\StoreProviderRequest;
use App\Http\Requests\Api\UpdateProviderRequest;
use App\Models\Provider;
use Illuminate\Http\JsonResponse;

class ProviderApiController extends Controller
{
    public function index(IndexRequest $request): JsonResponse
    {
        $this->authorize('viewAny', Provider::class);

        $query = Provider::query()->with('country');

        if ($request->filled('search')) {
            $term = $request->input('search');
            $query->where('name', 'like', "%{$term}%")
                ->orWhere('email', 'like', "%{$term}%");
        }
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }
        if ($request->filled('ids')) {
            $query->whereIn('id', $request->input('ids'));
        }
        if ($request->filled('country_id')) {
            $query->where('country_id', $request->input('country_id'));
        }

        $sort = $request->sortColumn();
        $dir = $request->sortDirection();
        $allowedSorts = ['id', 'name', 'created_at', 'updated_at'];
        if (!in_array($sort, $allowedSorts)) {
            $sort = 'id';
        }
        $query->orderBy($sort, $dir);

        $paginator = $query->paginate($request->perPage())->withQueryString();

        return response()->json($paginator);
    }

    public function show(int $id): JsonResponse
    {
        $provider = Provider::with(['country', 'branches', 'bankAccounts'])->find($id);
        if (!$provider) {
            return response()->json(['message' => 'Provider not found'], 404);
        }
        $this->authorize('view', $provider);
        return response()->json($provider);
    }

    public function providerLeads(int $id): JsonResponse
    {
        $provider = Provider::find($id);
        if (!$provider) {
            return response()->json(['message' => 'Provider not found'], 404);
        }
        $this->authorize('view', $provider);
        return response()->json($provider->leads()->orderBy('id', 'desc')->get());
    }

    public function branches(int $id): JsonResponse
    {
        $provider = Provider::find($id);
        if (!$provider) {
            return response()->json(['message' => 'Provider not found'], 404);
        }
        $this->authorize('view', $provider);
        return response()->json($provider->branches()->with(['city'])->get());
    }

    /** Branches with their services (pivot: min_cost, max_cost). */
    public function branchServices(int $id): JsonResponse
    {
        $provider = Provider::find($id);
        if (!$provider) {
            return response()->json(['message' => 'Provider not found'], 404);
        }
        $this->authorize('view', $provider);
        $branches = $provider->branches()->with(['services' => fn ($q) => $q->withPivot(['min_cost', 'max_cost'])])->get();
        return response()->json($branches);
    }

    public function bills(int $id): JsonResponse
    {
        $provider = Provider::find($id);
        if (!$provider) {
            return response()->json(['message' => 'Provider not found'], 404);
        }
        $this->authorize('view', $provider);
        return response()->json($provider->bills()->get());
    }

    public function files(int $id): JsonResponse
    {
        $provider = Provider::find($id);
        if (!$provider) {
            return response()->json(['message' => 'Provider not found'], 404);
        }
        $this->authorize('view', $provider);
        return response()->json($provider->files()->with(['patient', 'serviceType'])->get());
    }

    public function bankAccounts(int $id): JsonResponse
    {
        $provider = Provider::find($id);
        if (!$provider) {
            return response()->json(['message' => 'Provider not found'], 404);
        }
        $this->authorize('view', $provider);
        return response()->json($provider->bankAccounts()->get());
    }

    public function store(StoreProviderRequest $request): JsonResponse
    {
        $provider = Provider::create($request->validated());
        return response()->json($provider->load('country'), 201);
    }

    public function update(UpdateProviderRequest $request, int $id): JsonResponse
    {
        $provider = Provider::find($id);
        if (!$provider) {
            return response()->json(['message' => 'Provider not found'], 404);
        }
        $provider->update($request->validated());
        return response()->json($provider->fresh('country'));
    }

    public function destroy(int $id): JsonResponse
    {
        $provider = Provider::find($id);
        if (!$provider) {
            return response()->json(['message' => 'Provider not found'], 404);
        }
        $this->authorize('delete', $provider);
        $provider->delete();
        return response()->json(['message' => 'Provider deleted']);
    }
}
