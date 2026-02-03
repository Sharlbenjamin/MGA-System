<?php

namespace App\Helpers;

use App\Models\Bill;
use App\Models\File;
use App\Models\Gop;
use App\Models\MedicalReport;
use App\Models\Task;
use Illuminate\Support\Facades\URL;

class FileCompactViewHelper
{
    /**
     * Check if file has GOP type In (created/received). "Received" = exists; optionally status in Received/Sent/Updated.
     */
    public static function fileHasGopInReceived(File $record): bool
    {
        $hasReceivedStatus = $record->gops()
            ->where('type', 'In')
            ->whereIn('status', ['Received', 'Sent', 'Updated'])
            ->exists();
        if ($hasReceivedStatus) {
            return true;
        }
        return $record->gops()->where('type', 'In')->exists();
    }

    /**
     * Check if file has GOP type In that is received and has a document (uploaded in attachments).
     */
    public static function fileHasGopInUploaded(File $record): bool
    {
        return $record->gops()
            ->where('type', 'In')
            ->where(function ($q) {
                $q->where(function ($q2) {
                    $q2->whereNotNull('document_path')->where('document_path', '!=', '');
                })->orWhere(function ($q2) {
                    $q2->whereNotNull('gop_google_drive_link')->where('gop_google_drive_link', '!=', '');
                });
            })
            ->exists();
    }

    /**
     * Check if file has at least one medical report (Create MR done).
     */
    public static function fileHasMedicalReport(File $record): bool
    {
        return $record->medicalReports()->exists();
    }

    /**
     * Check if file has a medical report with document uploaded.
     */
    public static function fileHasMedicalReportUploaded(File $record): bool
    {
        return $record->medicalReports()
            ->whereNotNull('document_path')->where('document_path', '!=', '')
            ->exists();
    }

    /**
     * Check if file has at least one bill (Create Bill done).
     */
    public static function fileHasBill(File $record): bool
    {
        return $record->bills()->exists();
    }

    /**
     * Check if file has a bill with document uploaded.
     */
    public static function fileHasBillUploaded(File $record): bool
    {
        return $record->bills()
            ->where(function ($q) {
                $q->whereNotNull('bill_document_path')->where('bill_document_path', '!=', '')
                    ->orWhereNotNull('bill_google_link')->where('bill_google_link', '!=', '');
            })
            ->exists();
    }

    /**
     * Resolve effective is_done for a compact task: from DB task or from file data.
     * Used for: GOP In, Medical Report, Provider Bill (3 tasks).
     */
    public static function isTaskDoneFromFile(File $record, string $title): bool
    {
        return match (true) {
            $title === 'GOP In' => self::fileHasGopInUploaded($record),
            $title === 'Medical Report' => self::fileHasMedicalReportUploaded($record),
            $title === 'Provider Bill' => self::fileHasBillUploaded($record),
            default => false,
        };
    }

    /**
     * Get signed URL to view the uploaded document for a task (only when document is uploaded locally).
     */
    public static function getViewUrlForTask(File $record, string $title): ?string
    {
        $expiryMinutes = 60;
        if ($title === 'GOP In') {
            $gop = $record->gops()
                ->where('type', 'In')
                ->whereNotNull('document_path')->where('document_path', '!=', '')
                ->latest()
                ->first();
            return $gop?->getDocumentSignedUrl($expiryMinutes);
        }
        if ($title === 'Medical Report') {
            $mr = $record->medicalReports()
                ->whereNotNull('document_path')->where('document_path', '!=', '')
                ->latest()
                ->first();
            return $mr?->getDocumentSignedUrl($expiryMinutes);
        }
        if ($title === 'Provider Bill') {
            $bill = $record->bills()
                ->whereNotNull('bill_document_path')->where('bill_document_path', '!=', '')
                ->latest()
                ->first();
            if (!$bill) {
                return null;
            }
            return URL::temporarySignedRoute('docs.serve', now()->addMinutes($expiryMinutes), [
                'type' => 'bill',
                'id' => $bill->id,
            ]);
        }
        return null;
    }

