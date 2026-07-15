<?php

namespace App\Filament\Resources;

use App\Filament\Resources\InvoiceChecklistResource\Pages;
use App\Filament\Support\FileWorkflowActions;
use App\Filament\Support\FileWorkflowGapFilters;
use App\Models\File;
use App\Models\User;
use App\Services\FileWorkflowGapService;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class InvoiceChecklistResource extends Resource
{
    protected static ?string $model = File::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-check';

    protected static ?string $navigationGroup = 'Workflow';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Invoice Checklist';

    protected static ?string $modelLabel = 'Invoice checklist item';

    protected static ?string $pluralModelLabel = 'Invoice Checklist';

    protected static ?string $slug = 'invoice-checklist';

    public static function shouldRegisterNavigation(): bool
    {
        return static::userCanAccess();
    }

    public static function canViewAny(): bool
    {
        return static::userCanAccess();
    }

    protected static function userCanAccess(): bool
    {
        $user = Auth::user();

        if (! $user instanceof User) {
            return false;
        }

        return $user->hasAnyRole(['admin', 'Financial']);
    }

    public static function getNavigationBadge(): ?string
    {
        return (string) FileWorkflowGapService::scopeInvoiceChecklistBase(File::query())->count();
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
                'invoices',
                'bills',
                'medicalReports',
            ]);
    }

    public static function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => FileWorkflowGapService::scopeInvoiceChecklistBase($query))
            ->columns([
                Tables\Columns\TextColumn::make('mga_reference')
                    ->searchable()
                    ->sortable()
                    ->description(function (File $record): string {
                        $invoice = FileWorkflowGapService::firstInvoiceNeedingDocument($record);

                        return $invoice?->name ?? '—';
                    }),
                Tables\Columns\TextColumn::make('patient.name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('patient.client.company_name')
                    ->label('Client')
                    ->searchable()
                    ->sortable()
                    ->description(fn (File $record): string => $record->client_reference ?: '—'),
                Tables\Columns\TextColumn::make('providerBranch.provider.name')
                    ->label('Provider')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('service_date')
                    ->label('Service / Type')
                    ->date('d/m/Y')
                    ->sortable()
                    ->description(fn (File $record): string => $record->serviceType?->name ?? '—'),
                Tables\Columns\TextColumn::make('bill_amount')
                    ->label('Bill')
                    ->state(function (File $record): string {
                        if (! $record->relationLoaded('bills')) {
                            $record->load('bills');
                        }

                        if ($record->bills->isEmpty()) {
                            return 'No Bill';
                        }

                        return '€'.number_format((float) $record->bills->sum('total_amount'), 2);
                    })
                    ->badge()
                    ->color(function (File $record): string {
                        if (! $record->relationLoaded('bills')) {
                            $record->load('bills');
                        }

                        if ($record->bills->isEmpty()) {
                            return 'gray';
                        }

                        $hasAttachment = $record->bills->contains(
                            fn ($bill): bool => filled($bill->bill_document_path) || filled($bill->bill_google_link)
                        );

                        return $hasAttachment ? 'success' : 'danger';
                    }),
                Tables\Columns\IconColumn::make('gap_mr')
                    ->label('MR')
                    ->boolean()
                    ->getStateUsing(fn (File $record): bool => ! FileWorkflowGapService::missingMr($record))
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-exclamation-triangle')
                    ->trueColor('success')
                    ->falseColor('warning'),
            ])
            ->filters(FileWorkflowGapFilters::forInvoiceChecklist())
            ->actions([
                Tables\Actions\ActionGroup::make([
                    FileWorkflowActions::viewFile(),
                    FileWorkflowActions::openCreateInvoiceForm(),
                ])->dropdown(false),
                FileWorkflowActions::editInvoiceNeedingDoc(),
                FileWorkflowActions::generateInvoiceDocument(),
            ])
            ->defaultSort('service_date', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListInvoiceChecklist::route('/'),
        ];
    }
}
