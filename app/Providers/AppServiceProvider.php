<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Filament\Facades\Filament;
use App\Providers\Filament\AdminPanelProvider;
use Illuminate\Notifications\DatabaseNotification as LaravelDatabaseNotification;
use Filament\Notifications\DatabaseNotification as FilamentDatabaseNotification;
use Illuminate\Database\Eloquent\Relations\Relation;
use App\Models\Client;
use App\Models\Provider;
use App\Models\ProviderBranch;
use App\Models\Patient;
use App\Models\Task;
use App\Observers\TaskObserver;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;


class AppServiceProvider extends ServiceProvider
{
    public const HOME = '/'; // Redirect to Filament dashboard after login
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->register(AuthServiceProvider::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //FilamentDatabaseNotification::resolveUsing(function ($attributes) {return new LaravelDatabaseNotification($attributes);});
        
        // Map morphTo relationship types to their model classes (non-enforcing)
        // This allows Transaction model to resolve short names like 'Client' to Client::class
        // while still allowing other morphTo relationships to use full class names
        Relation::morphMap([
            'Client' => Client::class,
            'Provider' => Provider::class,
            'Branch' => ProviderBranch::class,
            'Patient' => Patient::class,
        ]);

        Task::observe(TaskObserver::class);

        $this->registerPerfLogging();
    }

    /**
     * When config('app.perf_log') is true, log slow queries (>200ms) on the File view route.
     * Request duration and query count are logged by FileViewPerfLoggingMiddleware.
     */
    protected function registerPerfLogging(): void
    {
        if (!config('app.perf_log', false)) {
            return;
        }

        DB::listen(function ($event) {
            $routeName = request()->route()?->getName() ?? '';
            if (!(str_contains($routeName, 'filament') && str_contains($routeName, 'files') && str_contains($routeName, 'view'))) {
                return;
            }
            if (!request()->attributes->has('perf_query_count')) {
                request()->attributes->set('perf_query_count', 0);
            }
            request()->attributes->set('perf_query_count', request()->attributes->get('perf_query_count') + 1);
            if ($event->time > 200) {
                Log::channel('single')->warning('Slow query on File view', [
                    'route' => $routeName,
                    'time_ms' => $event->time,
                    'sql' => $event->sql,
                ]);
            }
        });
    }
}
