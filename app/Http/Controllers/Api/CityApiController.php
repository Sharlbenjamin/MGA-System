<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\City;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CityApiController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = City::query()->orderBy('name');
        if ($request->filled('country_id')) {
            $query->where('country_id', $request->integer('country_id'));
        }
        if ($request->filled('search')) {
            $query->where('name', 'like', '%'.$request->input('search').'%');
        }

        return response()->json($query->limit(80)->get(['id', 'name', 'country_id']));
    }
}
