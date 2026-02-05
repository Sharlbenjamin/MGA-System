<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\FileAssignRequest;
use App\Http\Requests\Api\FileRequestAppointmentRequest;
use App\Http\Requests\Api\IndexRequest;
use App\Http\Requests\Api\StoreFileRequest;
use App\Http\Requests\Api\UpdateFileRequest;
use App\Models\File;
use App\Services\CaseAssignmentService;
use Illuminate\Http\JsonResponse;

class FileApiController extends Controller
{
    public function index(IndexRequest $request): JsonResponse
    {
        $this->authorize('viewAny', File::class);

        $query = File::query()->with([
            'patient.client',
            'country',
            'city',
            'serviceType',
            'providerBranch',
            'currentAssignment.user:id,name',
        ]);

        if ($request->filled('search')) {
            $term = $request->input('search');
            $query->where(function ($q) use ($term) {
                $q->where('mga_reference', 'like', "%{$term}%")
                    ->orWhere('client_reference', 'like', "%{$term}%")
                    ->orWhereHas('patient', fn ($q) => $q->where('name', 'like', "%{$term}%"))
                    ->orWhereHas('patient.client', fn ($q) => $q->where('company_name', 'like', "%{$term}%"));
            });
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
        if ($request->filled('city_id')) {
            $query->where('city_id', $request->input('city_id'));
        }
        if ($request->filled('service_type_id')) {
            $query->where('service_type_id', $request->input('service_type_id'));
        }
        if ($request->filled('provider_id')) {
            $query->whereHas('providerBranch', fn ($q) => $q->where('provider_id', $request->input('provider_id')));
        }
        if ($request->filled('client_id')) {
            $query->whereHas('patient', fn ($q) => $q->where('client_id', $request->input('client_id')));
        }

        $sort = $request->sortColumn();
        $dir = $request->sortDirection();
        $allowedSorts = ['id', 'mga_reference', 'created_at', 'updated_at', 'service_date'];
        if (!in_array($sort, $allowedSorts)) {
            $sort = 'id';
        }
        $query->orderBy($sort, $dir);

        $paginator = $query->paginate($request->perPage())->withQueryString();

        return response()->json($paginator);
    }

    /**
     * File view: single file with all relation-manager data (for mobile).
     */
    public function show(int $id): JsonResponse
    {
        $file = File::with([
            'patient.client',
            'client',
            'country',
            'city',
            'serviceType',
            'providerBranch.provider',
            'currentAssignment.user:id,name,email',
            'gops',
            'bills',
            'medicalReports',
            'prescriptions',
            'comments.user:id,name',
            'appointments.providerBranch',
            'tasks',
            'fileAssignments.user:id,name',
            'invoices',
            'bankAccounts',
            'activityLogs.user:id,name',
        ])->find($id);

        if (!$file) {
            return response()->json(['message' => 'File not found'], 404);
        }
        $this->authorize('view', $file);
        return response()->json($file);
    }

    public function store(StoreFileRequest $request): JsonResponse
    {
        $file = File::create($request->validated());
        return response()->json($file->load([
            'patient.client', 'country', 'city', 'serviceType', 'providerBranch',
        ]), 201);
    }

    public function update(UpdateFileRequest $request, int $id): JsonResponse
    {
        $file = File::find($id);
        if (!$file) {
            return response()->json(['message' => 'File not found'], 404);
        }
        $this->authorize('update', $file);
        $file->update($request->validated());
        return response()->json($file->fresh([
            'patient.client', 'country', 'city', 'serviceType', 'providerBranch',
        ]));
    }

    public function destroy(int $id): JsonResponse
    {
        $file = File::find($id);
        if (!$file) {
            return response()->json(['message' => 'File not found'], 404);
        }
        $this->authorize('delete', $file);
        $file->delete();
        return response()->json(['message' => 'File deleted']);
    }

    /** POST /api/files/{id}/assign – assign case to user. */
    public function assign(FileAssignRequest $request, int $id): JsonResponse
    {
        $file = File::find($id);
        if (!$file) {
            return response()->json(['message' => 'File not found'], 404);
        }
        $user = \App\Models\User::find($request->input('user_id'));
        app(CaseAssignmentService::class)->assign($file, $user, $request->user());
        return response()->json([
            'message' => 'Case assigned',
            'assignment' => $file->fileAssignments()->with(['user:id,name', 'assignedBy:id,name'])->latest('assigned_at')->first(),
        ]);
    }

    /** POST /api/files/{id}/request-appointment – request appointment for file. */
    public function requestAppointment(FileRequestAppointmentRequest $request, int $id): JsonResponse
    {
        $file = File::find($id);
        if (!$file) {
            return response()->json(['message' => 'File not found'], 404);
        }
        $appointment = $file->appointments()->create([
            'provider_branch_id' => $request->input('provider_branch_id'),
            'service_date' => $request->input('service_date'),
            'service_time' => $request->input('service_time'),
            'status' => 'Requested',
        ]);
        if (!$appointment->exists) {
            return response()->json(['message' => 'Appointment could not be created (e.g. duplicate or branch contact missing)'], 422);
        }
        return response()->json($appointment->load('providerBranch'), 201);
    }

    /**
     * Relation manager: GOPs for a file.
     */
    public function gops(int $fileId): JsonResponse
    {
        $file = File::find($fileId);
        if (!$file) {
            return response()->json(['message' => 'File not found'], 404);
        }
        return response()->json($file->gops);
    }

    /**
     * Relation manager: Bills for a file.
     */
    public function bills(int $fileId): JsonResponse
    {
        $file = File::find($fileId);
        if (!$file) {
            return response()->json(['message' => 'File not found'], 404);
        }
        return response()->json($file->bills);
    }

    /**
     * Relation manager: Medical reports for a file.
     */
    public function medicalReports(int $fileId): JsonResponse
    {
        $file = File::find($fileId);
        if (!$file) {
            return response()->json(['message' => 'File not found'], 404);
        }
        return response()->json($file->medicalReports);
    }

    /**
     * Relation manager: Prescriptions for a file.
     */
    public function prescriptions(int $fileId): JsonResponse
    {
        $file = File::find($fileId);
        if (!$file) {
            return response()->json(['message' => 'File not found'], 404);
        }
        return response()->json($file->prescriptions);
    }

    /**
     * Relation manager: Comments for a file.
     */
    public function comments(int $fileId): JsonResponse
    {
        $file = File::find($fileId);
        if (!$file) {
            return response()->json(['message' => 'File not found'], 404);
        }
        return response()->json($file->comments()->with('user:id,name')->orderBy('created_at', 'desc')->get());
    }

    /**
     * Relation manager: Appointments for a file.
     */
    public function appointments(int $fileId): JsonResponse
    {
        $file = File::find($fileId);
        if (!$file) {
            return response()->json(['message' => 'File not found'], 404);
        }
        return response()->json($file->appointments()->with('providerBranch')->get());
    }

    /**
     * Relation manager: Tasks for a file.
     */
    public function tasks(int $fileId): JsonResponse
    {
        $file = File::find($fileId);
        if (!$file) {
            return response()->json(['message' => 'File not found'], 404);
        }
        return response()->json($file->tasks);
    }

    /**
     * Relation manager: Assignments for a file.
     */
    public function assignments(int $fileId): JsonResponse
    {
        $file = File::find($fileId);
        if (!$file) {
            return response()->json(['message' => 'File not found'], 404);
        }
        return response()->json($file->fileAssignments()->with(['user:id,name', 'assignedBy:id,name'])->get());
    }

    /**
     * Relation manager: Invoices for a file.
     */
    public function invoices(int $fileId): JsonResponse
    {
        $file = File::find($fileId);
        if (!$file) {
            return response()->json(['message' => 'File not found'], 404);
        }
        return response()->json($file->invoices);
    }

    /**
     * Relation manager: Bank accounts for a file.
     */
    public function bankAccounts(int $fileId): JsonResponse
    {
        $file = File::find($fileId);
        if (!$file) {
            return response()->json(['message' => 'File not found'], 404);
        }
        return response()->json($file->bankAccounts);
    }

    /**
     * Relation manager: Activity logs for a file.
     */
    public function activityLogs(int $fileId): JsonResponse
    {
        $file = File::find($fileId);
        if (!$file) {
            return response()->json(['message' => 'File not found'], 404);
        }
        return response()->json($file->activityLogs()->with('user:id,name')->latest()->get());
    }
}
