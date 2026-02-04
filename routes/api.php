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

// File view, relation managers, client leads, provider leads (Bearer token required)
Route::middleware('auth:sanctum')->group(function () {
    // File view + relation managers
    Route::get('/files/{id}', [App\Http\Controllers\Api\FileApiController::class, 'show'])
        ->whereNumber('id');
    Route::get('/files/{fileId}/gops', [App\Http\Controllers\Api\FileApiController::class, 'gops'])
        ->whereNumber('fileId');
    Route::get('/files/{fileId}/bills', [App\Http\Controllers\Api\FileApiController::class, 'bills'])
        ->whereNumber('fileId');
    Route::get('/files/{fileId}/medical-reports', [App\Http\Controllers\Api\FileApiController::class, 'medicalReports'])
        ->whereNumber('fileId');
    Route::get('/files/{fileId}/prescriptions', [App\Http\Controllers\Api\FileApiController::class, 'prescriptions'])
        ->whereNumber('fileId');
    Route::get('/files/{fileId}/comments', [App\Http\Controllers\Api\FileApiController::class, 'comments'])
        ->whereNumber('fileId');
    Route::get('/files/{fileId}/appointments', [App\Http\Controllers\Api\FileApiController::class, 'appointments'])
        ->whereNumber('fileId');
    Route::get('/files/{fileId}/tasks', [App\Http\Controllers\Api\FileApiController::class, 'tasks'])
        ->whereNumber('fileId');
    Route::get('/files/{fileId}/assignments', [App\Http\Controllers\Api\FileApiController::class, 'assignments'])
        ->whereNumber('fileId');
    Route::get('/files/{fileId}/invoices', [App\Http\Controllers\Api\FileApiController::class, 'invoices'])
        ->whereNumber('fileId');
    Route::get('/files/{fileId}/bank-accounts', [App\Http\Controllers\Api\FileApiController::class, 'bankAccounts'])
        ->whereNumber('fileId');
    Route::get('/files/{fileId}/activity-logs', [App\Http\Controllers\Api\FileApiController::class, 'activityLogs'])
        ->whereNumber('fileId');

    // Client leads
    Route::get('/leads', [App\Http\Controllers\Api\LeadApiController::class, 'index']);
    Route::get('/leads/{id}', [App\Http\Controllers\Api\LeadApiController::class, 'show'])
        ->whereNumber('id');

    // Provider leads
    Route::get('/provider-leads', [App\Http\Controllers\Api\ProviderLeadApiController::class, 'index']);
    Route::get('/provider-leads/{id}', [App\Http\Controllers\Api\ProviderLeadApiController::class, 'show'])
        ->whereNumber('id');
});