    /**
     * Get details string for a linked record: amount (GOP/Bill), diagnosis (MR), or "Pending".
     */
    public static function getDetailsForRecord(object $record): string
    {
        if ($record instanceof Gop) {
            return $record->amount !== null && $record->amount !== '' ? '€ ' . number_format((float) $record->amount, 2) : 'Pending';
        }
        if ($record instanceof MedicalReport) {
            $diagnosis = trim((string) ($record->diagnosis ?? ''));
            if ($diagnosis === '') {
                return 'Pending';
            }
            return strlen($diagnosis) > 80 ? substr($diagnosis, 0, 80) . '…' : $diagnosis;
        }
        if ($record instanceof Bill) {
            return $record->total_amount !== null && $record->total_amount !== '' ? '€ ' . number_format((float) $record->total_amount, 2) : 'Pending';
        }
        return 'Pending';
    }

    /**
     * Get view URL for a linked record (signed URL when document is uploaded locally).
     */
    public static function getViewUrlForRecord(object $record): ?string
    {
        $expiryMinutes = 60;
        if ($record instanceof Gop) {
            if (empty($record->document_path)) {
                return null;
            }
            return $record->getDocumentSignedUrl($expiryMinutes);
        }
        if ($record instanceof MedicalReport) {
            if (empty($record->document_path)) {
                return null;
            }
            return $record->getDocumentSignedUrl($expiryMinutes);
        }
        if ($record instanceof Bill) {
            if (empty($record->bill_document_path)) {
                return null;
            }
            return URL::temporarySignedRoute('docs.serve', now()->addMinutes($expiryMinutes), [
                'type' => 'bill',
                'id' => $record->id,
            ]);
        }
        return null;
    }

    /**
     * Check if linked record has document uploaded (is done for view).
     */
    public static function isRecordDoneForView(object $record): bool
    {
        if ($record instanceof Gop) {
            return !empty($record->document_path) || !empty($record->gop_google_drive_link);
        }
        if ($record instanceof MedicalReport) {
            return !empty($record->document_path);
        }
        if ($record instanceof Bill) {
            return !empty($record->bill_document_path) || !empty($record->bill_google_link);
        }
        return false;
    }

    /**
     * Get details string for a task by title (legacy: used when no linked record).
     */
    public static function getDetailsForTask(File $record, string $title): string
    {
        if ($title === 'GOP In') {
            $gop = $record->gops()->where('type', 'In')->latest()->first();
            return $gop ? self::getDetailsForRecord($gop) : 'Pending';
        }
        if ($title === 'Medical Report') {
            $mr = $record->medicalReports()->latest()->first();
            return $mr ? self::getDetailsForRecord($mr) : 'Pending';
        }
        if ($title === 'Provider Bill') {
            $bill = $record->bills()->latest()->first();
            return $bill ? self::getDetailsForRecord($bill) : 'Pending';
        }
        return 'Pending';
    }

    public static function formatCaseInfo(File $record): string
    {
        $isEmpty = fn ($value) => $value === null || $value === '' || (is_string($value) && trim($value) === '');

        $patientName = ($record->patient && !$isEmpty($record->patient->name)) ? trim($record->patient->name) : 'N/A';
        $dob = 'N/A';
        if ($record->patient && !$isEmpty($record->patient->dob)) {
            try {
                $dob = \Carbon\Carbon::parse($record->patient->dob)->format('d/m/Y');
            } catch (\Exception $e) {
                $dob = 'N/A';
            }
        }
        $mgaReference = !$isEmpty($record->mga_reference) ? trim($record->mga_reference) : 'N/A';
        $symptoms = !$isEmpty($record->symptoms) ? trim($record->symptoms) : 'N/A';
        $serviceType = ($record->serviceType && !$isEmpty($record->serviceType->name)) ? trim($record->serviceType->name) : 'N/A';
        $serviceDate = $record->service_date ? $record->service_date->format('d/m/Y') : 'N/A';
        $serviceTime = 'N/A';
        if (!$isEmpty($record->service_time)) {
            try {
                $serviceTime = \Carbon\Carbon::parse($record->service_time)->format('h:iA');
            } catch (\Exception $e) {
                $serviceTime = 'N/A';
            }
        }
        $request = "{$serviceType} on {$serviceDate} at {$serviceTime}";
        $phone = !$isEmpty($record->phone) ? trim($record->phone) : 'N/A';
        $address = !$isEmpty($record->address) ? trim($record->address) : 'N/A';

        return "Patient Name: {$patientName}\nDOB: {$dob}\nMGA Reference: {$mgaReference}\nSymptoms: {$symptoms}\nRequest: {$request}\nPhone: {$phone}\nAddress: {$address}";
    }

