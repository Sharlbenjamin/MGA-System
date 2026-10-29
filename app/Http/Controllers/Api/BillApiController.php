<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\IndexRequest;
use App\Models\Bill;
use App\Models\BillItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BillApiController extends Controller
{
    public function index(IndexRequest $request): JsonResponse
    {
        $this->authorize('viewAny', Bill::class);

        $query = Bill::query()->with([
            'file:id,mga_reference,status',
            'provider:id,name',
        ]);

        if ($request->filled('search')) {
            $term = $request->input('search');
            $query->where(function ($q) use ($term) {
                $q->where('name', 'like', "%{$term}%")
                    ->orWhereHas('file', fn ($q) => $q->where('mga_reference', 'like', "%{$term}%"));
            });
        }
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }
        if ($request->filled('file_id')) {
            $query->where('file_id', $request->input('file_id'));
        }
        if ($request->filled('provider_id')) {
            $query->where('provider_id', $request->input('provider_id'));
        }

        $sort = $request->sortColumn();
        if (! in_array($sort, ['id', 'name', 'created_at', 'updated_at'], true)) {
            $sort = 'id';
        }

        return response()->json(
            $query->orderBy($sort, $request->sortDirection())->paginate($request->perPage())->withQueryString()
        );
    }

    public function show(int $id): JsonResponse
    {
        $bill = Bill::with(['file:id,mga_reference,status', 'provider:id,name', 'items'])->find($id);
        if (! $bill) {
            return response()->json(['message' => 'Bill not found'], 404);
        }
        $this->authorize('view', $bill);

        return response()->json($bill);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', Bill::class);

        $data = $request->validate([
            'file_id' => ['required', 'integer', 'exists:files,id'],
            'name' => ['nullable', 'string', 'max:255'],
            'provider_id' => ['nullable', 'integer', 'exists:providers,id'],
            'branch_id' => ['nullable', 'integer', 'exists:provider_branches,id'],
            'bank_account_id' => ['nullable', 'integer', 'exists:bank_accounts,id'],
            'status' => ['nullable', 'string', 'in:Unpaid,Partial,Paid'],
            'bill_google_link' => ['nullable', 'string', 'max:2048'],
            'payment_date' => ['nullable', 'date'],
        ]);
        $data['status'] = $data['status'] ?? 'Unpaid';

        $bill = Bill::create($data);

        return response()->json($bill->load(['file:id,mga_reference', 'provider:id,name', 'items']), 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $bill = Bill::find($id);
        if (! $bill) {
            return response()->json(['message' => 'Bill not found'], 404);
        }
        $this->authorize('update', $bill);

        $data = $request->validate([
            'name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'provider_id' => ['sometimes', 'nullable', 'integer', 'exists:providers,id'],
            'branch_id' => ['sometimes', 'nullable', 'integer', 'exists:provider_branches,id'],
            'bank_account_id' => ['sometimes', 'nullable', 'integer', 'exists:bank_accounts,id'],
            'status' => ['sometimes', 'string', 'in:Unpaid,Partial,Paid'],
            'bill_google_link' => ['sometimes', 'nullable', 'string', 'max:2048'],
            'payment_date' => ['sometimes', 'nullable', 'date'],
            'discount' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'paid_amount' => ['sometimes', 'nullable', 'numeric', 'min:0'],
        ]);

        $bill->update($data);

        return response()->json($bill->fresh(['file:id,mga_reference', 'provider:id,name', 'items']));
    }

    public function destroy(int $id): JsonResponse
    {
        $bill = Bill::find($id);
        if (! $bill) {
            return response()->json(['message' => 'Bill not found'], 404);
        }
        $this->authorize('delete', $bill);
        $bill->delete();

        return response()->json(['message' => 'Bill deleted']);
    }

    public function storeItem(Request $request, int $id): JsonResponse
    {
        $bill = Bill::find($id);
        if (! $bill) {
            return response()->json(['message' => 'Bill not found'], 404);
        }
        $this->authorize('update', $bill);

        $data = $request->validate([
            'description' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:0'],
            'discount' => ['nullable', 'numeric', 'min:0'],
            'tax' => ['nullable', 'numeric', 'min:0'],
        ]);

        $item = $bill->items()->create([
            'description' => $data['description'],
            'amount' => $data['amount'],
            'discount' => $data['discount'] ?? 0,
            'tax' => $data['tax'] ?? 0,
        ]);

        return response()->json($item, 201);
    }

    public function destroyItem(int $id, int $itemId): JsonResponse
    {
        $bill = Bill::find($id);
        if (! $bill) {
            return response()->json(['message' => 'Bill not found'], 404);
        }
        $this->authorize('update', $bill);
        $item = BillItem::where('bill_id', $bill->id)->find($itemId);
        if (! $item) {
            return response()->json(['message' => 'Bill item not found'], 404);
        }
        $item->delete();

        return response()->json(['message' => 'Bill item deleted']);
    }
}
