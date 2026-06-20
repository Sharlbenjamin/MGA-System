<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TransactionsWithoutDocumentsResource\Pages;
use App\Filament\Resources\TransactionResource;
use App\Filament\Support\TransactionDocumentationForm;
use App\Models\Transaction;
use App\Services\GenerateTrxInPdfService;
use App\Services\GenerateTrxOutPdfService;
use App\Services\TransactionDocumentationService;
use App\Services\TransactionDocumentationStatsService;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class TransactionsWithoutDocumentsResource extends Resource
{
    protected static ?string $model = Transaction::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-magnifying-glass';

    protected static ?string $navigationGroup = 'Workflow';

    protected static ?int $navigationSort = 6;

    protected static ?string $navigationLabel = 'Transactions missing documents';

    protected static ?string $modelLabel = 'Transaction missing documents';

    protected static ?string $pluralModelLabel = 'Transactions missing documents';

    public static function getNavigationBadge(): ?string
    {
        return (string) TransactionDocumentationService::scopeWithPendingDocumentTasks(Transaction::query())->count();
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public static function table(Table $table): Table
    {
        $docService = app(TransactionDocumentationService::class);

        return $table
            ->modifyQueryUsing(function (Builder $query): Builder {
                return TransactionDocumentationService::scopeWithPendingDocumentTasks($query)
                    ->with([
                        'bankAccount',
                        'invoices.file.patient.client',
                        'bills.file.patient.client',
                    ]);
            })
            ->defaultSort('date', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('date')->date()->sortable(),
                Tables\Columns\TextColumn::make('amount')->money('EUR')->sortable(),
                Tables\Columns\TextColumn::make('type')->badge(),
                Tables\Columns\TextColumn::make('bankAccount.beneficiary_name')->label('Bank account'),
                Tables\Columns\TextColumn::make('notes')
                    ->label('Comment')
                    ->limit(25)
                    ->toggleable(),
                Tables\Columns\TextColumn::make('documentation_category')
                    ->label('Category')
                    ->formatStateUsing(fn (?string $state, Transaction $record): string => TransactionDocumentationStatsService::categoryLabel(
                        TransactionDocumentationStatsService::resolveCategoryKey($record)
                    )),
                Tables\Columns\TextColumn::make('documentation_status')
                    ->label('Documentation')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'missing_attachment' => 'warning',
                        'missing_generated_pdf' => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('missing_items')
                    ->label('Missing items')
                    ->getStateUsing(fn (Transaction $record): string => implode('; ', $docService->pendingDocumentTaskLabels($record)))
                    ->wrap()
                    ->limit(60),
                Tables\Columns\IconColumn::make('trx_in_pdf')
                    ->label('Trx In PDF')
                    ->boolean()
                    ->getStateUsing(fn (Transaction $record): bool => filled($record->trx_in_pdf_path))
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('danger'),
                Tables\Columns\IconColumn::make('trx_out_pdf')
                    ->label('Trx Out PDF')
                    ->boolean()
                    ->getStateUsing(fn (Transaction $record): bool => filled($record->trx_out_pdf_path))
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('danger'),
                Tables\Columns\IconColumn::make('receipt')
                    ->label('Receipt')
                    ->boolean()
                    ->getStateUsing(fn (Transaction $record): bool => filled($record->attachment_path))
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('danger'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('documentation_filter')
                    ->label('Missing docs filter')
                    ->options([
                        'all' => 'All missing docs',
                        'missing_generated_pdf' => 'Missing generated PDF only',
                        'missing_attachment' => 'Missing attachment only',
                    ])
                    ->default('all')
                    ->query(function (Builder $query, array $data): Builder {
                        return match ($data['value'] ?? 'all') {
                            'missing_generated_pdf' => $query->where('documentation_status', 'missing_generated_pdf'),
                            'missing_attachment' => $query->where('documentation_status', 'missing_attachment'),
                            default => $query,
                        };
                    }),
                Tables\Filters\SelectFilter::make('type')
                    ->options([
                        'Income' => 'Income',
                        'Outflow' => 'Outflow',
                        'Expense' => 'Expense',
                    ]),
                Tables\Filters\SelectFilter::make('bank_account_id')
                    ->relationship('bankAccount', 'beneficiary_name')
                    ->label('Bank account')
                    ->searchable()
                    ->preload(),
            ])
            ->actions([
                TransactionDocumentationForm::makeTableAction(),
                Tables\Actions\Action::make('edit_transaction')
                    ->label('Edit transaction')
                    ->icon('heroicon-o-pencil-square')
                    ->url(fn (Transaction $record): string => TransactionResource::getUrl('edit', ['record' => $record])),
                Tables\Actions\Action::make('generate_trx_in_pdf')
                    ->label('Generate Trx In PDF')
                    ->icon('heroicon-o-document-text')
                    ->color('info')
                    ->visible(fn (Transaction $record): bool => $docService->canGenerateTrxIn($record)
                        && collect($docService->getMissingTasks($record))
                            ->contains(fn (array $task): bool => $task['key'] === 'missing_trx_in_pdf' && $task['status'] === 'pending'))
                    ->requiresConfirmation()
                    ->action(function (Transaction $record) use ($docService): void {
                        if (! $docService->canGenerateTrxIn($record)) {
                            Notification::make()->warning()->title('Cannot generate Trx In PDF')->body($docService->getTrxInSkipReason($record))->send();

                            return;
                        }

                        app(GenerateTrxInPdfService::class)->generate($record);
                        $docService->syncAndRecalculate($record->fresh());
                        Notification::make()->success()->title('Trx In PDF generated')->send();
                    }),
                Tables\Actions\Action::make('generate_trx_out_pdf')
                    ->label('Generate Trx Out PDF')
                    ->icon('heroicon-o-document-text')
                    ->color('info')
                    ->visible(fn (Transaction $record): bool => $docService->canGenerateTrxOut($record)
                        && collect($docService->getMissingTasks($record))
                            ->contains(fn (array $task): bool => $task['key'] === 'missing_trx_out_pdf' && $task['status'] === 'pending'))
                    ->requiresConfirmation()
                    ->action(function (Transaction $record) use ($docService): void {
                        if (! $docService->canGenerateTrxOut($record)) {
                            Notification::make()->warning()->title('Cannot generate Trx Out PDF')->body($docService->getTrxOutSkipReason($record))->send();

                            return;
                        }

                        app(GenerateTrxOutPdfService::class)->generate($record);
                        $docService->syncAndRecalculate($record->fresh());
                        Notification::make()->success()->title('Trx Out PDF generated')->send();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTransactionsWithoutDocuments::route('/'),
        ];
    }
}
