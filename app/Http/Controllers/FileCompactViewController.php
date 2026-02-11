<?php

namespace App\Http\Controllers;

use App\Models\File;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;

class FileCompactViewController extends Controller
{
    use AuthorizesRequests;

    /**
     * Redirect to Filament file view (compact view is shown there; no app layout / Vite).
     */
    public function show(Request $request, File $file)
    {
        $this->authorize('view', $file);

        return redirect()->route('filament.admin.resources.files.view', ['record' => $file]);
    }

    /**
     * Show standalone communications/threads wireframe for a file.
     */
    public function communications(Request $request, File $file)
    {
        $this->authorize('view', $file);

        return view('filament.pages.files.communications-wireframe', [
            'file' => $file,
        ]);
    }
}
