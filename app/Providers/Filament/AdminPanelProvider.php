<?php

namespace App\Providers\Filament;

use App\Filament\Resources\CityResource;
use App\Filament\Resources\ClientResource;
use App\Filament\Resources\CountryResource;
use App\Filament\Resources\DraftMailResource;
use App\Filament\Resources\LeadResource;
use App\Filament\Resources\ProviderBranchResource;
use App\Filament\Resources\ProviderLeadResource;
use App\Filament\Resources\ProviderResource;
use App\Filament\Resources\ContactResource;
use App\Filament\Resources\DrugResource;
use App\Filament\Resources\GopResource;
use App\Filament\Resources\MedicalReportResource;
use App\Filament\Resources\PatientResource;
use App\Filament\Resources\PrescriptionResource;
use App\Filament\Resources\FileResource;
use App\Filament\Resources\UserResource;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Widgets;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Filament\Notifications\Notification;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Auth;
use Illuminate\Notifications\DatabaseNotification;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        //dd(Filament::auth()->user());
        return $panel
        ->topNavigation()
        ->default()
        ->id('admin')
        ->path('admin')
        ->login()
            ->colors([
                'primary' => Color::Amber,
                'gray' => Color::Blue,
            ])->resources([
                ClientResource::class,
                CityResource::class,
                ContactResource::class,
                DraftMailResource::class,
                LeadResource::class,
                ProviderBranchResource::class,
                ProviderLeadResource::class,
                ProviderResource::class,
                PatientResource::class,
                FileResource::class,
                MedicalReportResource::class,
                GopResource::class,
                PrescriptionResource::class,
                DrugResource::class,
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->pages([
                Pages\Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
            ->widgets([
                Widgets\AccountWidget::class,
                Widgets\FilamentInfoWidget::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ])->maxContentWidth('full')
            ->brandName('MGA System')
            ->brandLogo(asset('logo.png'))
            ->favicon(asset('logo.png'));
    }
    
    
}