    /**
     * Find or create a task for a linked record (GOP In, Medical Report, Bill).
     */
    private static function findOrCreateTaskForRecord(File $file, string $title, object $linkedRecord, int|null $defaultUserId): Task
    {
        $taskableType = $linkedRecord::class;
        $taskableId = $linkedRecord->id;

        $task = $file->tasks()
            ->where('department', 'Operation')
            ->where('taskable_type', $taskableType)
            ->where('taskable_id', $taskableId)
            ->with('user')
            ->first();

        if ($task) {
            return $task;
        }

        $task = Task::create([
            'file_id' => $file->id,
            'title' => $title,
            'department' => 'Operation',
            'taskable_type' => $taskableType,
            'taskable_id' => $taskableId,
            'user_id' => $defaultUserId,
            'is_done' => false,
        ]);
        $task->load('user');
        return $task;
    }

    /** @return array<int, array{id: int|null, name: string, status: string, assignee: string, user_id: int|null, is_done: bool, description: string|null, linked_case: string, date_assigned: string|null, view_url: string|null, details: string}> */
    public static function getCompactTasks(File $record): array
    {
        $defaultUser = $record->assignedUser();
        $defaultUserId = $defaultUser?->id;
        $defaultUserName = $defaultUser?->name ?? '—';

        $result = [];

        // One task per GOP In
        $gopsIn = $record->gops()->where('type', 'In')->orderBy('id')->get();
        foreach ($gopsIn as $gop) {
            $task = self::findOrCreateTaskForRecord($record, 'GOP In', $gop, $defaultUserId);
            $isDoneFromRecord = self::isRecordDoneForView($gop);
            $isDone = (bool) $task->is_done;
            $effectiveDone = $isDone || $isDoneFromRecord;

            if ($isDoneFromRecord && !$task->is_done) {
                $task->update(['is_done' => true]);
            }
            if ($defaultUserId && !$task->user_id) {
                $task->update(['user_id' => $defaultUserId]);
                $task->load('user');
            }

            $statusLabel = !$task->user_id ? 'Unassigned' : ($effectiveDone ? 'Done' : 'Pending');
            $assignee = ($statusLabel === 'Unassigned') ? '—' : ($task->user?->name ?? $defaultUserName);

            $result[] = [
                'id' => $task->id,
                'name' => 'GOP In',
                'status' => $statusLabel,
                'assignee' => $assignee,
                'user_id' => $task->user_id ?? $defaultUserId,
                'is_done' => $effectiveDone,
                'description' => $task->description,
                'linked_case' => $record->mga_reference ?? '—',
                'date_assigned' => $task->created_at?->format('d/m/Y'),
                'view_url' => self::getViewUrlForRecord($gop),
                'details' => self::getDetailsForRecord($gop),
            ];
        }
        if ($gopsIn->isEmpty()) {
            $orphan = self::findOrphanTaskForTitle($record, 'GOP In');
            $result[] = $orphan ? self::rowFromOrphanTask($record, $orphan, 'GOP In', $defaultUserId) : self::placeholderTaskRow($record, 'GOP In', '—', null);
        }

        // One task per Medical Report
        $medicalReports = $record->medicalReports()->orderBy('id')->get();
        foreach ($medicalReports as $mr) {
            $task = self::findOrCreateTaskForRecord($record, 'Medical Report', $mr, $defaultUserId);
            $isDoneFromRecord = self::isRecordDoneForView($mr);
            $isDone = (bool) $task->is_done;
            $effectiveDone = $isDone || $isDoneFromRecord;

            if ($isDoneFromRecord && !$task->is_done) {
                $task->update(['is_done' => true]);
            }
            if ($defaultUserId && !$task->user_id) {
                $task->update(['user_id' => $defaultUserId]);
                $task->load('user');
            }

            $statusLabel = !$task->user_id ? 'Unassigned' : ($effectiveDone ? 'Done' : 'Pending');
            $assignee = ($statusLabel === 'Unassigned') ? '—' : ($task->user?->name ?? $defaultUserName);

            $result[] = [
                'id' => $task->id,
                'name' => 'Medical Report',
                'status' => $statusLabel,
                'assignee' => $assignee,
                'user_id' => $task->user_id ?? $defaultUserId,
                'is_done' => $effectiveDone,
                'description' => $task->description,
                'linked_case' => $record->mga_reference ?? '—',
                'date_assigned' => $task->created_at?->format('d/m/Y'),
                'view_url' => self::getViewUrlForRecord($mr),
                'details' => self::getDetailsForRecord($mr),
            ];
        }
        if ($medicalReports->isEmpty()) {
            $orphan = self::findOrphanTaskForTitle($record, 'Medical Report');
            $result[] = $orphan ? self::rowFromOrphanTask($record, $orphan, 'Medical Report', $defaultUserId) : self::placeholderTaskRow($record, 'Medical Report', '—', null);
        }

        // One task per Bill
        $bills = $record->bills()->orderBy('id')->get();
        foreach ($bills as $bill) {
            $task = self::findOrCreateTaskForRecord($record, 'Provider Bill', $bill, $defaultUserId);
            $isDoneFromRecord = self::isRecordDoneForView($bill);
            $isDone = (bool) $task->is_done;
            $effectiveDone = $isDone || $isDoneFromRecord;

            if ($isDoneFromRecord && !$task->is_done) {
                $task->update(['is_done' => true]);
            }
            if ($defaultUserId && !$task->user_id) {
                $task->update(['user_id' => $defaultUserId]);
                $task->load('user');
            }

            $statusLabel = !$task->user_id ? 'Unassigned' : ($effectiveDone ? 'Done' : 'Pending');
            $assignee = ($statusLabel === 'Unassigned') ? '—' : ($task->user?->name ?? $defaultUserName);

            $result[] = [
                'id' => $task->id,
                'name' => 'Provider Bill',
                'status' => $statusLabel,
                'assignee' => $assignee,
                'user_id' => $task->user_id ?? $defaultUserId,
                'is_done' => $effectiveDone,
                'description' => $task->description,
                'linked_case' => $record->mga_reference ?? '—',
                'date_assigned' => $task->created_at?->format('d/m/Y'),
                'view_url' => self::getViewUrlForRecord($bill),
                'details' => self::getDetailsForRecord($bill),
            ];
        }
        if ($bills->isEmpty()) {
            $orphan = self::findOrphanTaskForTitle($record, 'Provider Bill');
            $result[] = $orphan ? self::rowFromOrphanTask($record, $orphan, 'Provider Bill', $defaultUserId) : self::placeholderTaskRow($record, 'Provider Bill', '—', null);
        }

        return $result;
    }

