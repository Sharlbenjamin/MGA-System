<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\IndexRequest;
use App\Models\Client;
use App\Models\Transaction;
use Illuminate\Http\JsonResponse;

class ClientApiController extends Controller
{
    public function index(IndexRequest $request): JsonResponse
    {
        $this->authorize('viewAny', Client::class);

        $query = Client::query();

        if ($request->filled('search')) {
            $term = $request->input('search');
            $query->where('company_name', 'like', "%{$term}%")
                ->orWhere('email', 'like', "%{$term}%");
        }
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }
        if ($request->filled('ids')) {
            $query->whereIn('id', $request->input('ids'));
        }

        $sort = $request->sortColumn();
        $dir = $request->sortDirection();
        $allowedSorts = ['id', 'company_name', 'created_at', 'updated_at'];
        if (!in_array($sort, $allowedSorts)) {
            $sort = 'id';
        }
        $query->orderBy($sort, $dir);

        $paginator = $query->paginate($request->perPage())->withQueryString();

        return response()->json($paginator);
    }

    public function show(int $id): JsonResponse
    {
        $client = Client::find($id);
        if (!$client) {
            return response()->json(['message' => 'Client not found'], 404);
        }
        $this->authorize('view', $client);
        return response()->json($client);
    }

    public function files(int $id): JsonResponse
    {
        $client = Client::find($id);
        if (!$client) {
            return response()->json(['message' => 'Client not found'], 404);
        }
        $this->authorize('view', $client);
        return response()->json($client->files()->with(['patient', 'serviceType'])->get());
    }

    public function invoices(int $id): JsonResponse
    {
        $client = Client::find($id);
        if (!$client) {
            return response()->json(['message' => 'Client not found'], 404);
        }
        $this->authorize('view', $client);
        return response()->json($client->invoices()->get());
    }

    public function transactions(int $id): JsonResponse
    {
        $client = Client::find($id);
        if (!$client) {
            return response()->json(['message' => 'Client not found'], 404);
        }
        $this->authorize('view', $client);
        $items = Transaction::where('related_type', 'Client')->where('related_id', $id)->orderBy('id', 'desc')->get();
        return response()->json($items);
    }

    public function leads(int $id): JsonResponse
    {
        $client = Client::find($id);
        if (!$client) {
            return response()->json(['message' => 'Client not found'], 404);
        }
        $this->authorize('view', $client);
        return response()->json($client->leads()->orderBy('id', 'desc')->get());
    }
}
