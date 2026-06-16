<?php

namespace App\Filament\Support;

use App\Models\BankAccount;
use App\Services\TransactionDocumentationService;
use App\Services\TransactionImportService;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Forms\Components\Wizard\Step;
use Filament\Notifications\Notification;

class ImportBankTransactionsAction
{
    public static function make(): Action
    {
        return Action::make('importExcel')
            ->label('Import Excel')
            ->icon('heroicon-o-arrow-up-tray')
            ->color('gray')
            ->modalWidth('7xl')
            ->modalSubmitActionLabel('Confirm import')
            ->steps([
                Step::make('Upload')
                    ->description('Upload your Santander MovimientosCuenta or internal bank export.')
                    ->schema([
                        Forms\Components\FileUpload::make('excel_file')
                            ->label('Bank statement (Excel)')
                            ->acceptedFileTypes([
                                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                                'application/vnd.ms-excel',
                                'text/csv',
                            ])
                            ->required()
                            ->disk('local')
                            ->directory('imports/transactions')
                            ->storeFileNamesIn('excel_original_filename'),
                        Forms\Components\Select::make('bank_account_id')
                            ->label('Internal bank account')
                            ->options(fn () => BankAccount::query()->where('type', 'Internal')->pluck('beneficiary_name', 'id'))
                            ->default(fn () => ImportBankTransactions::defaultBankAccountId())
                            ->required()
                            ->searchable(),
                        Forms\Components\Hidden::make('rows'),
                        Forms\Components\Hidden::make('total_rows'),
                        Forms\Components\Hidden::make('skipped_existing'),
                        Forms\Components\Hidden::make('skipped_in_file'),
                        Forms\Components\Hidden::make('format'),
                    ])
                    ->afterValidation(function (Forms\Get $get, Forms\Set $set): void {
                        $file = $get('excel_file');
                        if (is_array($file)) {
                            $file = $file[0] ?? null;
                        }

                        if (! $file) {
                            return;
                        }

                        $parsed = app(ImportBankTransactions::class)->parseUploadedFile($file);

                        $set('rows', $parsed['rows']);
                        $set('total_rows', $parsed['total_rows']);
                        $set('skipped_existing', $parsed['skipped_existing']);
                        $set('skipped_in_file', $parsed['skipped_in_file']);
                        $set('format', $parsed['format']);
                    }),
                Step::make('Preview')
                    ->description('Review duplicate detection before classifying rows.')
                    ->schema([
                        Forms\Components\Placeholder::make('dedupe_summary')
                            ->label('Import preview')
                            ->content(fn (Forms\Get $get): string => ImportBankTransactions::dedupeSummaryText([
                                'rows' => $get('rows') ?? [],
                                'total_rows' => $get('total_rows') ?? 0,
                                'skipped_existing' => $get('skipped_existing') ?? 0,
                                'skipped_in_file' => $get('skipped_in_file') ?? 0,
                                'format' => $get('format') ?? '',
                            ]))
                            ->columnSpanFull(),
                    ]),
                Step::make('Review')
                    ->description('Confirm type per row. Documentation gaps will show on the list after import.')
                    ->schema([
                        Forms\Components\Select::make('bulk_type')
                            ->label('Set all rows to type')
                            ->placeholder('Choose to apply to every row…')
                            ->options([
                                'Income' => 'Income',
                                'Outflow' => 'Outflow',
                                'Expense' => 'Expense',
                            ])
                            ->live()
                            ->afterStateUpdated(function (?string $state, Forms\Set $set, Forms\Get $get): void {
                                if (! $state) {
                                    return;
                                }

                                $importService = app(TransactionImportService::class);
                                $rows = $get('rows') ?? [];

                                foreach (array_keys($rows) as $index) {
                                    $rows[$index]['type'] = $state;
                                    $rows[$index]['related_type'] = $importService->defaultRelatedType($state);
                                }

                                $set('rows', $rows);
                                $set('bulk_type', null);
                            }),
                        Forms\Components\Repeater::make('rows')
                            ->label('New transactions to import')
                            ->schema([
                                Forms\Components\Grid::make(4)->schema([
                                    Forms\Components\TextInput::make('transaction_date')
                                        ->label('Date')
                                        ->disabled(),
                                    Forms\Components\TextInput::make('amount')
                                        ->label('Amount')
                                        ->prefix('€')
                                        ->disabled(),
                                    Forms\Components\TextInput::make('bank_code')
                                        ->label('Code')
                                        ->disabled(),
                                    Forms\Components\Select::make('type')
                                        ->options([
                                            'Income' => 'Income',
                                            'Outflow' => 'Outflow',
                                            'Expense' => 'Expense',
                                        ])
                                        ->required()
                                        ->live()
                                        ->afterStateUpdated(function (?string $state, Forms\Set $set): void {
                                            if (! $state) {
                                                return;
                                            }
                                            $set('related_type', app(TransactionImportService::class)->defaultRelatedType($state));
                                        }),
                                ]),
                                Forms\Components\Textarea::make('description')
                                    ->label('Item / description')
                                    ->rows(2)
                                    ->disabled()
                                    ->columnSpanFull(),
                                Forms\Components\TextInput::make('reference')
                                    ->label('Reference')
                                    ->disabled()
                                    ->columnSpanFull(),
                                Forms\Components\Placeholder::make('missing_after_import')
                                    ->label('After import')
                                    ->content(fn (Forms\Get $get): string => app(TransactionDocumentationService::class)
                                        ->previewMissingTasksForNewTransaction((string) ($get('type') ?? 'Income')))
                                    ->columnSpanFull(),
                            ])
                            ->defaultItems(0)
                            ->addable(false)
                            ->deletable(false)
                            ->reorderable(false)
                            ->collapsible()
                            ->itemLabel(fn (array $state): string => sprintf(
                                '%s — €%s — %s',
                                $state['transaction_date'] ?? '?',
                                number_format((float) ($state['amount'] ?? 0), 2),
                                $state['bank_code'] ?? ($state['type'] ?? '')
                            ))
                            ->columnSpanFull(),
                    ])
                    ->visible(fn (Forms\Get $get): bool => count($get('rows') ?? []) > 0),
            ])
            ->action(function (array $data): void {
                $rows = $data['rows'] ?? [];

                if ($rows === []) {
                    Notification::make()
                        ->warning()
                        ->title('No new rows to import')
                        ->body('All rows were skipped as duplicates or the file had no valid data.')
                        ->send();

                    return;
                }

                $file = $data['excel_file'] ?? null;
                if (is_array($file)) {
                    $file = $file[0] ?? null;
                }

                $filename = $data['excel_original_filename'] ?? null;
                if (is_array($filename)) {
                    $filename = $filename[array_key_first($filename)] ?? null;
                }
                $filename = $filename ?: ($file ? basename($file) : 'import.xlsx');

                $result = app(ImportBankTransactions::class)->createTransactions(
                    $rows,
                    (int) $data['bank_account_id'],
                    (string) $filename,
                    (int) ($data['total_rows'] ?? count($rows)),
                    (int) (($data['skipped_existing'] ?? 0) + ($data['skipped_in_file'] ?? 0)),
                );

                $idPreview = collect($result['created_ids'])
                    ->take(15)
                    ->map(fn (int $id): string => 'TRX-'.$id)
                    ->implode(', ');

                $body = sprintf(
                    'Created %d transaction(s).',
                    count($result['created_ids']),
                );

                if ($result['skipped'] > 0) {
                    $body .= sprintf(' Skipped %d during import.', $result['skipped']);
                }

                if ($idPreview !== '') {
                    $body .= "\n\nIDs: ".$idPreview;
                    if (count($result['created_ids']) > 15) {
                        $body .= '…';
                    }
                }

                Notification::make()
                    ->success()
                    ->title('Import completed')
                    ->body($body)
                    ->send();
            });
    }
}
