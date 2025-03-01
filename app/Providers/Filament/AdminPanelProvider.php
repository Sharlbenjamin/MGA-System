<?php

namespace App\Providers\Filament;

use App\Filament\Admin\Resources\CityResource;
use App\Filament\Admin\Resources\ClientResource;
use App\Filament\Admin\Resources\CountryResource;
use App\Filament\Admin\Resources\DraftMailResource;
use App\Filament\Admin\Resources\LeadResource;
use App\Filament\Admin\Resources\ProviderBranchResource;
use App\Filament\Admin\Resources\ProviderLeadResource;
use App\Filament\Admin\Resources\ProviderResource;
use App\Filament\Admin\Resources\ContactResource;
use App\Filament\Admin\Resources\DrugResource;
use App\Filament\Admin\Resources\GopResource;
use App\Filament\Admin\Resources\MedicalReportResource;
use App\Filament\Admin\Resources\PatientResource;
use App\Filament\Admin\Resources\PrescriptionResource;
use App\Filament\Admin\Resources\FileResource;
use App\Filament\Admin\Resources\UserResource;
use App\Filament\Admin\Resources\TeamResource;
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
use Filament\Navigation\NavigationItem;
use Filament\Pages\Auth\Login; 

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
        ->topNavigation()
            ->id('admin')
            ->path('admin')
            ->login(Login::class) 
            ->colors([
                'primary' => Color::Amber,
            ])
            ->resources([
                ClientResource::class,
                CityResource::class,
                CountryResource::class,
                ContactResource::class,
                DraftMailResource::class,
                LeadResource::class,
                ProviderBranchResource::class,
                ProviderLeadResource::class,
                ProviderResource::class,
                TeamResource::class,
                UserResource::class,
                PatientResource::class,
                FileResource::class,
                MedicalReportResource::class,
                GopResource::class,
                PrescriptionResource::class,
                DrugResource::class,
            ])
            ->discoverResources(in: app_path('Filament/Admin/Resources'), for: 'App\\Filament\\Admin\\Resources')
            ->discoverPages(in: app_path('Filament/Admin/Pages'), for: 'App\\Filament\\Admin\\Pages')
            ->pages([
                Pages\Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Admin/Widgets'), for: 'App\\Filament\\Admin\\Widgets')
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
            ])
            ->navigationItems([
                // CRM Group
                NavigationItem::make('Clients')
                    ->url(fn () => ClientResource::getUrl())
                    ->icon('heroicon-o-users')
                    ->group('CRM')
                    ->sort(1),
    
                NavigationItem::make('Leads')
                    ->url(fn () => LeadResource::getUrl())
                    ->icon('heroicon-o-light-bulb')
                    ->group('CRM')
                    ->sort(2),
    
                NavigationItem::make('Draft Mails')
                    ->url(fn () => DraftMailResource::getUrl())
                    ->icon('heroicon-o-envelope')
                    ->group('CRM')
                    ->sort(3),
    
                // PRM Group
                NavigationItem::make('Providers')
                    ->url(fn () => ProviderResource::getUrl())
                    ->icon('heroicon-o-truck')
                    ->group('PRM')
                    ->sort(1),
    
                NavigationItem::make('Provider Leads')
                    ->url(fn () => ProviderLeadResource::getUrl())
                    ->icon('heroicon-o-light-bulb')
                    ->group('PRM')
                    ->sort(2),
    
                NavigationItem::make('Branches')
                    ->url(fn () => ProviderBranchResource::getUrl())
                    ->icon('heroicon-o-home-modern')
                    ->group('PRM')
                    ->sort(3),
    
                // Maps Group
                NavigationItem::make('Cities')
                    ->url(fn () => CityResource::getUrl())
                    ->icon('heroicon-o-map-pin')
                    ->group('Maps')
                    ->sort(1),
    
                NavigationItem::make('Countries')
                    ->url(fn () => CountryResource::getUrl())
                    ->icon('heroicon-o-globe-alt')
                    ->group('Maps')
                    ->sort(2),
    
                // User Settings Group
                NavigationItem::make('Users')
                    ->url(fn () => UserResource::getUrl())
                    ->icon('heroicon-o-user')
                    ->group('User Settings')
                    ->sort(1),
                
                NavigationItem::make('Contacts')
                    ->url(fn () => ContactResource::getUrl())
                    ->icon('heroicon-o-user')
                    ->group('User Settings')
                    ->sort(2),
    
                NavigationItem::make('Teams')
                    ->url(fn () => TeamResource::getUrl())
                    ->icon('heroicon-o-users')
                    ->group('User Settings')
                    ->sort(3),
                // Operation
                NavigationItem::make('Patients')
                    ->url(fn () => PatientResource::getUrl())
                    ->icon('heroicon-o-user-plus')
                    ->group('Operation')
                    ->sort(1),

                NavigationItem::make('Files')
                    ->url(fn () => FileResource::getUrl())
                    ->icon('heroicon-o-clipboard-document-list')
                    ->group('Operation')
                    ->sort(2),
            ]) ->maxContentWidth('full');
    }

    protected function getMaxContentWidth(): ?string
{
    return '7xl'; // or any other Tailwind CSS max-width value you prefer
}
}