    /**
     * Find a standalone (orphan) task for this file and title (no linked record yet).
     */
    private static function findOrphanTaskForTitle(File $record, string $title): ?Task
    {
        return $record->tasks()
            ->where('department', 'Operation')
            ->where(function ($q) use ($title) {
                $q->where('title', $title)->orWhere('title', 'like', '%' . $title . '%');
            })
            ->whereNull('taskable_type')
            ->with('user')
            ->first();
    }

    /**
     * Build a task row from a standalone (orphan) task (no linked record).
     */
    private static function rowFromOrphanTask(File $record, Task $task, string $name, ?int $defaultUserId): array
    {
        $defaultUserName = $record->assignedUser()?->name ?? '—';
        $effectiveDone = (bool) $task->is_done;
        $statusLabel = !$task->user_id ? 'Unassigned' : ($effectiveDone ? 'Done' : 'Pending');
        $assignee = ($statusLabel === 'Unassigned') ? '—' : ($task->user?->name ?? $defaultUserName);
        return [
            'id' => $task->id,
            'name' => $name,
            'status' => $statusLabel,
            'assignee' => $assignee,
            'user_id' => $task->user_id ?? $defaultUserId,
            'is_done' => $effectiveDone,
            'description' => $task->description,
            'linked_case' => $record->mga_reference ?? '—',
            'date_assigned' => $task->created_at?->format('d/m/Y'),
            'view_url' => null,
            'details' => 'Pending',
        ];
    }

    /**
     * Build a placeholder task row when there is no linked record (GOP In, MR, or Bill).
     */
    private static function placeholderTaskRow(File $record, string $name, string $assignee, ?int $userId): array
    {
        return [
            'id' => null,
            'name' => $name,
            'status' => 'Unassigned',
            'assignee' => $assignee,
            'user_id' => $userId,
            'is_done' => false,
            'description' => null,
            'linked_case' => $record->mga_reference ?? '—',
            'date_assigned' => null,
            'view_url' => null,
            'details' => 'Pending',
        ];
    }
}
