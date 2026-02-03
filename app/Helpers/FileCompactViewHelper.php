<?php

namespace App\Helpers;

use App\Models\File;
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
     */
    public static function isTaskDoneFromFile(File $record, string $title): bool
    {
        return match (true) {
            $title === 'Create GOP In' => self::fileHasGopInReceived($record),
            $title === 'Upload GOP In' => self::fileHasGopInUploaded($record),
            $title === 'Create MR' => self::fileHasMedicalReport($record),
            $title === 'Upload MR' => self::fileHasMedicalReportUploaded($record),
            $title === 'Create Bill' => self::fileHasBill($record),
            $title === 'Upload Bill' => self::fileHasBillUploaded($record),
            default => false,
        };
    }

    /**
     * Get signed URL to view the uploaded document for a task (only when document is uploaded locally).
     * Returns null if no local document (e.g. only Google link) or task type has no document.
     */
    public static function getViewUrlForTask(File $record, string $title): ?string
    {
        $expiryMinutes = 60;
        if ($title === 'Create GOP In' || $title === 'Upload GOP In') {
            $gop = $record->gops()
                ->where('type', 'In')
                ->whereNotNull('document_path')->where('document_path', '!=', '')
                ->latest()
                ->first();
            return $gop?->getDocumentSignedUrl($expiryMinutes);
        }
        if ($title === 'Create MR' || $title === 'Upload MR') {
            $mr = $record->medicalReports()
                ->whereNotNull('document_path')->where('document_path', '!=', '')
                ->latest()
                ->first();
            return $mr?->getDocumentSignedUrl($expiryMinutes);
        }
        if ($title === 'Create Bill' || $title === 'Upload Bill') {
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

    /** @return array<int, array{id: int|null, name: string, status: string, assignee: string, user_id: int|null, is_done: bool, description: string|null, linked_case: string, date_assigned: string|null, view_url: string|null}> */
    public static function getCompactTasks(File $record): array
    {
        $titles = [
            'Create GOP In',
            'Upload GOP In',
            'Create MR',
            'Upload MR',
            'Create Bill',
            'Upload Bill',
        ];
        $fileTasks = $record->tasks()->where('department', 'Operation')->with('user')->get()->keyBy(fn (Task $t) => $t->title);
        $defaultUser = $record->assignedUser();
        $defaultUserId = $defaultUser?->id;
        $defaultUserName = $defaultUser?->name ?? '—';

        $result = [];
        foreach ($titles as $title) {
            $task = $fileTasks->get($title) ?? $fileTasks->first(fn (Task $t) => stripos($t->title, $title) !== false || stripos($title, $t->title) !== false);
            $isDoneFromFile = self::isTaskDoneFromFile($record, $title);
            $isDone = $task ? (bool) $task->is_done : false;
            $effectiveDone = $isDone || $isDoneFromFile;

            if ($task) {
                if ($isDoneFromFile && !$task->is_done) {
                    $task->update(['is_done' => true]);
                }
                if ($defaultUserId && !$task->user_id) {
                    $task->update(['user_id' => $defaultUserId]);
                    $task->load('user');
                }
            }

            $assignee = ($task && $task->user) ? $task->user->name : $defaultUserName;
            $userId = $task?->user_id ?? $defaultUserId;

            $dateAssigned = $task?->created_at
                ? $task->created_at->format('d/m/Y')
                : null;

            $viewUrl = self::getViewUrlForTask($record, $title);

            $result[] = [
                'id' => $task?->id,
                'name' => $title,
                'status' => $effectiveDone ? 'Done' : 'Pending',
                'assignee' => $assignee,
                'user_id' => $userId,
                'is_done' => $effectiveDone,
                'description' => $task?->description,
                'linked_case' => $record->mga_reference ?? '—',
                'date_assigned' => $dateAssigned,
                'view_url' => $viewUrl,
            ];
        }
        return $result;
    }
}
