<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Validator;
use App\Http\Controllers\PatientController;

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

// Patient API routes
Route::prefix('patients')->group(function () {
    Route::get('/search-similar', [PatientController::class, 'searchSimilar']);
    Route::post('/check-duplicate', [PatientController::class, 'checkDuplicate']);
});
