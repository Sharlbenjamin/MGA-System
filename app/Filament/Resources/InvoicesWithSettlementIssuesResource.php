<?php

namespace App\Filament\Resources;

use App\Filament\Resources\InvoicesWithSettlementIssuesResource\Pages;
use App\Models\Invoice;
use App\Services\InvoiceSettlementIntegrityService;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class InvoicesWithSettlementIssuesResource extends Resource
{
    protected static ?string $model = Invoice::class;

    protected static ?string $navigationIcon = 'heroicon-o-exclamation-circle';

    protected static ?string $navigationGroup = 'Workflow';

    protected static ?int $navigationSort = 6;

    protected static ?string $navigationLabel = 'Invoices with settlement issues';

    protected static ?string $modelLabel = 'Invoice with settlement issue';

    protected static ?string $pluralModelLabel = 'Invoices with settlement issues';

    protected static ?string $slug = 'invoices-with-settlement-issues';

    public static function getNavigationBadge(): ?string
    {
        $count = InvoiceSettlementIntegrityService::settlementIssueCount();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public static function getEloquentQuery(): Builder
    {
        $pivotSum = InvoiceSettlementIntegrityService::pivotSumSubquerySql();

        return parent::getEloquentQuery()
            ->with(['patient.client', 'file', 'transactions'])
            ->select('invoices.*')
            ->selectRaw("{$pivotSum} as pivot_paid_sum")
            ->selectRaw('(SELECT COUNT(*) FROM invoice_transaction WHERE invoice_transaction.invoice_id = invoices.id) as linked_transaction_count');
    }

    public static function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => InvoiceSettlementIntegrityService::applyIssuesScope($query))
            ->defaultSort('invoice_date', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('issue_type')
                    ->label('Issue')
                    ->badge()
                    ->getStateUsing(fn (Invoice $record): string => InvoiceSettlementIntegrityService::describeIssue(
                        $record,
                        InvoiceSettlementIntegrityService::pivotSumFor($record),
                    ))
                    ->formatStateUsing(fn (string $state): string => InvoiceSettlementIntegrityService::issueTypeLabel($state))
                    ->color(fn (string $state): string => match ($state) {
                        InvoiceSettlementIntegrityService::ISSUE_NO_TRANSACTION_LINK => 'danger',
                        InvoiceSettlementIntegrityService::ISSUE_AMOUNT_MISMATCH => 'warning',
                        InvoiceSettlementIntegrityService::ISSUE_STATUS_UNDERSTATES => 'info',
                        InvoiceSettlementIntegrityService::ISSUE_STATUS_OVERSTATES => 'warning',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('name')
                    ->label('Invoice')
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
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'Paid' => 'success',
                        'Partial' => 'warning',
                        'Unpaid' => 'gray',
                        default => 'info',
                    }),
                Tables\Columns\TextColumn::make('total_amount')
                    ->label('Total')
                    ->money('EUR')
                    ->sortable(),
                Tables\Columns\TextColumn::make('paid_amount')
                    ->label('Stored paid')
                    ->money('EUR')
                    ->sortable(),
                Tables\Columns\TextColumn::make('pivot_paid_sum')
                    ->label('Paid from transactions')
                    ->money('EUR')
                    ->sortable(),
                Tables\Columns\TextColumn::make('linked_transaction_count')
                    ->label('Linked transactions')
                    ->sortable(),
                Tables\Columns\TextColumn::make('file.mga_reference')
                    ->label('File ref')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('invoice_date')
                    ->date()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('issue_type')
                    ->label('Issue type')
                    ->options(InvoiceSettlementIntegrityService::issueTypeLabels())
                    ->query(function (Builder $query, array $data): Builder {
                        $value = $data['value'] ?? null;

                        if (blank($value)) {
                            return $query;
                        }

                        return InvoiceSettlementIntegrityService::applyIssueTypeScope($query, (string) $value);
                    }),
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'Draft' => 'Draft',
                        'Posted' => 'Posted',
                        'Not Sent' => 'Not Sent',
                        'Sent' => 'Sent',
                        'Unpaid' => 'Unpaid',
                        'Partial' => 'Partial',
                        'Paid' => 'Paid',
                    ]),
                Tables\Filters\SelectFilter::make('client')
                    ->relationship('patient.client', 'company_name')
                    ->label('Client')
                    ->searchable()
                    ->preload(),
            ])
            ->actions([
                Tables\Actions\Action::make('edit_invoice')
                    ->label('Edit invoice')
                    ->icon('heroicon-o-pencil-square')
                    ->url(fn (Invoice $record): string => InvoiceResource::getUrl('edit', ['record' => $record])),
                Tables\Actions\Action::make('edit_transaction')
                    ->label('Edit linked transaction')
                    ->icon('heroicon-o-banknotes')
                    ->visible(fn (Invoice $record): bool => $record->transactions()->exists())
                    ->url(function (Invoice $record): string {
                        $transaction = $record->transactions()->first();

                        return TransactionResource::getUrl('edit', ['record' => $transaction]);
                    }),
                Tables\Actions\Action::make('recalculate')
                    ->label('Recalculate from transactions')
                    ->icon('heroicon-o-arrow-path')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Recalculate invoice settlement')
                    ->modalDescription('Update paid amount and status from linked transaction payments.')
                    ->action(function (Invoice $record): void {
                        $fresh = InvoiceSettlementIntegrityService::recalculateIssue($record);

                        Notification::make()
                            ->success()
                            ->title('Invoice recalculated')
                            ->body("{$fresh->name} is now {$fresh->status}.")
                            ->send();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListInvoicesWithSettlementIssues::route('/'),
        ];
    }
}
