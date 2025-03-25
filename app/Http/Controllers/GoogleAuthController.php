<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\File;
use Illuminate\Support\Facades\Log;

class GoogleAuthController extends Controller
{
    public function createMeeting(Request $request)
    {
        try {
            $file = File::findOrFail($request->file_id);
            $meetLink = $file->generateGoogleMeetLink();

            return response()->json([
                'success' => true,
                'meet_link' => $meetLink
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to create Google Meet link', [
                'error' => $e->getMessage(),
                'file_id' => $request->file_id
            ]);

            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
