<?php

namespace App\Http\Controllers\Api;

use App\Filament\Resources\LeadResource;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\IndexRequest;
use App\Http\Requests\Api\StoreLeadRequest;
use App\Http\Requests\Api\UpdateLeadRequest;
use App\Models\Client;
use App\Models\DraftMail;
use App\Models\Lead;
use App\Services\LeadOverviewService;
use Illuminate\Http\JsonResponse;

class LeadApiController extends Controller
{
    public function overview(LeadOverviewService $overview): JsonResponse
    {
        $this->authorize('viewAny', Lead::class);

        return response()->json(['widgets' => $overview->widgets()]);
    }

    public function index(IndexRequest $request, LeadOverviewService $overview): JsonResponse
    {
        $this->authorize('viewAny', Lead::class);

        $query = Lead::query()
            ->with('client')
            ->whereRaw('LOWER(leads.status) != ?', ['error']);

        $overview->applyIndexFilters($query, $request->all());

        if ($request->filled('search')) {
            $term = $request->input('search');
            $query->where(function ($q) use ($term) {
                $q->where('first_name', 'like', "%{$term}%")
                    ->orWhere('email', 'like', "%{$term}%")
                    ->orWhereHas('client', fn ($q) => $q->where('company_name', 'like', "%{$term}%"));
            });
        }
        if ($request->filled('status')) {
            $query->where('leads.status', $request->input('status'));
        }
        if ($request->filled('ids')) {
            $query->whereIn('id', $request->input('ids'));
        }
        if ($request->filled('client_id')) {
            $query->where('client_id', $request->input('client_id'));
        }

        if ($request->filled('sort')) {
            $sort = $request->sortColumn();
            $dir = $request->sortDirection();
            $allowedSorts = ['id', 'first_name', 'created_at', 'updated_at', 'last_contact_date'];
            if (! in_array($sort, $allowedSorts, true)) {
                $sort = 'id';
            }
            $query->orderBy($sort, $dir);
        } else {
            $query->orderByRaw('leads.last_contact_date IS NULL')
                ->orderBy('leads.last_contact_date');
        }

        $paginator = $query->paginate($request->perPage())->withQueryString();
        $paginator->getCollection()->transform(fn (Lead $lead) => $this->presentLead($lead));

        return response()->json($paginator);
    }

    public function show(int $id): JsonResponse
    {
        $lead = Lead::with(['client', 'interactions.user:id,name'])->find($id);
        if (! $lead) {
            return response()->json(['message' => 'Lead not found'], 404);
        }
        $this->authorize('view', $lead);

        return response()->json($this->presentLead($lead));
    }

    public function store(StoreLeadRequest $request): JsonResponse
    {
        $data = $request->validated();
        if (! empty($data['create_new_client'])) {
            $companyName = (string) ($data['new_client_company_name'] ?? '');
            $initials = trim((string) ($data['new_client_initials'] ?? ''));
            if ($initials === '') {
                $initials = LeadResource::initialsFromCompanyName($companyName);
            }

            $client = Client::create([
                'company_name' => $companyName,
                'type' => $data['new_client_type'] ?? 'Assistance',
                'status' => $data['new_client_status'] ?? LeadResource::DEFAULT_CLIENT_STATUS,
                'initials' => $initials,
                'number_requests' => 0,
            ]);
            $data['client_id'] = $client->id;
        }

        unset(
            $data['create_new_client'],
            $data['new_client_company_name'],
            $data['new_client_type'],
            $data['new_client_status'],
            $data['new_client_initials'],
        );

        $lead = Lead::create($data);

        return response()->json($this->presentLead($lead->load('client')), 201);
    }

    public function update(UpdateLeadRequest $request, int $id): JsonResponse
    {
        $lead = Lead::find($id);
        if (! $lead) {
            return response()->json(['message' => 'Lead not found'], 404);
        }
        $lead->update($request->validated());

        return response()->json($this->presentLead($lead->fresh(['client', 'interactions.user:id,name'])));
    }

    public function destroy(int $id): JsonResponse
    {
        $lead = Lead::find($id);
        if (! $lead) {
            return response()->json(['message' => 'Lead not found'], 404);
        }
        $this->authorize('delete', $lead);
        $lead->delete();

        return response()->json(['message' => 'Lead deleted']);
    }

    public function sendEmail(int $id): JsonResponse
    {
        $lead = Lead::find($id);
        if (! $lead) {
            return response()->json(['message' => 'Lead not found'], 404);
        }
        $this->authorize('update', $lead);

        $draft = DraftMail::query()->where('status', $lead->status)->first();
        if (! $draft) {
            return response()->json(['message' => 'No draft email is set up for this lead status.'], 422);
        }

        LeadResource::sendEmails($lead);

        return response()->json($this->presentLead($lead->fresh(['client', 'interactions.user:id,name'])));
    }

    protected function presentLead(Lead $lead): Lead
    {
        $lead->setAttribute('follow_up_label', Lead::followUpCountdownLabel($lead->last_contact_date));
        $lead->setAttribute('follow_up_color', Lead::followUpCountdownColor($lead->last_contact_date));
        $lead->setAttribute('follow_up_days', Lead::followUpDaysRemaining($lead->last_contact_date));

        return $lead;
    }
}
