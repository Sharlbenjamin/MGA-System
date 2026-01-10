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
use App\Filament\Resources\InvoiceResource;
use App\Filament\Resources\BillResource;
use App\Filament\Resources\TransactionResource;
use App\Filament\Resources\BranchAvailabilityResource;
use App\Filament\Pages\BulkAddBranches;
use App\Filament\Widgets\FilesPerClient;
use App\Filament\Widgets\FilesPerCountry;
use App\Filament\Widgets\FilesPerMonth;
use App\Filament\Widgets\FilesPerServiceType;
use App\Filament\Widgets\FilesPerStatus;
use App\Filament\Widgets\FileStatsOverview;
use App\Filament\Widgets\TotalFile;
use App\Filament\Widgets\CasesPerMonthStatus;
use App\Filament\Widgets\MonthlyProfit;
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
use Filament\Widgets\AccountWidget;
use Filament\Widgets\FilamentInfoWidget;
use Filament\Navigation\NavigationGroup;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            ->topNavigation()
            ->colors([
                'primary' => Color::hex('#191970'),
            ])
            ->resources([
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
                BranchAvailabilityResource::class,
                // Stages Resources
                \App\Filament\Resources\FilesWithoutGopResource::class,
                \App\Filament\Resources\GopWithoutDocsResource::class,
                \App\Filament\Resources\FilesWithoutBillsResource::class,
                \App\Filament\Resources\FilesWithoutInvoicesResource::class,
                \App\Filament\Resources\FilesWithoutMRResource::class,
                \App\Filament\Resources\BillsWithoutDocumentsResource::class,
                \App\Filament\Resources\InvoicesWithoutDocsResource::class,
                \App\Filament\Resources\BillWithoutTransactionResource::class,
                \App\Filament\Resources\InvoicesWithoutTransactionResource::class,
                \App\Filament\Resources\TransactionsWithoutDocumentsResource::class,
            ])

            ->databaseNotifications()
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->pages([
                BulkAddBranches::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
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
            ->maxContentWidth('full')
            ->brandName('MGA System')
            ->brandLogo(asset('logo.png'))
            ->favicon(asset('logo.png'))
            ->globalSearchKeyBindings(['command+k', 'ctrl+k'])
            ->globalSearch(true)
            ->sidebarCollapsibleOnDesktop()
            ->navigationGroups([
                NavigationGroup::make()
                    ->label('CRM')
                    ->collapsible(),
                NavigationGroup::make()
                    ->label('PRM')
                    ->collapsible(),
                NavigationGroup::make()
                    ->label('Ops')
                    ->collapsible(),
                NavigationGroup::make()
                    ->label('Workflow')
                    ->collapsible(),
                NavigationGroup::make()
                    ->label('Finance')
                    ->collapsible(),
                NavigationGroup::make()
                    ->label('System')
                    ->collapsible(),
            ]);
    }
}
