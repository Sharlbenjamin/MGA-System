<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\MobileDashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardApiController extends Controller
{
    public function index(Request $request, MobileDashboardService $dashboard): JsonResponse
    {
        $user = $request->user();
        $user->loadMissing('roles');

        return response()->json(
            $dashboard->forUser($user, $request->only(['duration', 'month', 'year', 'date'])),
        );
    }
}
