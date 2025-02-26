<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Filament\Facades\Filament;
use App\Providers\Filament\AdminPanelProvider;


class AppServiceProvider extends ServiceProvider
{
    public const HOME = '/admin'; // Redirect to Filament dashboard after login
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->register(AdminPanelProvider::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
