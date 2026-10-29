<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\IndexRequest;
use App\Models\Client;
use App\Models\Transaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ClientApiController extends Controller
{
    public function index(IndexRequest $request): JsonResponse
    {
        $this->authorize('viewAny', Client::class);

        $query = Client::query();

        if ($request->filled('search')) {
            $term = $request->input('search');
            $query->where(function ($q) use ($term) {
                $q->where('company_name', 'like', "%{$term}%")
                    ->orWhere('email', 'like', "%{$term}%")
                    ->orWhere('operation_email', 'like', "%{$term}%")
                    ->orWhere('initials', 'like', "%{$term}%");
            });
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
        $client = Client::with(['country', 'gopContact', 'operationContact', 'financialContact'])->find($id);
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

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', Client::class);
        $client = Client::create($this->clientData($request));

        return response()->json($client->load('country'), 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $client = Client::find($id);
        if (! $client) {
            return response()->json(['message' => 'Client not found'], 404);
        }
        $this->authorize('update', $client);
        $client->update($this->clientData($request, partial: true));

        return response()->json($client->fresh(['country', 'gopContact', 'operationContact', 'financialContact']));
    }

    public function destroy(int $id): JsonResponse
    {
        $client = Client::find($id);
        if (! $client) {
            return response()->json(['message' => 'Client not found'], 404);
        }
        $this->authorize('delete', $client);
        $client->delete();

        return response()->json(['message' => 'Client deleted']);
    }

    protected function clientData(Request $request, bool $partial = false): array
    {
        $required = $partial ? 'sometimes' : 'required';

        return $request->validate([
            'company_name' => [$required, 'string', 'max:255'],
            'type' => [$required, 'string', 'in:Assistance,Insurance,Agency'],
            'status' => [$required, 'string', Rule::in(array_keys(Client::statusOptions()))],
            'initials' => [$required, 'string', 'max:10'],
            'country_id' => ['nullable', 'integer', 'exists:countries,id'],
            'niv_number' => ['nullable', 'string', 'max:255'],
            'number_requests' => [$partial ? 'sometimes' : 'required', 'integer', 'min:0'],
            'gop_contact_id' => ['nullable', 'exists:contacts,id'],
            'operation_contact_id' => ['nullable', 'exists:contacts,id'],
            'financial_contact_id' => ['nullable', 'exists:contacts,id'],
            'phone' => ['nullable', 'string', 'max:255'],
            'linkedin_url' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'operation_email' => ['nullable', 'email', 'max:255'],
            'invoice_cc_emails' => ['nullable', 'array'],
            'invoice_cc_emails.*' => ['email'],
            'invoice_file_fee_strategy' => ['nullable', 'string', 'in:tier,multiplier'],
            'invoice_template' => ['nullable', 'string', 'in:itemized,combined'],
            'address' => ['nullable', 'string'],
            'comment' => ['nullable', 'string'],
        ]);
    }
}
