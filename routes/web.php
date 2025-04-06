<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use Filament\Facades\Filament;
use Filament\Http\Middleware\Authenticate as FilamentAuthenticate;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use App\Http\Middleware\PasswordProtect;
use App\Http\Controllers\GoogleAuthController;
use Google\Client as Google_Client;
use Google\Service\Calendar;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\GopController;

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
