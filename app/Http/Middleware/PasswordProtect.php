<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class PasswordProtect
{
    public function handle(Request $request, Closure $next): Response
    {
        // ✅ Ensure the system password is entered before anything else
        if (!session('access_granted')) {
            return redirect()->route('password.form');
        }

        return $next($request);
    }
}