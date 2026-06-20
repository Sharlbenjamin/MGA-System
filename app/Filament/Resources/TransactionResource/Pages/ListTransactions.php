<?php

namespace App\Filament\Resources\TransactionResource\Pages;

use App\Exports\BankStatementTransactionsExport;
use App\Filament\Resources\BankAccountResource;
use App\Filament\Resources\TransactionResource;
use App\Filament\Support\ImportBankTransactionsAction;
use App\Filament\Widgets\TransactionDocumentationStatsWidget;
use App\Models\BankAccount;
use App\Services\BulkTransactionPdfService;
use App\Services\TransactionDocumentationStatsService;
use Carbon\Carbon;
use Filament\Actions;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Concerns\ExposesTableToWidgets;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\On;
use Maatwebsite\Excel\Facades\Excel;

class ListTransactions extends ListRecords
{
    use ExposesTableToWidgets;

    protected static string $resource = TransactionResource::class;

    public BankAccount $bankAccount;

    public ?string $activeWidgetCategory = null;

    public ?string $activeWidgetCompletion = null;

    public ?string $activeWidgetDocumentationStatus = null;

    public ?string $activeWidgetDataIssue = null;

    public function mount(?int $bankAccountId = null): void
    {
        $resolved = request()->route('bankAccount');

        if ($resolved instanceof BankAccount) {
            $this->bankAccount = $resolved;
        } elseif ($bankAccountId !== null) {
            $this->bankAccount = BankAccount::query()->findOrFail($bankAccountId);
        } elseif ($resolved !== null) {
            $this->bankAccount = BankAccount::query()->findOrFail($resolved);
        } else {
            abort(404);
        }

        $this->authorizeAccess();

        $this->loadDefaultActiveTab();
    }

    public function getBreadcrumbs(): array
    {
        return [
            BankAccountResource::getUrl('index') => BankAccountResource::getBreadcrumb(),
            '#' => $this->getTitle(),
        ];
    }

    public function getTitle(): string
    {
        return 'Bank Transactions';
    }

    public function getSubheading(): ?string
    {
        return $this->bankAccount->beneficiary_name.($this->bankAccount->iban ? ' · '.$this->bankAccount->iban : '');
    }

    protected function getTableQuery(): ?Builder
    {
        return parent::getTableQuery()
            ->where('bank_account_id', $this->bankAccount->id);
    }

    protected function getHeaderActions(): array
    {
        $bankAccountId = $this->bankAccount->id;

        return [
            ImportBankTransactionsAction::make($bankAccountId),
            ImportBankTransactionsAction::downloadTemplateAction(),
            Actions\Action::make('bulkGeneratePdfs')
                ->label('Bulk Generate PDFs')
                ->icon('heroicon-o-document-plus')
                ->color('warning')
                ->form([
                    TextInput::make('year')
                        ->label('Year')
                        ->numeric()
                        ->default(Carbon::now()->year)
                        ->required(),
                    Select::make('quarter')
                        ->label('Quarter')
                        ->options([
                            '1' => 'Q1',
                            '2' => 'Q2',
                            '3' => 'Q3',
                            '4' => 'Q4',
                            'full' => 'Full Year',
                        ])
                        ->default((string) Carbon::now()->quarter)
                        ->required(),
                    Select::make('scope')
                        ->label('Scope')
                        ->options([
                            'receivables' => 'Receivables (Trx In)',
                            'bulk_bills' => 'Bulk Bills (Trx Out)',
                            'both' => 'Both',
                        ])
                        ->default('both')
                        ->required(),
                    Toggle::make('regenerate_existing')
                        ->label('Regenerate existing PDFs')
                        ->default(false),
                ])
                ->action(function (array $data) use ($bankAccountId): void {
                    $result = app(BulkTransactionPdfService::class)->generateForPeriod(
                        (int) $data['year'],
                        (string) $data['quarter'],
                        (string) $data['scope'],
                        (bool) ($data['regenerate_existing'] ?? false),
                        $bankAccountId,
                    );

                    $body = sprintf(
                        'Generated: %d | Skipped: %d | Failed: %d',
                        $result->generated,
                        $result->skipped,
                        $result->failed,
                    );

                    if ($result->skippedDetails !== []) {
                        $preview = collect($result->skippedDetails)
                            ->take(5)
                            ->map(fn (array $detail) => "#{$detail['transaction_id']}: {$detail['reason']}")
                            ->implode("\n");
                        $body .= "\n\nSkipped (sample):\n".$preview;
                    }

                    if ($result->failedDetails !== []) {
                        $preview = collect($result->failedDetails)
                            ->take(5)
                            ->map(fn (array $detail) => "#{$detail['transaction_id']}: {$detail['error']}")
                            ->implode("\n");
                        $body .= "\n\nFailed:\n".$preview;
                    }

                    $notification = Notification::make()
                        ->title($result->failed > 0 ? 'Bulk PDF generation completed with errors' : 'Bulk PDF generation completed')
                        ->body($body);

                    if ($result->failed > 0) {
                        $notification->danger()->send();
                    } else {
                        $notification->success()->send();
                    }
                }),
            Actions\Action::make('exportForLawyer')
                ->label('Export for Lawyer')
                ->icon('heroicon-o-briefcase')
                ->color('primary')
                ->form([
                    TextInput::make('year')
                        ->label('Year')
                        ->numeric()
                        ->default(Carbon::now()->year)
                        ->required(),
                    Select::make('quarter')
                        ->label('Quarter')
                        ->options([
                            '1' => 'Q1',
                            '2' => 'Q2',
                            '3' => 'Q3',
                            '4' => 'Q4',
                            'full' => 'Full Year',
                        ])
                        ->default((string) Carbon::now()->quarter)
                        ->required(),
                    TextInput::make('iva_percent')
                        ->label('IVA %')
                        ->numeric()
                        ->default(21)
                        ->required()
                        ->minValue(0)
                        ->maxValue(100),
                    Select::make('nif_source')
                        ->label('NIF Source')
                        ->options([
                            'country' => 'Country',
                            'niv_number' => 'NIV Number',
                        ])
                        ->default('country')
                        ->required(),
                ])
                ->action(function (array $data) use ($bankAccountId) {
                    $url = route('lawyer.export', [
                        'year' => $data['year'],
                        'quarter' => $data['quarter'],
                        'iva_percent' => $data['iva_percent'] ?? 21,
                        'nif_source' => $data['nif_source'] ?? 'country',
                        'bank_account_id' => $bankAccountId,
                    ]);

                    return redirect($url);
                }),
            Actions\Action::make('exportBankFormat')
                ->label('Export bank format')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->action(function () {
                    $filename = 'bank-transactions-'.now()->format('Y-m-d-His').'.xlsx';

                    return Excel::download(
                        new BankStatementTransactionsExport($this->getFilteredTableQuery()),
                        $filename
                    );
                }),
            Actions\CreateAction::make()
                ->url(fn (): string => TransactionResource::getUrl('create', [
                    'bank_account_id' => $bankAccountId,
                ])),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            TransactionDocumentationStatsWidget::make([
                'bankAccountId' => $this->bankAccount->id,
                'activeCategory' => $this->activeWidgetCategory,
                'activeCompletion' => $this->activeWidgetCompletion,
                'activeDocumentationStatus' => $this->activeWidgetDocumentationStatus,
                'activeDataIssue' => $this->activeWidgetDataIssue,
            ]),
        ];
    }

