<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use Filament\Facades\Filament;
use Filament\Http\Middleware\Authenticate as FilamentAuthenticate;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use App\Http\Middleware\PasswordProtect;
use App\Http\Controllers\GoogleAuthController;
use App\Http\Controllers\GopController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\PrescriptionController;
use App\Http\Controllers\TaxesExportController;
use App\Http\Controllers\HRMonthlyReportExportController;
use App\Http\Controllers\FileCompactViewController;
use App\Http\Controllers\FileDocumentExportController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\ProviderLeadController;
use App\Http\Controllers\LeadController;
use App\Models\City;
use Google\Client as Google_Client;
use Google\Service\Calendar;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

// ✅ Step 1: Check for site password unless already logged in
Route::get('/', function () {
    if (Auth::check()) {
        return redirect()->route('redirect.after.login'); // ✅ Step 3: Redirect after login
    }

    if (!session('access_granted')) {
        return redirect()->route('password.form'); // 🚀 Require site password
    }

    return redirect(route('filament.admin.auth.login')); // ✅ Step 2: Redirect to admin login
});

// ✅ Password entry form
Route::get('/password', function () {
    return view('password-protect');
})->name('password.form');

// ✅ Step 2: Handle password submission and redirect to `/admin/login`
Route::post('/password', function (Request $request) {
    if ($request->password === env('SITE_PASSWORD')) {
        session(['access_granted' => true]);
        return redirect(route('filament.admin.auth.login')); // 🚀 Redirect to Admin login
    }

    return back()->withErrors(['password' => 'Incorrect password']);
})->name('password.submit');

// ✅ Step 3: Redirect to the correct panel after login
Route::get('/redirect-after-login', function () {
    if (!Auth::check()) {
        return redirect(route('filament.admin.auth.login')); // 🚀 Ensure logged-in users only
    }

    $user = Auth::user();

    return $user->hasRole('Telemedicine Doctor')
        ? redirect(route('filament.doctor.pages.dashboard'))  // 🚀 Redirect Doctors to Doctor Panel
        : redirect(route('filament.admin.pages.dashboard')); // 🚀 Redirect Others to Admin Panel
})->name('redirect.after.login');

// Filament Admin Panel Routes
Route::middleware([PasswordProtect::class, FilamentAuthenticate::class, DispatchServingFilamentEvent::class])->group(function () {
    // Filament registers its own routes automatically.
    

});

// Export Routes (only require Filament authentication, not site password)
Route::middleware([FilamentAuthenticate::class])->group(function () {
    Route::get('/taxes/export', [TaxesExportController::class, 'export'])->name('taxes.export');
    Route::get('/taxes/export/zip', [TaxesExportController::class, 'exportZip'])->name('taxes.export.zip');
    Route::get('/files/export/zip', [FileDocumentExportController::class, 'exportZip'])->name('files.export.zip');
    Route::get('/hr/monthly-report/export', [HRMonthlyReportExportController::class, 'export'])->name('hr.monthly-report.export');

    // Compact view: standalone page for file (no Filament, full page)
    Route::get('/file-compact/{file}', [FileCompactViewController::class, 'show'])->name('files.compact');
    Route::get('/file-communications/{file}', [FileCompactViewController::class, 'communications'])->name('files.communications-wireframe');
    Route::post('/file-communications/{file}/threads/{thread}/read', [FileCompactViewController::class, 'markRead'])->name('files.communications.mark-read');
    Route::post('/file-communications/{file}/threads/{thread}/reply', [FileCompactViewController::class, 'sendReply'])->name('files.communications.reply');
    Route::get('/communication-attachments/{attachment}', [FileCompactViewController::class, 'downloadAttachment'])->name('files.communications.attachment');
});

// API Routes for AJAX functionality
Route::middleware([FilamentAuthenticate::class])->group(function () {
    // Get cities by country ID
    Route::get('/api/cities/{countryId}', function ($countryId) {
        $cities = City::where('country_id', $countryId)->get(['id', 'name']);
        return response()->json($cities);
    });

    // Check email existence
    Route::get('/api/check-email', function (Request $request) {
        $email = $request->input('email');
        $type = $request->input('type', 'provider');
        
        if ($type === 'provider') {
            $exists = \App\Models\Provider::where('email', $email)->exists();
        } elseif ($type === 'client') {
            $exists = \App\Models\Client::where('email', $email)->exists();
        } else {
            $exists = \App\Models\ProviderLead::where('email', $email)->exists();
        }
        
        return response()->json(['exists' => $exists]);
    });

});

// Google Meet Route
Route::post('/create-meeting', [GoogleAuthController::class, 'createMeeting'])->name('google.create-meeting');

Route::get('/google/callback', function (Request $request) {
    $client = new Google_Client();
    $client->setAuthConfig(storage_path('app/google-calendar/credentials.json'));
    $client->setAccessType('offline');
    $client->setPrompt('select_account consent');
    $client->setScopes([
        'https://www.googleapis.com/auth/calendar',
        'https://www.googleapis.com/auth/calendar.events'
    ]);

    if ($request->has('code')) {
        $token = $client->fetchAccessTokenWithAuthCode($request->code);
        $client->setAccessToken($token);

        // Store the token to disk for future use
        if (!file_exists(dirname(storage_path('app/google-calendar/token.json')))) {
            mkdir(dirname(storage_path('app/google-calendar/token.json')), 0700, true);
        }
        file_put_contents(storage_path('app/google-calendar/token.json'), json_encode($client->getAccessToken()));

        return 'Authorization successful! You can close this window.';
    }

    return 'Authorization failed!';
});

// Remove or comment out the test routes we created earlier
// Route::get('/test-meet', ...);
// Route::post('/api/create-meet', ...);

Route::get('/gop/{gop}', [GopController::class, 'view'])->name('gop.view');
Route::get('/invoice/{invoice}', [InvoiceController::class, 'view'])->name('invoice.view');
Route::get('/prescription/{prescription}', [PrescriptionController::class, 'view'])->name('prescription.view');

// Signed document routes for secure file access
Route::middleware('signed')->group(function () {
    Route::get('/docs/{type}/{id}', [DocumentController::class, 'serve'])->name('docs.serve');
    Route::get('/docs/{type}/{id}/metadata', [DocumentController::class, 'metadata'])->name('docs.metadata');
});
