<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Prevent 500 when unauthenticated: do not redirect API/JSON to route('login') (which does not exist)
        $middleware->redirectGuestsTo(function (Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return null;
            }
            return route('filament.admin.auth.login');
        });
        $middleware->append(\App\Http\Middleware\FileViewPerfLoggingMiddleware::class);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Ensure ALL /api/* responses are JSON (no HTML error pages for mobile clients)
        $exceptions->render(function (\Throwable $e, Request $request) {
            if (!$request->is('api/*') && !$request->expectsJson()) {
                return null;
            }
            // Let Laravel return its own JSON for validation
            if ($e instanceof ValidationException) {
                return null;
            }
            // Policy/auth must return 403 JSON, not 500
            if ($e instanceof AuthorizationException) {
                return response()->json(['message' => $e->getMessage() ?: 'Forbidden'], 403);
            }
            $code = $e instanceof HttpException ? $e->getStatusCode() : 500;
            $message = $e instanceof HttpException ? $e->getMessage() : 'Server Error';
            if ($e instanceof NotFoundHttpException) {
                $message = 'Not found';
            }
            return response()->json(['message' => $message], $code);
        });
    })->create();
