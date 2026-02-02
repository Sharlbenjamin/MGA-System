<?php

namespace App\Http\Controllers;

use App\Helpers\FileCompactViewHelper;
use App\Models\File;
use Illuminate\Http\Request;

class FileCompactViewController extends Controller
{
    /**
     * Show the compact view for a file (standalone page).
     */
    public function show(Request $request, File $file)
    {
        $this->authorize('view', $file);

        $file->load([
            'patient.client',
            'serviceType',
            'country',
            'city',
            'providerBranch.provider',
            'providerBranch.city',
        ]);

        $summaryText = FileCompactViewHelper::formatCaseInfo($file);
        $compactTasks = FileCompactViewHelper::getCompactTasks($file);

        return view('filament.pages.files.file-compact-standalone', [
            'record' => $file,
            'summaryText' => $summaryText,
            'compactTasks' => $compactTasks,
        ]);
    }
}
