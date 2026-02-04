<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\File;
use Illuminate\Http\JsonResponse;

class FileApiController extends Controller
{
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

        return response()->json($file);
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
