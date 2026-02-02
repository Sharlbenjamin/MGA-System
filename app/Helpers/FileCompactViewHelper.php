<?php

namespace App\Helpers;

use App\Models\File;
use App\Models\Task;

class FileCompactViewHelper
{
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

    /** @return array<int, array{name: string, status: string, assignee: string}> */
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
        $result = [];
        foreach ($titles as $title) {
            $task = $fileTasks->get($title) ?? $fileTasks->first(fn (Task $t) => stripos($t->title, $title) !== false || stripos($title, $t->title) !== false);
            $result[] = [
                'name' => $title,
                'status' => $task ? ($task->is_done ? 'Done' : 'Pending') : 'Pending',
                'assignee' => $task && $task->user ? $task->user->name : '—',
            ];
        }
        return $result;
    }
}
