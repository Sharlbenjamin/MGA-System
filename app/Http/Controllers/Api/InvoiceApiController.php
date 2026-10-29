<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\IndexRequest;
use App\Models\File;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InvoiceApiController extends Controller
{
    public function index(IndexRequest $request): JsonResponse
    {
        $this->authorize('viewAny', Invoice::class);

        $query = Invoice::query()->with([
            'file:id,mga_reference,status,country_id,patient_id',
            'file.patient.client:id,company_name',
            'file.country:id,name,nicename',
            'patient:id,name',
        ]);

        if ($request->filled('search')) {
            $term = $request->input('search');
            $query->where(function ($q) use ($term) {
                $q->where('name', 'like', "%{$term}%")
                    ->orWhereHas('file', fn ($q) => $q->where('mga_reference', 'like', "%{$term}%"))
                    ->orWhereHas('patient', fn ($q) => $q->where('name', 'like', "%{$term}%"));
            });
        }
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }
        if ($request->filled('file_id')) {
            $query->where('file_id', $request->input('file_id'));
        }
        if ($request->filled('patient_id')) {
            $query->where('patient_id', $request->input('patient_id'));
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
        $invoice = Invoice::with(['file:id,mga_reference,status', 'patient:id,name', 'items'])->find($id);
        if (! $invoice) {
            return response()->json(['message' => 'Invoice not found'], 404);
        }
        $this->authorize('view', $invoice);

        return response()->json($invoice);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', Invoice::class);

        $data = $request->validate([
            'file_id' => ['required', 'integer', 'exists:files,id'],
            'patient_id' => ['nullable', 'integer', 'exists:patients,id'],
            'name' => ['nullable', 'string', 'max:255'],
            'bank_account_id' => ['nullable', 'integer', 'exists:bank_accounts,id'],
            'status' => ['nullable', 'string', 'in:Draft,Posted,Sent,Unpaid,Partial,Paid'],
            'invoice_date' => ['nullable', 'date'],
            'payment_link' => ['nullable', 'string', 'max:2048'],
            'discount' => ['nullable', 'numeric', 'min:0'],
            'tax' => ['nullable', 'numeric', 'min:0'],
        ]);

        if (empty($data['patient_id'])) {
            $data['patient_id'] = File::find($data['file_id'])?->patient_id;
        }
        $data['status'] = $data['status'] ?? 'Draft';

        $invoice = Invoice::create($data);

        return response()->json($invoice->load(['file:id,mga_reference', 'patient:id,name', 'items']), 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $invoice = Invoice::find($id);
        if (! $invoice) {
            return response()->json(['message' => 'Invoice not found'], 404);
        }
        $this->authorize('update', $invoice);

        $data = $request->validate([
            'patient_id' => ['sometimes', 'nullable', 'integer', 'exists:patients,id'],
            'name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'bank_account_id' => ['sometimes', 'nullable', 'integer', 'exists:bank_accounts,id'],
            'status' => ['sometimes', 'string', 'in:Draft,Posted,Sent,Unpaid,Partial,Paid'],
            'invoice_date' => ['sometimes', 'nullable', 'date'],
            'payment_date' => ['sometimes', 'nullable', 'date'],
            'payment_link' => ['sometimes', 'nullable', 'string', 'max:2048'],
            'discount' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'tax' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'paid_amount' => ['sometimes', 'nullable', 'numeric', 'min:0'],
        ]);

        $invoice->update($data);

        return response()->json($invoice->fresh(['file:id,mga_reference', 'patient:id,name', 'items']));
    }

    public function destroy(int $id): JsonResponse
    {
        $invoice = Invoice::find($id);
        if (! $invoice) {
            return response()->json(['message' => 'Invoice not found'], 404);
        }
        $this->authorize('delete', $invoice);
        $invoice->delete();

        return response()->json(['message' => 'Invoice deleted']);
    }

    public function storeItem(Request $request, int $id): JsonResponse
    {
        $invoice = Invoice::find($id);
        if (! $invoice) {
            return response()->json(['message' => 'Invoice not found'], 404);
        }
        $this->authorize('update', $invoice);

        $data = $request->validate([
            'description' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:0'],
            'discount' => ['nullable', 'numeric', 'min:0'],
            'tax' => ['nullable', 'numeric', 'min:0'],
            'item_type' => ['nullable', 'string', 'in:bill,file_fee'],
        ]);

        $item = $invoice->items()->create([
            'description' => $data['description'],
            'amount' => $data['amount'],
            'discount' => $data['discount'] ?? 0,
            'tax' => $data['tax'] ?? 0,
            'item_type' => $data['item_type'] ?? InvoiceItem::TYPE_BILL,
        ]);

        return response()->json($item, 201);
    }

    public function destroyItem(int $id, int $itemId): JsonResponse
    {
        $invoice = Invoice::find($id);
        if (! $invoice) {
            return response()->json(['message' => 'Invoice not found'], 404);
        }
        $this->authorize('update', $invoice);
        $item = InvoiceItem::where('invoice_id', $invoice->id)->find($itemId);
        if (! $item) {
            return response()->json(['message' => 'Invoice item not found'], 404);
        }
        $item->delete();

        return response()->json(['message' => 'Invoice item deleted']);
    }
}
