<?php

namespace App\Filament\Resources\TransactionResource\Pages;

use App\Filament\Resources\TransactionResource;
use App\Imports\TransactionImport;
use App\Models\BankAccount;
use App\Models\Transaction;
use App\Models\TransactionImportBatch;
use App\Services\TransactionDocumentationService;
use App\Services\TransactionImportService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

class ImportTransactions extends Page
{
    protected static string $resource = TransactionResource::class;

    protected static string $view = 'filament.resources.transaction-resource.pages.import-transactions';

    protected static ?string $title = 'Import bank transactions';

    public ?array $data = [];

    public array $parsedRows = [];

    public array $classificationRows = [];

    public ?int $batchId = null;

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Wizard::make([
                    Forms\Components\Wizard\Step::make('Upload')
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
                                ->directory('imports/transactions'),
                            Forms\Components\Select::make('bank_account_id')
                                ->label('Internal bank account')
                                ->options(fn () => BankAccount::query()->where('type', 'Internal')->pluck('beneficiary_name', 'id'))
                                ->searchable(),
                        ]),
                    Forms\Components\Wizard\Step::make('Preview & dedupe')
                        ->schema([
                            Forms\Components\Placeholder::make('dedupe_summary')
                                ->label('Import preview')
                                ->content(fn () => $this->getDedupeSummaryText())
                                ->columnSpanFull(),
                        ]),
                    Forms\Components\Wizard\Step::make('Classify')
                        ->schema([
                            Forms\Components\Repeater::make('rows')
                                ->label('New transactions to import')
                                ->schema([
                                    Forms\Components\Placeholder::make('row_info')
                                        ->label('Row')
                                        ->content(fn (Forms\Get $get) => sprintf(
                                            '%s — €%s — %s',
                                            $get('transaction_date') ?? '?',
                                            number_format((float) ($get('amount') ?? 0), 2),
                                            $get('reference') ?? $get('description') ?? ''
                                        )),
                                    Forms\Components\Select::make('type')
                                        ->options([
                                            'Income' => 'Income (client payment)',
                                            'Outflow' => 'Outflow (provider/card)',
                                            'Expense' => 'Expense',
                                        ])
                                        ->required()
                                        ->live(),
                                    Forms\Components\Select::make('related_type')
                                        ->label('Related type')
                                        ->options(fn (Forms\Get $get) => TransactionResource::relatedTypes($get('type') ?? 'Income'))
                                        ->visible(fn (Forms\Get $get) => in_array($get('type'), ['Income', 'Outflow'], true)),
                                    Forms\Components\TextInput::make('name')
                                        ->label('Transaction name')
                                        ->default(fn (Forms\Get $get) => $get('reference') ?? $get('description')),
                                ])
                                ->defaultItems(0)
                                ->addable(false)
                                ->deletable(false)
                                ->reorderable(false)
                                ->columnSpanFull(),
                        ]),
                ])->columnSpanFull(),
            ])
            ->statePath('data');
    }

    public function parseUpload(): void
    {
        $file = $this->data['excel_file'] ?? null;
        if (is_array($file)) {
            $file = $file[0] ?? null;
        }

        if (! $file) {
            Notification::make()->danger()->title('Upload a file first')->send();

            return;
        }

        $fullPath = storage_path('app/' . ltrim($file, '/'));
        $import = new TransactionImport;
        Excel::import($import, $fullPath);

        $importService = app(TransactionImportService::class);
        $classified = $importService->classifyRows($import->rows);

        $this->parsedRows = $classified['new']->values()->all();
        $this->data['rows'] = collect($this->parsedRows)->map(function ($row) use ($importService) {
            $amount = $importService->parseAmount($row['amount'] ?? null);
            $date = $importService->parseDate($row['transaction_date'] ?? null);

            return [
                'transaction_date' => $date?->format('Y-m-d'),
                'amount' => $amount,
                'reference' => $row['reference'] ?? null,
                'description' => $row['description'] ?? null,
                'type' => $amount && $row['amount'] < 0 ? 'Outflow' : 'Income',
                'name' => $row['reference'] ?? $row['description'] ?? 'Imported transaction',
            ];
        })->all();

        $this->data['_skipped_existing'] = $classified['duplicates_existing']->count();
        $this->data['_skipped_in_file'] = $classified['duplicates_in_file']->count();
        $this->data['_total_rows'] = $import->rows->count();

        Notification::make()
            ->success()
            ->title('File parsed')
            ->body(sprintf(
                '%d new, %d skipped (already exist), %d skipped (duplicate in file)',
                count($this->data['rows']),
                $this->data['_skipped_existing'],
                $this->data['_skipped_in_file']
            ))
            ->send();
    }

    public function confirmImport(): void
    {
        $rows = $this->data['rows'] ?? [];

        if (empty($rows)) {
            Notification::make()->warning()->title('No new rows to import')->send();

            return;
        }

        $importService = app(TransactionImportService::class);
        $docService = app(TransactionDocumentationService::class);

        DB::transaction(function () use ($rows, $importService, $docService) {
            $batch = TransactionImportBatch::create([
                'filename' => is_array($this->data['excel_file'] ?? null)
                    ? basename($this->data['excel_file'][0] ?? 'import.xlsx')
                    : basename($this->data['excel_file'] ?? 'import.xlsx'),
                'imported_by' => Auth::id(),
                'total_rows' => $this->data['_total_rows'] ?? count($rows),
                'imported_count' => 0,
                'skipped_duplicates' => ($this->data['_skipped_existing'] ?? 0) + ($this->data['_skipped_in_file'] ?? 0),
                'status' => 'completed',
            ]);

            $count = 0;

            foreach ($rows as $row) {
                if ($importService->isDuplicate($row, $row['type'] ?? null)) {
                    continue;
                }

                $date = $importService->parseDate($row['transaction_date'] ?? null);
                $amount = $importService->parseAmount($row['amount'] ?? null);

                if (! $date || $amount === null || empty($row['type'])) {
                    continue;
                }

                $transaction = Transaction::create([
                    'name' => $row['name'] ?? 'Imported TRX',
                    'bank_account_id' => $this->data['bank_account_id'] ?? BankAccount::query()->where('type', 'Internal')->value('id'),
                    'related_type' => $row['related_type'] ?? ($row['type'] === 'Expense' ? 'Other' : 'Client'),
                    'related_id' => null,
                    'amount' => $amount,
                    'type' => $row['type'],
                    'date' => $date,
                    'notes' => $row['description'] ?? null,
                    'reference' => $row['reference'] ?? $row['description'] ?? null,
                    'status' => 'Draft',
                    'documentation_status' => 'incomplete',
                    'import_batch_id' => $batch->id,
                    'created_by' => Auth::id(),
                    'updated_by' => Auth::id(),
                ]);

                $docService->syncAndRecalculate($transaction);
                $count++;
            }

            $batch->update(['imported_count' => $count]);
            $this->batchId = $batch->id;
        });

        Notification::make()
            ->success()
            ->title('Import completed')
            ->body('New transactions have been created. Complete documentation from the list.')
            ->send();

        $this->redirect(TransactionResource::getUrl('index'));
    }

    protected function getDedupeSummaryText(): string
    {
        if (empty($this->data['rows']) && empty($this->data['_total_rows'])) {
            return 'Upload a file and click "Parse file" to preview duplicates.';
        }

        return sprintf(
            "Total rows: %d\nNew to import: %d\nSkipped (already in system): %d\nSkipped (duplicate in file): %d",
            $this->data['_total_rows'] ?? 0,
            count($this->data['rows'] ?? []),
            $this->data['_skipped_existing'] ?? 0,
            $this->data['_skipped_in_file'] ?? 0,
        );
    }

    protected function getFormActions(): array
    {
        return [
            Forms\Components\Actions\Action::make('parse')
                ->label('Parse file')
                ->action('parseUpload'),
            Forms\Components\Actions\Action::make('import')
                ->label('Confirm import')
                ->color('success')
                ->action('confirmImport'),
        ];
    }
}
