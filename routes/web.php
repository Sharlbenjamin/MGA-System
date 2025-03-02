<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use Filament\Facades\Filament;
use Filament\Http\Middleware\Authenticate as FilamentAuthenticate;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use App\Http\Middleware\PasswordProtect;

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
        ? redirect(route('filament.doctor.home'))  // 🚀 Redirect Doctors to Doctor Panel
        : redirect(route('filament.admin.home')); // 🚀 Redirect Others to Admin Panel
})->name('redirect.after.login');

// ✅ Protect Filament Admin Panel with Password Middleware
Route::middleware([PasswordProtect::class, FilamentAuthenticate::class, DispatchServingFilamentEvent::class])->group(function () {
    // Filament registers its own routes automatically.
});