<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\IndexRequest;
use App\Models\User;
use App\Models\UserSignature;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UserApiController extends Controller
{
    public function index(IndexRequest $request): JsonResponse
    {
        $this->authorize('viewAny', User::class);

        $query = User::query()->with(['roles:id,name', 'signature']);

        if ($request->filled('search')) {
            $term = $request->input('search');
            $query->where(function ($q) use ($term) {
                $q->where('name', 'like', "%{$term}%")
                    ->orWhere('email', 'like', "%{$term}%");
            });
        }
        if ($request->filled('role')) {
            $query->whereHas('roles', fn ($q) => $q->where('name', $request->input('role')));
        }

        $sort = $request->sortColumn();
        if (! in_array($sort, ['id', 'name', 'created_at', 'updated_at'], true)) {
            $sort = 'id';
        }

        $paginator = $query->orderBy($sort, $request->sortDirection())->paginate($request->perPage())->withQueryString();
        $paginator->getCollection()->transform(fn (User $user) => $this->present($user));

        return response()->json($paginator);
    }

    public function show(int $id): JsonResponse
    {
        $user = User::with(['roles:id,name', 'signature', 'employee:id,user_id,name,status,department'])->find($id);
        if (! $user) {
            return response()->json(['message' => 'User not found'], 404);
        }
        $this->authorize('view', $user);

        return response()->json($this->present($user));
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', User::class);
        $data = $this->validated($request);

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
            'smtp_username' => $data['smtp_username'] ?? null,
            'smtp_password' => $data['smtp_password'] ?? null,
        ]);
        $user->syncRoles($data['roles']);
        $this->saveSignature($user, $data['signature'] ?? null);

        return response()->json($this->present($user->load(['roles', 'signature'])), 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $user = User::find($id);
        if (! $user) {
            return response()->json(['message' => 'User not found'], 404);
        }
        $this->authorize('update', $user);
        $data = $this->validated($request, $user, partial: true);

        $payload = array_filter([
            'name' => $data['name'] ?? null,
            'email' => $data['email'] ?? null,
            'smtp_username' => $data['smtp_username'] ?? null,
        ], fn ($value) => $value !== null);

        if (! empty($data['password'])) {
            $payload['password'] = $data['password'];
        }
        if (array_key_exists('smtp_password', $data) && filled($data['smtp_password'])) {
            $payload['smtp_password'] = $data['smtp_password'];
        }
        if (array_key_exists('smtp_username', $data)) {
            $payload['smtp_username'] = $data['smtp_username'];
        }

        $user->update($payload);
        if (array_key_exists('roles', $data)) {
            $user->syncRoles($data['roles']);
        }
        if (array_key_exists('signature', $data)) {
            $this->saveSignature($user, $data['signature']);
        }

        return response()->json($this->present($user->fresh(['roles', 'signature'])));
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $user = User::find($id);
        if (! $user) {
            return response()->json(['message' => 'User not found'], 404);
        }
        $this->authorize('delete', $user);
        if ((int) $request->user()->id === (int) $user->id) {
            return response()->json(['message' => 'You cannot delete your own user.'], 422);
        }
        $user->delete();

        return response()->json(['message' => 'User deleted']);
    }

    protected function present(User $user): array
    {
        $user->makeHidden([
            'password',
            'remember_token',
            'smtp_password',
            'two_factor_secret',
            'two_factor_recovery_codes',
        ]);

        $payload = $user->toArray();
        $payload['roles'] = $user->roles->pluck('name')->values();
        unset($payload['smtp_password']);

        return $payload;
    }

    protected function saveSignature(User $user, ?array $signature): void
    {
        if ($signature === null) {
            return;
        }

        $filled = array_filter($signature, fn ($value) => filled($value));
        if ($filled === []) {
            return;
        }

        UserSignature::updateOrCreate(
            ['user_id' => $user->id],
            [
                'name' => $signature['name'] ?? $user->name,
                'job_title' => $signature['job_title'] ?? '',
                'department' => $signature['department'] ?? 'Operation',
                'work_phone' => $signature['work_phone'] ?? '',
            ],
        );
    }

    protected function validated(Request $request, ?User $user = null, bool $partial = false): array
    {
        $required = $partial ? 'sometimes' : 'required';

        return $request->validate([
            'name' => [$required, 'string', 'max:255'],
            'email' => [$required, 'email', 'max:255', Rule::unique('users', 'email')->ignore($user?->id)],
            'password' => [$partial ? 'nullable' : 'required', 'string', 'min:8'],
            'roles' => [$required, 'array', 'min:1'],
            'roles.*' => ['string', 'exists:roles,name'],
            'smtp_username' => ['nullable', 'string', 'max:255'],
            'smtp_password' => ['nullable', 'string', 'max:255'],
            'signature' => ['nullable', 'array'],
            'signature.name' => ['nullable', 'string', 'max:255'],
            'signature.job_title' => ['nullable', 'string', 'max:255'],
            'signature.department' => ['nullable', 'string', 'in:Operation,Financial,Provider Network,Client Network'],
            'signature.work_phone' => ['nullable', 'string', 'max:255'],
        ]);
    }
}
