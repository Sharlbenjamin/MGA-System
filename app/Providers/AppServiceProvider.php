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
    }
}