    #[On('apply-transaction-documentation-filter')]
    public function applyDocumentationFilter(
        string $category,
        string $completion = 'all',
        ?string $documentationStatus = null,
    ): void {
        $filters = $this->tableFilters ?? [];

        $filters['documentation_category'] = ['value' => $category];
        unset($filters['type'], $filters['documentation_workflow']);

        if ($documentationStatus) {
            $filters['documentation_status'] = ['values' => [$documentationStatus]];
        } elseif ($completion === 'completed') {
            $filters['documentation_status'] = ['values' => ['complete']];
        } elseif ($completion === 'uncompleted') {
            $filters['documentation_status'] = ['values' => TransactionDocumentationStatsService::uncompletedDocumentationStatuses()];
        } else {
            unset($filters['documentation_status']);
        }

        unset(
            $filters['linking_status_mismatch'],
            $filters['data_integrity_paid_invoice'],
        );

        $this->activeWidgetCategory = $category;
        $this->activeWidgetCompletion = $documentationStatus ? null : $completion;
        $this->activeWidgetDocumentationStatus = $documentationStatus;
        $this->activeWidgetDataIssue = null;

        $this->tableFilters = $filters;
        $this->resetTable();
    }

    #[On('apply-transaction-data-integrity-filter')]
    public function applyDataIntegrityFilter(string $issueKey, ?string $category = null): void
    {
        $filters = $this->tableFilters ?? [];

        unset(
            $filters['linking_status_mismatch'],
            $filters['data_integrity_paid_invoice'],
        );

        if ($category) {
            $filters['documentation_category'] = ['value' => $category];
        }

        match ($issueKey) {
            'transaction_invoice_total_mismatch' => $filters['linking_status_mismatch'] = ['isActive' => true],
            'paid_amount_mismatch' => $filters['data_integrity_paid_invoice'] = ['isActive' => true],
            default => null,
        };

        $this->activeWidgetCategory = $category;
        $this->activeWidgetCompletion = null;
        $this->activeWidgetDocumentationStatus = null;
        $this->activeWidgetDataIssue = $issueKey;

        $this->tableFilters = $filters;
        $this->resetTable();
    }

    #[On('apply-transaction-status-filter')]
    public function applyStatusFilter(string $documentationStatus): void
    {
        $filters = $this->tableFilters ?? [];

        unset(
            $filters['documentation_category'],
            $filters['documentation_workflow'],
            $filters['type'],
            $filters['linking_status_mismatch'],
            $filters['data_integrity_paid_invoice'],
        );

        if ($documentationStatus === 'all') {
            unset($filters['documentation_status']);
            $this->activeWidgetDocumentationStatus = null;
        } else {
            $filters['documentation_status'] = ['values' => [$documentationStatus]];
            $this->activeWidgetDocumentationStatus = $documentationStatus;
        }

        $this->activeWidgetCategory = null;
        $this->activeWidgetCompletion = null;
        $this->activeWidgetDataIssue = null;

        $this->tableFilters = $filters;
        $this->resetTable();
    }

    #[On('clear-transaction-documentation-filter')]
    public function clearDocumentationFilter(): void
    {
        $this->activeWidgetCategory = null;
        $this->activeWidgetCompletion = null;
        $this->activeWidgetDocumentationStatus = null;
        $this->activeWidgetDataIssue = null;

        $this->tableFilters = [];
        $this->resetTable();
    }
}
