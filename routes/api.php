<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PatientController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// Patient API routes
Route::prefix('patients')->group(function () {
    Route::get('/search-similar', [PatientController::class, 'searchSimilar']);
    Route::post('/check-duplicate', [PatientController::class, 'checkDuplicate']);
});
