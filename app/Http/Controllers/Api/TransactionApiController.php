<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\IndexRequest;
use App\Models\Transaction;
use App\Models\User;
use App\Services\TransactionDocumentationStatsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TransactionApiController extends Controller
{
    public function index(IndexRequest $request): JsonResponse
    {
        $this->authorize('viewAny', Transaction::class);

        $query = Transaction::query()->with(['bankAccount:id,beneficiary_name,type']);

        if (! $this->seesAllTypes($request->user())) {
            $query->where('type', 'Outflow');
        }
        if ($request->filled('search')) {
            $term = $request->input('search');
            $query->where(function ($q) use ($term) {
                $q->where('name', 'like', "%{$term}%")
                    ->orWhere('reference', 'like', "%{$term}%")
                    ->orWhere('notes', 'like', "%{$term}%");
            });
        }
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }
        if ($request->filled('type')) {
            $query->where('type', $request->input('type'));
        }
        if ($request->filled('related_type')) {
            $query->where('related_type', $request->input('related_type'));
        }
        if ($request->filled('related_id')) {
            $query->where('related_id', $request->input('related_id'));
        }

        $sort = $request->sortColumn();
        if (! in_array($sort, ['id', 'name', 'created_at', 'updated_at', 'date'], true)) {
            $sort = 'id';
        }

        $paginator = $query->orderBy($sort, $request->sortDirection())->paginate($request->perPage())->withQueryString();
        $paginator->getCollection()->each(function (Transaction $transaction) {
            $transaction->setAttribute('related_label', $transaction->getRelatedPartyLabel());
        });

        return response()->json($paginator);
    }

    public function show(int $id): JsonResponse
    {
        $transaction = Transaction::with(['bankAccount', 'createdByUser:id,name', 'updatedByUser:id,name'])->find($id);
        if (! $transaction) {
            return response()->json(['message' => 'Transaction not found'], 404);
        }
        $this->authorize('view', $transaction);
        $transaction->setAttribute('related_label', $transaction->getRelatedPartyLabel());

        return response()->json($transaction);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', Transaction::class);
        $data = $this->validated($request);
        $data['created_by'] = $request->user()->id;
        $data['updated_by'] = $request->user()->id;

        $transaction = Transaction::create($data);
        $transaction->setAttribute('related_label', $transaction->getRelatedPartyLabel());

        return response()->json($transaction->load('bankAccount'), 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $transaction = Transaction::find($id);
        if (! $transaction) {
            return response()->json(['message' => 'Transaction not found'], 404);
        }
        $this->authorize('update', $transaction);

        $data = $this->validated($request, partial: true);
        $data['updated_by'] = $request->user()->id;
        $transaction->update($data);
        $fresh = $transaction->fresh('bankAccount');
        $fresh->setAttribute('related_label', $fresh->getRelatedPartyLabel());

        return response()->json($fresh);
    }

    public function destroy(int $id): JsonResponse
    {
        $transaction = Transaction::find($id);
        if (! $transaction) {
            return response()->json(['message' => 'Transaction not found'], 404);
        }
        $this->authorize('delete', $transaction);
        $transaction->delete();

        return response()->json(['message' => 'Transaction deleted']);
    }

    protected function seesAllTypes(User $user): bool
    {
        return $user->hasAnyRole([
            'admin',
            'Admin',
            'financial',
            'Financial',
            'financial manager',
            'Financial Manager',
            'manager',
            'Manager',
        ]);
    }

    protected function validated(Request $request, bool $partial = false): array
    {
        $categories = array_keys(TransactionDocumentationStatsService::allCategoryOptions());
        $required = $partial ? 'sometimes' : 'required';

        return $request->validate([
            'name' => ['nullable', 'string', 'max:255'],
            'type' => [$required, 'string', 'in:Income,Outflow,Expense'],
            'related_type' => [$required, 'string', 'max:50'],
            'related_id' => ['nullable', 'integer'],
            'bank_account_id' => ['nullable', 'integer', 'exists:bank_accounts,id'],
            'amount' => [$required, 'numeric'],
            'date' => [$required, 'date'],
            'notes' => ['nullable', 'string'],
            'bank_charges' => ['nullable', 'numeric', 'min:0'],
            'charges_covered_by_client' => ['nullable', 'boolean'],
            'status' => [$required, 'string', 'in:Completed,Draft,Pending'],
            'reference' => ['nullable', 'string', 'max:255'],
            'documentation_category' => ['nullable', 'string', 'in:'.implode(',', $categories)],
        ]);
    }
}
