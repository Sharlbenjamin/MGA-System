<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ProviderBranch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProviderBranchApiController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = ProviderBranch::query()->with('provider:id,name');
        $serviceTypeId = $request->integer('service_type_id');

        if ($request->filled('provider_id')) {
            $query->where('provider_id', $request->integer('provider_id'));
        }
        if ($serviceTypeId && $serviceTypeId !== 2 && $request->filled('city_id')) {
            $query->whereHas('branchCities', fn ($q) => $q->where('city_id', $request->integer('city_id')));
        }
        if ($serviceTypeId) {
            $query->whereHas('services', fn ($q) => $q->where('service_type_id', $serviceTypeId));
        }
        if ($request->filled('search')) {
            $term = $request->input('search');
            $query->where(function ($q) use ($term) {
                $q->where('branch_name', 'like', "%{$term}%")
                    ->orWhereHas('provider', fn ($q) => $q->where('name', 'like', "%{$term}%"));
            });
        }

        return response()->json(
            $query->orderBy('priority')->limit(80)->get(['id', 'branch_name', 'provider_id', 'city_id', 'status', 'priority', 'phone', 'email'])
        );
    }
}
