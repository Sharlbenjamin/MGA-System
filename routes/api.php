<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Validator;
use App\Http\Controllers\PatientController;

Route::get('/health', function () {
    return response()->json([
        'ok' => true,
        'service' => 'mga-api',
        'env' => app()->environment(),
    ]);
});

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
        'user' => \App\Support\MobileUserPayload::from($user),
    ]);
})->middleware('throttle:10,1');

// GET /api/user – current authenticated user (Bearer token)
Route::get('/user', function (Request $request) {
    return \App\Support\MobileUserPayload::from($request->user());
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
    $communication = App\Http\Controllers\Api\CommunicationApiController::class;

    // Files (list, CRUD, actions, relation managers)
    Route::get('/files/overview', [$file, 'overview']);
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
    Route::get('/files/next-reference', [$file, 'nextReference']);
    Route::post('/files/{fileId}/comments', [$file, 'storeComment'])->whereNumber('fileId');
    Route::post('/files/{fileId}/tasks', [$file, 'storeTask'])->whereNumber('fileId');
    Route::post('/files/{fileId}/tasks/{taskId}/toggle', [$file, 'toggleTask'])->whereNumber('fileId')->whereNumber('taskId');
    Route::get('/files/{fileId}/communications', [$communication, 'fileThreads'])->whereNumber('fileId');
    Route::post('/files/{fileId}/communications/link', [$communication, 'linkForFile'])->whereNumber('fileId');

    // Client leads
    Route::get('/leads/overview', [$lead, 'overview']);
    Route::get('/leads', [$lead, 'index']);
    Route::post('/leads', [$lead, 'store']);
    Route::get('/leads/{id}', [$lead, 'show'])->whereNumber('id');
    Route::match(['patch', 'put'], '/leads/{id}', [$lead, 'update'])->whereNumber('id');
    Route::delete('/leads/{id}', [$lead, 'destroy'])->whereNumber('id');
    Route::post('/leads/{id}/send-email', [$lead, 'sendEmail'])->whereNumber('id');

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
    Route::get('/providers/{id}/service-costs', [$provider, 'branchServices'])->whereNumber('id'); // same data as branch-services
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
    Route::post('/clients', [$client, 'store']);
    Route::match(['patch', 'put'], '/clients/{id}', [$client, 'update'])->whereNumber('id');
    Route::delete('/clients/{id}', [$client, 'destroy'])->whereNumber('id');

    $bill = App\Http\Controllers\Api\BillApiController::class;
    $invoice = App\Http\Controllers\Api\InvoiceApiController::class;
    $transaction = App\Http\Controllers\Api\TransactionApiController::class;
    $userApi = App\Http\Controllers\Api\UserApiController::class;
    $employee = App\Http\Controllers\Api\EmployeeApiController::class;
    $notification = App\Http\Controllers\Api\NotificationApiController::class;
    $catalog = App\Http\Controllers\Api\CatalogApiController::class;
    $city = App\Http\Controllers\Api\CityApiController::class;
    $patient = App\Http\Controllers\Api\PatientApiController::class;
    $branch = App\Http\Controllers\Api\ProviderBranchApiController::class;

    Route::get('/bills', [$bill, 'index']);
    Route::post('/bills', [$bill, 'store']);
    Route::get('/bills/{id}', [$bill, 'show'])->whereNumber('id');
    Route::match(['patch', 'put'], '/bills/{id}', [$bill, 'update'])->whereNumber('id');
    Route::delete('/bills/{id}', [$bill, 'destroy'])->whereNumber('id');
    Route::post('/bills/{id}/items', [$bill, 'storeItem'])->whereNumber('id');
    Route::delete('/bills/{id}/items/{itemId}', [$bill, 'destroyItem'])->whereNumber('id')->whereNumber('itemId');

    Route::get('/invoices', [$invoice, 'index']);
    Route::post('/invoices', [$invoice, 'store']);
    Route::get('/invoices/{id}', [$invoice, 'show'])->whereNumber('id');
    Route::match(['patch', 'put'], '/invoices/{id}', [$invoice, 'update'])->whereNumber('id');
    Route::delete('/invoices/{id}', [$invoice, 'destroy'])->whereNumber('id');
    Route::post('/invoices/{id}/items', [$invoice, 'storeItem'])->whereNumber('id');
    Route::delete('/invoices/{id}/items/{itemId}', [$invoice, 'destroyItem'])->whereNumber('id')->whereNumber('itemId');

    Route::get('/transactions', [$transaction, 'index']);
    Route::post('/transactions', [$transaction, 'store']);
    Route::get('/transactions/{id}', [$transaction, 'show'])->whereNumber('id');
    Route::match(['patch', 'put'], '/transactions/{id}', [$transaction, 'update'])->whereNumber('id');
    Route::delete('/transactions/{id}', [$transaction, 'destroy'])->whereNumber('id');

    Route::get('/users', [$userApi, 'index']);
    Route::post('/users', [$userApi, 'store']);
    Route::get('/users/{id}', [$userApi, 'show'])->whereNumber('id');
    Route::match(['patch', 'put'], '/users/{id}', [$userApi, 'update'])->whereNumber('id');
    Route::delete('/users/{id}', [$userApi, 'destroy'])->whereNumber('id');

    Route::get('/employees', [$employee, 'index']);
    Route::post('/employees', [$employee, 'store']);
    Route::get('/employees/{id}', [$employee, 'show'])->whereNumber('id');
    Route::match(['patch', 'put'], '/employees/{id}', [$employee, 'update'])->whereNumber('id');
    Route::delete('/employees/{id}', [$employee, 'destroy'])->whereNumber('id');

    Route::get('/notifications', [$notification, 'index']);
    Route::post('/notifications/read-all', [$notification, 'readAll']);
    Route::post('/notifications/{id}/read', [$notification, 'read']);
    Route::post('/push-token', [$notification, 'storePushToken']);
    Route::get('/push-token/status', [$notification, 'pushStatus']);
    Route::patch('/push-token/preferences', [$notification, 'updatePushPreferences']);
    Route::delete('/push-token', [$notification, 'destroyPushToken']);

    Route::get('/dashboard', [App\Http\Controllers\Api\DashboardApiController::class, 'index']);

    Route::get('/catalog', [$catalog, 'index']);
    Route::get('/contacts', [$catalog, 'contacts']);
    Route::get('/bank-accounts', [$catalog, 'bankAccounts']);
    Route::get('/cities', [$city, 'index']);
    Route::get('/patients', [$patient, 'index']);
    Route::post('/patients', [$patient, 'store']);
    Route::get('/provider-branches', [$branch, 'index']);

    // Communications (Gmail IMAP mirror + ops inbox)
    Route::get('/communications/threads', [$communication, 'index']);
    Route::get('/communications/threads/{threadId}', [$communication, 'show'])->whereNumber('threadId');
    Route::post('/communications/threads/{threadId}/read', [$communication, 'markRead'])->whereNumber('threadId');
    Route::post('/communications/threads/{threadId}/reply', [$communication, 'reply'])->whereNumber('threadId');
    Route::post('/communications/threads/{threadId}/link-file', [$communication, 'linkThreadToFile'])->whereNumber('threadId');
    Route::post('/communications/poll', [$communication, 'poll']);
});
