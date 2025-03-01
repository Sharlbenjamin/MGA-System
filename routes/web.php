<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use Filament\Facades\Filament;
use Filament\Http\Middleware\Authenticate as FilamentAuthenticate;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use App\Http\Middleware\PasswordProtect;

// ✅ Redirect root (`127.0.0.1` or `/`) based on session and authentication status
Route::get('/', function () {
    if (session('access_granted')) {
        return Auth::check() 
        ? redirect('/admin')  // Redirect to Filament dashboard if logged in
        : redirect('/admin/login'); // Corrected route to Filament login page
    }

    return redirect()->route('password.form');
});

// ✅ Password entry form
Route::get('/password', function () {
    return view('password-protect');
})->name('password.form');

// ✅ Handle password submission
Route::post('/password', function (Request $request) {
    if ($request->password === env('SITE_PASSWORD')) {
        session(['access_granted' => true]);

        return Auth::check()
            ? redirect('/admin')  // Redirect to Filament dashboard if logged in
            : redirect('/admin/login'); // Corrected route to Filament login page
    }

    return back()->withErrors(['password' => 'Incorrect password']);
})->name('password.submit');

// ✅ Protect Filament Admin Panel with Password Middleware
Route::middleware([PasswordProtect::class, FilamentAuthenticate::class, DispatchServingFilamentEvent::class])->group(function () {
    // Filament registers its own routes automatically, so no need to call anything here.
});