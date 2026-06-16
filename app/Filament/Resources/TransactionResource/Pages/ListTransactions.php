<?php

namespace App\Filament\Resources\TransactionResource\Pages;

use App\Exports\BankStatementTransactionsExport;
use App\Filament\Resources\TransactionResource;
use App\Filament\Support\ImportBankTransactionsAction;
use App\Filament\Widgets\TransactionDocumentationStatsWidget;
use App\Services\BulkTransactionPdfService;
use Carbon\Carbon;
use Filament\Actions;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Concerns\ExposesTableToWidgets;
use Filament\Resources\Pages\ListRecords;
use Livewire\Attributes\On;
use Maatwebsite\Excel\Facades\Excel;

class ListTransactions extends ListRecords
{
    use ExposesTableToWidgets;

    protected static string $resource = TransactionResource::class;

    protected function getHeaderActions(): array
    {
        return [
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
                ->action(function (array $data): void {
                    $result = app(BulkTransactionPdfService::class)->generateForPeriod(
                        (int) $data['year'],
                        (string) $data['quarter'],
                        (string) $data['scope'],
                        (bool) ($data['regenerate_existing'] ?? false),
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
                ->action(function (array $data) {
                    $url = route('lawyer.export', [
                        'year' => $data['year'],
                        'quarter' => $data['quarter'],
                        'iva_percent' => $data['iva_percent'] ?? 21,
                        'nif_source' => $data['nif_source'] ?? 'country',
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
            ImportBankTransactionsAction::make(),
            Actions\CreateAction::make(),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            TransactionDocumentationStatsWidget::class,
        ];
    }

    #[On('apply-transaction-documentation-filter')]
    public function applyDocumentationFilter(string $workflow, string $completion = 'all'): void
    {
        $filters = $this->tableFilters ?? [];

        $filters['documentation_workflow'] = ['value' => $workflow];
        unset($filters['type']);

        if ($completion === 'completed') {
            $filters['documentation_status'] = ['values' => ['complete']];
        } elseif ($completion === 'uncompleted') {
            $filters['documentation_status'] = ['values' => ['incomplete']];
        } else {
            unset($filters['documentation_status']);
        }

        $this->tableFilters = $filters;
        $this->resetTable();
    }
}
