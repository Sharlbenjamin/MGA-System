<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
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
        //
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Ensure ALL /api/* responses are JSON (no HTML error pages for mobile clients)
        $exceptions->render(function (\Throwable $e, Request $request) {
            if (!$request->is('api/*') && !$request->expectsJson()) {
                return null;
            }
            // Let Laravel return its own JSON for validation/auth
            if ($e instanceof ValidationException) {
                return null;
            }
            $code = $e instanceof HttpException ? $e->getStatusCode() : 500;
            $message = $e instanceof HttpException ? $e->getMessage() : 'Server Error';
            if ($e instanceof NotFoundHttpException) {
                $message = 'Not found';
            }
            return response()->json(['message' => $message], $code);
        });
    })->create();
