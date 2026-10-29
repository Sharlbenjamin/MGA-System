<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\FileAssignRequest;
use App\Http\Requests\Api\FileRequestAppointmentRequest;
use App\Http\Requests\Api\IndexRequest;
use App\Http\Requests\Api\StoreFileRequest;
use App\Http\Requests\Api\UpdateFileRequest;
use App\Models\Comment;
use App\Models\File;
use App\Models\Gop;
use App\Models\Task;
use App\Services\CaseAssignmentService;
use App\Services\FileCaseStatusOverviewService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FileApiController extends Controller
{
    public function overview(FileCaseStatusOverviewService $overview): JsonResponse
    {
        $this->authorize('viewAny', File::class);

        return response()->json(['widgets' => $overview->widgets()]);
    }

    public function index(IndexRequest $request, FileCaseStatusOverviewService $overview): JsonResponse
    {
        $this->authorize('viewAny', File::class);

        $mobile = $request->boolean('mobile');
        $fileTable = (new File())->getTable();

        if ($mobile) {
            $query = File::query()->select($fileTable.'.*')->with([
                'patient:id,name,client_id',
                'patient.client:id,company_name',
                'country:id,name,nicename',
                'serviceType:id,name',
                'providerBranch:id,branch_name',
                'currentAssignment.user:id,name',
            ]);
        } else {
            $query = File::query()->select($fileTable.'.*')->with([
                'patient.client',
                'country',
                'city',
                'serviceType',
                'providerBranch.provider',
                'currentAssignment.user:id,name',
                'bills:id,file_id,total_amount,status,bill_document_path,bill_google_link',
            ])->addSelect([
                'last_comment' => Comment::select('content')
                    ->whereColumn('comments.file_id', $fileTable.'.id')
                    ->orderByDesc('created_at')
                    ->limit(1),
                'first_gop_in_amount' => Gop::select('amount')
                    ->whereColumn('gops.file_id', $fileTable.'.id')
                    ->where('type', 'In')
                    ->orderBy('id')
                    ->limit(1),
                'first_gop_in_has_doc' => DB::raw("COALESCE((SELECT CASE WHEN g2.document_path IS NOT NULL AND g2.document_path != '' THEN 1 ELSE 0 END FROM gops g2 WHERE g2.file_id = {$fileTable}.id AND g2.type = 'In' ORDER BY g2.id ASC LIMIT 1), 0)"),
            ]);
        }

        $overview->applyIndexFilters($query, $request->all());

        if ($mobile && ! $request->filled('status') && ! $this->requestHasStatuses($request) && ! $request->filled('search')) {
            $query->whereIn('status', FileCaseStatusOverviewService::OPENED_STATUSES);
        }

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

        $sort = $request->filled('sort') ? $request->sortColumn() : 'created_at';
        $dir = $request->sortDirection();
        $allowedSorts = ['id', 'mga_reference', 'created_at', 'updated_at', 'service_date'];
        if (! in_array($sort, $allowedSorts, true)) {
            $sort = 'created_at';
        }
        $query->orderBy($sort, $dir);

        $paginator = $query->paginate($request->perPage())->withQueryString();
        $paginator->getCollection()->transform(function (File $file) {
            $file->setAttribute(
                'assigned_name',
                $file->currentAssignment?->user?->name
                    ?? $file->current_assignment?->user?->name
            );

            return $file;
        });

        return response()->json($paginator);
    }

    protected function requestHasStatuses(IndexRequest $request): bool
    {
        $statuses = $request->input('statuses');
        if (! is_array($statuses)) {
            return false;
        }

        return array_values(array_filter($statuses, fn ($status) => is_string($status) && $status !== '')) !== [];
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

    public function nextReference(Request $request): JsonResponse
    {
        $this->authorize('create', File::class);
        $patientId = $request->input('patient_id');
        $clientId = $request->input('client_id');

        if ($patientId) {
            $reference = File::generateMGAReference($patientId, 'patient');
        } elseif ($clientId) {
            $reference = File::generateMGAReference($clientId, 'client');
        } else {
            return response()->json(['message' => 'patient_id or client_id is required'], 422);
        }

        return response()->json(['mga_reference' => $reference]);
    }

    public function storeComment(Request $request, int $fileId): JsonResponse
    {
        $file = File::find($fileId);
        if (! $file) {
            return response()->json(['message' => 'File not found'], 404);
        }
        $this->authorize('update', $file);
        $data = $request->validate([
            'content' => ['required', 'string', 'max:5000'],
        ]);
        $comment = $file->comments()->create([
            'user_id' => $request->user()->id,
            'content' => $data['content'],
        ]);

        return response()->json($comment->load('user:id,name'), 201);
    }

    public function storeTask(Request $request, int $fileId): JsonResponse
    {
        $file = File::find($fileId);
        if (! $file) {
            return response()->json(['message' => 'File not found'], 404);
        }
        $this->authorize('update', $file);
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'department' => ['required', 'string', 'in:Operation,Financial,Network,Network Providers'],
            'due_date' => ['nullable', 'date'],
        ]);
        $task = $file->tasks()->create([
            ...$data,
            'is_done' => false,
        ]);

        return response()->json($task->load('user:id,name'), 201);
    }

    public function toggleTask(Request $request, int $fileId, int $taskId): JsonResponse
    {
        $file = File::find($fileId);
        if (! $file) {
            return response()->json(['message' => 'File not found'], 404);
        }
        $this->authorize('update', $file);
        $task = Task::where('file_id', $file->id)->find($taskId);
        if (! $task) {
            return response()->json(['message' => 'Task not found'], 404);
        }
        $task->is_done = ! $task->is_done;
        $task->done_by = $task->is_done ? $request->user()->id : null;
        $task->save();

        return response()->json($task->fresh(['user:id,name', 'doneBy:id,name']));
    }
}
