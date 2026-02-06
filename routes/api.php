<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Validator;
use App\Http\Controllers\PatientController;

// GET /api/login – hint (login requires POST)
Route::get('/login', function () {
    return response()->json([
        'message' => 'Use POST with JSON body: email, password, device_name (optional)',
    ], 405);
});

// POST /api/login – authenticate and issue Sanctum token
Route::post('/login', function (Request $request) {
    $validator = Validator::make($request->all(), [
        'email' => ['required', 'email'],
        'password' => ['required'],
        'device_name' => ['nullable', 'string'],
    ]);

    if ($validator->fails()) {
        return response()->json(['message' => $validator->errors()->first()], 422);
    }

    if (!Auth::attempt($request->only('email', 'password'))) {
        return response()->json(['message' => 'Invalid credentials'], 422);
    }

    $user = Auth::user();
    $token = $user->createToken($request->input('device_name', 'mobile'))->plainTextToken;

    return response()->json([
        'token' => $token,
        'user' => [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
        ],
    ]);
});

// GET /api/user – current authenticated user (Bearer token)
Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// POST /api/logout – revoke current token
Route::post('/logout', function (Request $request) {
    $request->user()->currentAccessToken()->delete();

    return response()->json(['message' => 'Logged out']);
})->middleware('auth:sanctum');

// Patient API routes (no auth)
Route::prefix('patients')->group(function () {
    Route::get('/search-similar', [PatientController::class, 'searchSimilar']);
    Route::post('/check-duplicate', [PatientController::class, 'checkDuplicate']);
});

// Authenticated API: files, leads, provider-leads, providers (pagination, filters, CRUD, actions)
Route::middleware('auth:sanctum')->group(function () {
    $file = App\Http\Controllers\Api\FileApiController::class;
    $lead = App\Http\Controllers\Api\LeadApiController::class;
    $providerLead = App\Http\Controllers\Api\ProviderLeadApiController::class;
    $provider = App\Http\Controllers\Api\ProviderApiController::class;
    $client = App\Http\Controllers\Api\ClientApiController::class;

    // Files (list, CRUD, actions, relation managers)
    Route::get('/files', [$file, 'index']);
    Route::post('/files', [$file, 'store']);
    Route::get('/files/{id}', [$file, 'show'])->whereNumber('id');
    Route::match(['patch', 'put'], '/files/{id}', [$file, 'update'])->whereNumber('id');
    Route::delete('/files/{id}', [$file, 'destroy'])->whereNumber('id');
    Route::post('/files/{id}/assign', [$file, 'assign'])->whereNumber('id');
    Route::post('/files/{id}/request-appointment', [$file, 'requestAppointment'])->whereNumber('id');
    Route::get('/files/{fileId}/gops', [$file, 'gops'])->whereNumber('fileId');
    Route::get('/files/{fileId}/bills', [$file, 'bills'])->whereNumber('fileId');
    Route::get('/files/{fileId}/medical-reports', [$file, 'medicalReports'])->whereNumber('fileId');
    Route::get('/files/{fileId}/prescriptions', [$file, 'prescriptions'])->whereNumber('fileId');
    Route::get('/files/{fileId}/comments', [$file, 'comments'])->whereNumber('fileId');
    Route::get('/files/{fileId}/appointments', [$file, 'appointments'])->whereNumber('fileId');
    Route::get('/files/{fileId}/tasks', [$file, 'tasks'])->whereNumber('fileId');
    Route::get('/files/{fileId}/assignments', [$file, 'assignments'])->whereNumber('fileId');
    Route::get('/files/{fileId}/invoices', [$file, 'invoices'])->whereNumber('fileId');
    Route::get('/files/{fileId}/bank-accounts', [$file, 'bankAccounts'])->whereNumber('fileId');
    Route::get('/files/{fileId}/activity-logs', [$file, 'activityLogs'])->whereNumber('fileId');

    // Client leads
    Route::get('/leads', [$lead, 'index']);
    Route::post('/leads', [$lead, 'store']);
    Route::get('/leads/{id}', [$lead, 'show'])->whereNumber('id');
    Route::match(['patch', 'put'], '/leads/{id}', [$lead, 'update'])->whereNumber('id');
    Route::delete('/leads/{id}', [$lead, 'destroy'])->whereNumber('id');

    // Provider leads
    Route::get('/provider-leads', [$providerLead, 'index']);
    Route::post('/provider-leads', [$providerLead, 'store']);
    Route::get('/provider-leads/{id}', [$providerLead, 'show'])->whereNumber('id');
    Route::match(['patch', 'put'], '/provider-leads/{id}', [$providerLead, 'update'])->whereNumber('id');
    Route::delete('/provider-leads/{id}', [$providerLead, 'destroy'])->whereNumber('id');
    Route::post('/provider-leads/{id}/convert', [$providerLead, 'convert'])->whereNumber('id');

    // Providers + relation managers
    Route::get('/providers', [$provider, 'index']);
    Route::post('/providers', [$provider, 'store']);
    Route::get('/providers/{id}', [$provider, 'show'])->whereNumber('id');
    Route::match(['patch', 'put'], '/providers/{id}', [$provider, 'update'])->whereNumber('id');
    Route::delete('/providers/{id}', [$provider, 'destroy'])->whereNumber('id');
    Route::get('/providers/{id}/provider-leads', [$provider, 'providerLeads'])->whereNumber('id');
    Route::get('/providers/{id}/branches', [$provider, 'branches'])->whereNumber('id');
    Route::get('/providers/{id}/branch-services', [$provider, 'branchServices'])->whereNumber('id');
    Route::get('/providers/{id}/bills', [$provider, 'bills'])->whereNumber('id');
    Route::get('/providers/{id}/files', [$provider, 'files'])->whereNumber('id');
    Route::get('/providers/{id}/bank-accounts', [$provider, 'bankAccounts'])->whereNumber('id');

    // Clients + relation managers
    Route::get('/clients', [$client, 'index']);
    Route::get('/clients/{id}', [$client, 'show'])->whereNumber('id');
    Route::get('/clients/{id}/files', [$client, 'files'])->whereNumber('id');
    Route::get('/clients/{id}/invoices', [$client, 'invoices'])->whereNumber('id');
    Route::get('/clients/{id}/transactions', [$client, 'transactions'])->whereNumber('id');
    Route::get('/clients/{id}/leads', [$client, 'leads'])->whereNumber('id');
});
