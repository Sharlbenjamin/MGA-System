<?php

namespace App\Services;

use App\Models\File;
use App\Models\FileAssignment;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

class CaseAssignmentService
{
    /**
     * Assign a file/case to a user. Unassigns any current primary assignment.
     */
    public function assign(File $file, User $assignee, ?User $assignedBy = null): FileAssignment
    {
        $assignedBy = $assignedBy ?? Auth::user();

        // Unassign current primary assignment(s)
        $file->fileAssignments()
            ->whereNull('unassigned_at')
            ->where('is_primary', true)
            ->update(['unassigned_at' => now()]);

        return FileAssignment::create([
            'file_id' => $file->id,
            'user_id' => $assignee->id,
            'assigned_by_id' => $assignedBy?->id ?? $assignee->id,
            'assigned_at' => now(),
            'is_primary' => true,
        ]);
    }

    /**
     * Unassign an assignment (set unassigned_at).
     */
    public function unassign(FileAssignment $assignment): void
    {
        $assignment->update(['unassigned_at' => now()]);
    }

    /**
     * Get all assignments for a file (history).
     */
    public function getAssignmentsForFile(File $file)
    {
        return $file->fileAssignments()->with(['user', 'assignedBy'])->orderByDesc('assigned_at')->get();
    }

    /**
     * Get active assignments for a user in a given month/year (for bonus calculation).
     */
    public function getActiveAssignmentsForUser(User $user, int $month, int $year)
    {
        $start = now()->setYear($year)->setMonth($month)->startOfMonth();
        $end = $start->copy()->endOfMonth();

        return FileAssignment::query()
            ->where('user_id', $user->id)
            ->where('assigned_at', '<=', $end)
            ->where(function ($q) use ($start) {
                $q->whereNull('unassigned_at')->orWhere('unassigned_at', '>=', $start);
            })
            ->with('file')
            ->get();
    }
}
