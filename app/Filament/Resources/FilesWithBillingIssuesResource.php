<?php

namespace App\Filament\Resources;

use App\Filament\Resources\FilesWithBillingIssuesResource\Pages;
use App\Models\File;
use App\Services\FileBillingIntegrityService;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Route;

class FilesWithBillingIssuesResource extends Resource
{
    protected static ?string $model = File::class;

    protected static ?string $navigationIcon = 'heroicon-o-scale';

    protected static ?string $navigationGroup = 'Workflow';

    protected static ?int $navigationSort = 8;

    protected static ?string $navigationLabel = 'Billing Mismatches';

    protected static ?string $modelLabel = 'Billing mismatch';

    protected static ?string $pluralModelLabel = 'Billing Mismatches';

    protected static ?string $slug = 'files-with-billing-issues';

    public static function indexRouteName(): string
    {
        return static::getRouteBaseName().'.index';
    }

    public static function tryGetIndexUrl(): ?string
    {
        try {
            if (Route::has(static::indexRouteName())) {
                return static::getUrl('index');
            }
        } catch (\Throwable) {
            //
        }

        return null;
    }

    public static function getNavigationBadge(): ?string
    {
        try {
            $count = FileBillingIntegrityService::billingIssueCount();

            return $count > 0 ? (string) $count : null;
        } catch (\Throwable) {
            return null;
        }
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public static function getEloquentQuery(): Builder
    {
        $billsTotal = FileBillingIntegrityService::billsTotalSubquerySql();
        $invoicesTotal = FileBillingIntegrityService::invoicesTotalSubquerySql();

        return parent::getEloquentQuery()
            ->with(['patient.client', 'bills', 'invoices'])
            ->select('files.*')
            ->selectRaw("{$billsTotal} as bills_total_sum")
            ->selectRaw("{$invoicesTotal} as invoices_total_sum")
            ->selectRaw("({$invoicesTotal} - {$billsTotal}) as margin_delta");
    }

    public static function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => FileBillingIntegrityService::applyIssuesScope($query))
            ->defaultSort('service_date', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('issue_types')
                    ->label('Issues')
                    ->badge()
                    ->getStateUsing(fn (File $record): array => FileBillingIntegrityService::describeIssues($record))
                    ->formatStateUsing(fn (string $state): string => FileBillingIntegrityService::issueTypeLabel($state))
                    ->color(fn (string $state): string => match ($state) {
                        FileBillingIntegrityService::ISSUE_BILLS_EXCEED_INVOICE => 'danger',
                        FileBillingIntegrityService::ISSUE_BILL_AFTER_INVOICE_SENT => 'info',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('mga_reference')
                    ->label('File')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('patient.client.company_name')
                    ->label('Client')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('patient.name')
                    ->label('Patient')
                    ->searchable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('bills_total_sum')
                    ->label('Bills total')
                    ->state(fn (File $record): float => FileBillingIntegrityService::billsTotalFor($record))
                    ->money('EUR')
                    ->sortable(),
                Tables\Columns\TextColumn::make('invoices_total_sum')
                    ->label('Invoice total')
                    ->state(fn (File $record): float => FileBillingIntegrityService::invoicesTotalFor($record))
                    ->money('EUR')
                    ->sortable(),
                Tables\Columns\TextColumn::make('margin_delta')
                    ->label('Margin (inv − bills)')
                    ->state(fn (File $record): float => FileBillingIntegrityService::marginDeltaFor($record))
                    ->money('EUR')
                    ->color(fn (File $record): string => FileBillingIntegrityService::marginDeltaFor($record) < 0 ? 'danger' : 'success')
                    ->sortable(),
                Tables\Columns\TextColumn::make('service_date')
                    ->date()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('issue_type')
                    ->label('Issue type')
                    ->options(FileBillingIntegrityService::issueTypeLabels())
                    ->query(function (Builder $query, array $data): Builder {
                        $value = $data['value'] ?? null;

                        if (blank($value)) {
                            return $query;
                        }

                        return FileBillingIntegrityService::applyIssueTypeScope($query, (string) $value);
                    }),
                Tables\Filters\SelectFilter::make('client')
                    ->relationship('patient.client', 'company_name')
                    ->label('Client')
                    ->searchable()
                    ->preload(),
            ])
            ->actions([
                Tables\Actions\Action::make('view_file')
                    ->label('View file')
                    ->icon('heroicon-o-document-text')
                    ->url(fn (File $record): string => FileResource::getUrl('view', ['record' => $record])),
                Tables\Actions\Action::make('edit_invoice')
                    ->label('Edit invoice')
                    ->icon('heroicon-o-pencil-square')
                    ->visible(fn (File $record): bool => $record->invoices()->exists())
                    ->url(function (File $record): string {
                        $invoice = $record->invoices()->latest('id')->first();

                        return InvoiceResource::getUrl('edit', ['record' => $invoice]);
                    }),
                Tables\Actions\Action::make('edit_bill')
                    ->label('Edit bill')
                    ->icon('heroicon-o-banknotes')
                    ->visible(fn (File $record): bool => $record->bills()->exists())
                    ->url(function (File $record): string {
                        $bill = $record->bills()->latest('id')->first();

                        return BillResource::getUrl('edit', ['record' => $bill]);
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListFilesWithBillingIssues::route('/'),
        ];
    }
}
