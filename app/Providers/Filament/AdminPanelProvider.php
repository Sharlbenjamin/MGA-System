<?php

namespace App\Providers\Filament;

use App\Filament\Pages\BulkAddBranches;
use App\Filament\Pages\EmploymentContractDraft;
use App\Filament\Resources\BranchAvailabilityResource;
use App\Filament\Resources\CityResource;
use App\Filament\Resources\ClientResource;
use App\Filament\Resources\ContactResource;
use App\Filament\Resources\DraftMailResource;
use App\Filament\Resources\DrugResource;
use App\Filament\Resources\FileResource;
use App\Filament\Resources\GopResource;
use App\Filament\Resources\LeadResource;
use App\Filament\Resources\MedicalReportResource;
use App\Filament\Resources\PatientResource;
use App\Filament\Resources\PrescriptionResource;
use App\Filament\Resources\ProviderBranchResource;
use App\Filament\Resources\ProviderLeadResource;
use App\Filament\Resources\ProviderResource;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

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
                // Workflow pipeline (7 items)
                \App\Filament\Resources\AssistedFileChecklistResource::class,
                \App\Filament\Resources\InvoiceChecklistResource::class,
                \App\Filament\Resources\ClientOfferChecklistResource::class,
                \App\Filament\Resources\BillsWithoutDocumentsResource::class,
                \App\Filament\Resources\TransactionsOutWithoutBillsResource::class,
                \App\Filament\Resources\TransactionsInWithoutInvoicesResource::class,
                \App\Filament\Resources\InvoicesWithSettlementIssuesResource::class,
                \App\Filament\Resources\FilesWithBillingIssuesResource::class,
                \App\Filament\Resources\TransactionsWithoutDocumentsResource::class,
            ])

            ->databaseNotifications()
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->pages([
                BulkAddBranches::class,
                EmploymentContractDraft::class,
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
                    ->label('HR')
                    ->collapsible(),
                NavigationGroup::make()
                    ->label('System')
                    ->collapsible(),
            ]);
    }
}
