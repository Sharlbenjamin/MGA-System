<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ClientOfferChecklistResource\Pages;
use App\Filament\Support\FileWorkflowActions;
use App\Filament\Support\FileWorkflowGapFilters;
use App\Filament\Support\GopInOfferForm;
use App\Models\File;
use App\Models\Gop;
use App\Models\User;
use App\Services\ClientOfferMessageFormatter;
use App\Services\FileWorkflowGapService;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class ClientOfferChecklistResource extends Resource
{
    protected static ?string $model = File::class;

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';

    protected static ?string $navigationGroup = 'Workflow';

    protected static ?int $navigationSort = 3;

    protected static ?string $navigationLabel = 'Offer Checklist';

    protected static ?string $modelLabel = 'Offer checklist item';

    protected static ?string $pluralModelLabel = 'Offer Checklist';

    protected static ?string $slug = 'client-offer-checklist';

    public static function shouldRegisterNavigation(): bool
    {
        return static::canViewAny();
    }

    public static function canViewAny(): bool
    {
        $user = Auth::user();

        if (! $user instanceof User) {
            return false;
        }

        try {
            return $user->hasPermissionTo('view File') || (method_exists($user, 'isAdmin') && $user->isAdmin());
        } catch (\Throwable) {
            return method_exists($user, 'isAdmin') && $user->isAdmin();
        }
    }

    public static function getNavigationBadge(): ?string
    {
        try {
            return (string) FileWorkflowGapService::scopeClientOfferChecklistBase(File::query())->count();
        } catch (\Throwable) {
            return null;
        }
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with([
                'patient.client',
                'country',
                'city',
                'serviceType',
                'providerBranch.provider',
                'gops.items',
            ]);
    }

    public static function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => FileWorkflowGapService::scopeClientOfferChecklistBase($query))
            ->columns([
                Tables\Columns\TextColumn::make('mga_reference')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('patient.name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('patient.client.company_name')
                    ->label('Client')
                    ->searchable()
                    ->sortable()
                    ->description(fn (File $record): string => $record->client_reference ?: '—'),
                Tables\Columns\TextColumn::make('country.name')
                    ->label('Country')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('city.name')
                    ->label('City')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('providerBranch.branch_name')
                    ->label('Provider')
                    ->placeholder('Not selected')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('service_date')
                    ->label('Service / Type')
                    ->date('d/m/Y')
                    ->sortable()
                    ->description(fn (File $record): string => $record->serviceType?->name ?? '—'),
                Tables\Columns\TextColumn::make('status')->badge(),
            ])
            ->filters(FileWorkflowGapFilters::forClientOfferChecklist())
            ->actions([
                FileWorkflowActions::viewFile(),
                Tables\Actions\Action::make('generate_offer')
                    ->label('Generate offer')
                    ->icon('heroicon-o-document-plus')
                    ->color('success')
                    ->modalHeading(fn (File $record): string => "Offer for {$record->mga_reference}")
                    ->modalWidth('7xl')
                    ->modalSubmitActionLabel('Generate offer')
                    ->fillForm(fn (File $record): array => array_merge(
                        GopInOfferForm::defaultInState($record),
                        ['status' => Gop::IN_STATUS_OFFERED],
                    ))
                    ->form(fn (File $record): array => GopInOfferForm::schema($record, inOnly: true))
                    ->action(function (File $record, array $data): void {
                        $data['status'] = $data['status'] ?? Gop::IN_STATUS_OFFERED;
                        $gop = GopInOfferForm::persist($record, $data);

                        Notification::make()
                            ->success()
                            ->title('Offer generated')
                            ->body('Total '.$gop->amount.'€. Copy the offer or GOP request from the file GOP tab.')
                            ->send();
                    }),
                Tables\Actions\Action::make('copy_latest_draft')
                    ->label('Copy draft')
                    ->icon('heroicon-o-clipboard-document')
                    ->color('gray')
                    ->visible(function (File $record): bool {
                        return $record->gops
                            ->where('type', 'In')
                            ->isNotEmpty();
                    })
                    ->fillForm(function (File $record): array {
                        $gop = $record->gops->where('type', 'In')->sortByDesc('id')->first();

                        return [
                            'offer_sections' => app(ClientOfferMessageFormatter::class)->normalizeSections($gop?->offer_sections),
                            'message' => $gop
                                ? app(ClientOfferMessageFormatter::class)->formatOffer($record, $gop)
                                : '',
                        ];
                    })
                    ->form(function (File $record): array {
                        $gop = $record->gops->where('type', 'In')->sortByDesc('id')->first();

                        if (! $gop) {
                            return [];
                        }

                        return GopInOfferForm::copyFormSchema($record, $gop, ClientOfferMessageFormatter::MODE_OFFER);
                    })
                    ->modalSubmitActionLabel('Copy')
                    ->action(function (File $record, array $data, $livewire): void {
                        GopInOfferForm::copyToClipboard(
                            (string) ($data['message'] ?? ''),
                            'Client offer',
                            $livewire,
                        );
                    }),
                Tables\Actions\Action::make('copy_gop_request')
                    ->label('Copy GOP')
                    ->icon('heroicon-o-clipboard')
                    ->color('gray')
                    ->visible(function (File $record): bool {
                        return $record->gops
                            ->where('type', 'In')
                            ->isNotEmpty();
                    })
                    ->fillForm(function (File $record): array {
                        $gop = $record->gops->where('type', 'In')->sortByDesc('id')->first();

                        return [
                            'offer_sections' => app(ClientOfferMessageFormatter::class)->normalizeSections($gop?->offer_sections),
                            'message' => $gop
                                ? app(ClientOfferMessageFormatter::class)->formatRequest($record, $gop)
                                : '',
                        ];
                    })
                    ->form(function (File $record): array {
                        $gop = $record->gops->where('type', 'In')->sortByDesc('id')->first();

                        if (! $gop) {
                            return [];
                        }

                        return GopInOfferForm::copyFormSchema($record, $gop, ClientOfferMessageFormatter::MODE_REQUEST);
                    })
                    ->modalSubmitActionLabel('Copy')
                    ->action(function (File $record, array $data, $livewire): void {
                        GopInOfferForm::copyToClipboard(
                            (string) ($data['message'] ?? ''),
                            'GOP request',
                            $livewire,
                        );
                    }),
            ])
            ->defaultSort('service_date', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListClientOfferChecklist::route('/'),
        ];
    }
}
