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
use App\Filament\Resources\UserResource;
use App\Filament\Resources\TeamResource;
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


class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
        ->topNavigation()
            ->id('admin')
            ->path('admin')
            ->colors([
                'primary' => Color::Amber,
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
    
                NavigationItem::make('Teams')
                    ->url(fn () => TeamResource::getUrl())
                    ->icon('heroicon-o-users')
                    ->group('User Settings')
                    ->sort(2),
            ]);
    }
}
