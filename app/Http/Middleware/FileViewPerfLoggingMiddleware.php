<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class FileViewPerfLoggingMiddleware
{
    /**
     * When PERF_LOG is enabled, record start time for File view route so we can log duration and query count in terminate.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (!config('app.perf_log', false)) {
            return $next($request);
        }

        $routeName = $request->route()?->getName() ?? '';
        if (str_contains($routeName, 'filament') && str_contains($routeName, 'files') && str_contains($routeName, 'view')) {
            $request->attributes->set('perf_start', microtime(true));
            $request->attributes->set('perf_query_count', 0);
        }

        return $next($request);
    }

    /**
     * Log request duration and query count for File view route.
     */
    public function terminate(Request $request, Response $response): void
    {
        if (!config('app.perf_log', false)) {
            return;
        }

        $routeName = $request->route()?->getName() ?? '';
        if (!(str_contains($routeName, 'filament') && str_contains($routeName, 'files') && str_contains($routeName, 'view'))) {
            return;
        }

        $start = $request->attributes->get('perf_start');
        $queryCount = $request->attributes->get('perf_query_count', 0);
        if ($start !== null) {
            $duration = round((microtime(true) - $start) * 1000);
            Log::channel('single')->info('File view request', [
                'route' => $routeName,
                'duration_ms' => $duration,
                'query_count' => $queryCount,
            ]);
        }
    }
}
