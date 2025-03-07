<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Filament\Facades\Filament;
use App\Providers\Filament\AdminPanelProvider;
use Illuminate\Notifications\DatabaseNotification as LaravelDatabaseNotification;
use Filament\Notifications\DatabaseNotification as FilamentDatabaseNotification;


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
    }
}
