<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use Filament\Http\Middleware\Authenticate as FilamentAuthenticate;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Facades\Filament;

// ✅ Redirect root (`127.0.0.1` or `/`) based on session and authentication status
Route::get('/', function () {
    if (session('access_granted')) {
        return Auth::check() ? redirect('/admin') : redirect('/admin/login');
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
            ? redirect('/admin') // If logged in, go to Filament dashboard
            : redirect('/admin/login'); // If not logged in, go to Filament login
    }

    return back()->withErrors(['password' => 'Incorrect password']);
})->name('password.submit');

// ✅ Protect Filament Admin Panel with Password Middleware
Route::middleware([\App\Http\Middleware\PasswordProtect::class, FilamentAuthenticate::class, DispatchServingFilamentEvent::class])->group(function () {
    Route::prefix('admin')->group(function () {
        Filament::serving(function () {
            // Filament automatically registers its routes
        });
    });
});