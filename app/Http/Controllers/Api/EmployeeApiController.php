<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\IndexRequest;
use App\Models\Employee;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class EmployeeApiController extends Controller
{
    public function index(IndexRequest $request): JsonResponse
    {
        $this->authorize('viewAny', Employee::class);

        $query = Employee::query()->with(['user:id,name,email', 'jobTitle:id,name', 'manager:id,name']);

        if ($request->filled('search')) {
            $term = $request->input('search');
            $query->where(function ($q) use ($term) {
                $q->where('name', 'like', "%{$term}%")
                    ->orWhere('phone', 'like', "%{$term}%")
                    ->orWhere('department', 'like', "%{$term}%");
            });
        }
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }
        if ($request->filled('department')) {
            $query->where('department', $request->input('department'));
        }

        $sort = $request->sortColumn();
        if (! in_array($sort, ['id', 'name', 'created_at', 'updated_at'], true)) {
            $sort = 'id';
        }

        $paginator = $query->orderBy($sort, $request->sortDirection())->paginate($request->perPage())->withQueryString();
        $paginator->getCollection()->each(fn (Employee $employee) => $this->present($employee));

        return response()->json($paginator);
    }

    public function show(int $id): JsonResponse
    {
        $employee = Employee::with(['user:id,name,email', 'jobTitle:id,name', 'manager:id,name', 'bankAccount:id,beneficiary_name'])->find($id);
        if (! $employee) {
            return response()->json(['message' => 'Employee not found'], 404);
        }
        $this->authorize('view', $employee);

        return response()->json($this->present($employee));
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', Employee::class);
        $data = $this->validated($request);
        $data = $this->storeFiles($request, $data);
        $employee = Employee::create($data);

        return response()->json($this->present($employee->load(['user:id,name', 'jobTitle:id,name', 'manager:id,name'])), 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $employee = Employee::find($id);
        if (! $employee) {
            return response()->json(['message' => 'Employee not found'], 404);
        }
        $this->authorize('update', $employee);
        $data = $this->validated($request, partial: true);
        $data = $this->storeFiles($request, $data);
        $employee->update($data);

        return response()->json($this->present($employee->fresh(['user:id,name', 'jobTitle:id,name', 'manager:id,name', 'bankAccount:id,beneficiary_name'])));
    }

    public function destroy(int $id): JsonResponse
    {
        $employee = Employee::find($id);
        if (! $employee) {
            return response()->json(['message' => 'Employee not found'], 404);
        }
        $this->authorize('delete', $employee);
        $employee->delete();

        return response()->json(['message' => 'Employee deleted']);
    }

    protected function present(Employee $employee): Employee
    {
        $employee->setAttribute('cv_url', $this->publicUrl($employee->cv_path));
        $employee->setAttribute('photo_id_url', $this->publicUrl($employee->photo_id_path));
        $employee->setAttribute('signed_contract_url', $this->publicUrl($employee->signed_contract_path));

        return $employee;
    }

    protected function publicUrl(?string $path): ?string
    {
        if (! filled($path)) {
            return null;
        }
        if (preg_match('/^https?:\/\//i', $path) === 1) {
            return $path;
        }

        return Storage::disk('public')->url($path);
    }

    protected function storeFiles(Request $request, array $data): array
    {
        foreach ([
            'cv' => ['column' => 'cv_path', 'directory' => 'employee-cvs'],
            'photo_id' => ['column' => 'photo_id_path', 'directory' => 'employee-photo-ids'],
            'signed_contract_file' => ['column' => 'signed_contract_path', 'directory' => 'employee-contracts'],
        ] as $input => $target) {
            if ($request->hasFile($input)) {
                $data[$target['column']] = $request->file($input)->store($target['directory'], 'public');
            }
            unset($data[$input]);
        }

        return $data;
    }

    protected function validated(Request $request, bool $partial = false): array
    {
        $required = $partial ? 'sometimes' : 'required';

        return $request->validate([
            'name' => [$required, 'string', 'max:255'],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'job_title_id' => [$required, 'integer', 'exists:job_titles,id'],
            'manager_id' => ['nullable', 'integer', 'exists:employees,id'],
            'bank_account_id' => ['nullable', 'integer', 'exists:bank_accounts,id'],
            'department' => [$required, 'string', 'in:Operation,Financial,Client Network,Provider Network'],
            'status' => [$required, 'string', 'in:interviewing,active,former'],
            'date_of_birth' => ['nullable', 'date'],
            'gender' => ['nullable', 'string', 'in:female,male,other'],
            'national_id' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:255'],
            'basic_salary' => [$partial ? 'sometimes' : 'required', 'numeric', 'min:0'],
            'full_salary' => ['nullable', 'numeric', 'min:0'],
            'social_insurance_salary' => ['nullable', 'numeric', 'min:0'],
            'expected_salary' => ['nullable', 'numeric', 'min:0'],
            'offered_salary' => ['nullable', 'numeric', 'min:0'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date'],
            'interview_date' => ['nullable', 'date'],
            'social_insurance_number' => ['nullable', 'string', 'max:255'],
            'linkedin_url' => ['nullable', 'string', 'max:255'],
            'employment_type' => ['nullable', 'string', 'in:full_time,part_time'],
            'notice_period' => ['nullable', 'string', 'max:255'],
            'english_level' => ['nullable', 'string', 'in:basic,good,fluent,native'],
            'flexible_shifts' => ['nullable', 'boolean'],
            'reason_for_leaving' => ['nullable', 'string'],
            'has_laptop' => ['nullable', 'boolean'],
            'signed_contract' => ['nullable', 'boolean'],
            'cv' => ['nullable', 'file', 'max:10240'],
            'photo_id' => ['nullable', 'file', 'max:10240'],
            'signed_contract_file' => ['nullable', 'file', 'max:10240'],
        ]);
    }
}
