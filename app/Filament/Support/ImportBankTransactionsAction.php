<?php

namespace App\Filament\Support;

use App\Exports\BankStatementImportTemplateExport;
use App\Services\TransactionImportService;
use Filament\Actions;
use Filament\Forms;
use Filament\Forms\Get;
use Filament\Notifications\Notification;
use Illuminate\Support\HtmlString;
use Maatwebsite\Excel\Facades\Excel;

class ImportBankTransactionsAction
{
    public static function make(int $bankAccountId): Actions\Action
    {
        return Actions\Action::make('importBankTransactions')
            ->label('Import bank statement')
            ->icon('heroicon-o-arrow-up-tray')
            ->color('success')
            ->modalHeading('Import bank transactions')
            ->modalDescription('Upload an Excel or CSV export from your bank. Duplicates on this account are skipped automatically.')
            ->modalSubmitActionLabel('Import transactions')
            ->modalWidth('2xl')
            ->form([
                Forms\Components\FileUpload::make('file')
                    ->label('Bank statement file')
                    ->acceptedFileTypes([
                        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                        'application/vnd.ms-excel',
                        'text/csv',
                        'text/plain',
                    ])
                    ->required()
                    ->disk('local')
                    ->directory('transaction-imports')
                    ->visibility('private')
                    ->maxSize(10240)
                    ->live()
                    ->helperText('Supports .xlsx, .xls, .csv. Required: transaction_date (or date / fecha). Use debit/credit columns or amount + type.'),
                Forms\Components\Placeholder::make('import_preview')
                    ->label('Preview')
                    ->visible(fn (Get $get): bool => filled($get('file')))
                    ->content(function (Get $get) use ($bankAccountId): HtmlString {
                        $file = $get('file');

                        if (blank($file)) {
                            return new HtmlString('');
                        }

                        try {
                            $service = app(TransactionImportService::class);
                            $path = is_array($file) ? ($file[0] ?? null) : $file;

                            if (blank($path)) {
                                return new HtmlString('<p class="text-sm text-danger-600">Invalid file upload.</p>');
                            }

                            $rows = $service->parseRowsFromPath($service->resolveUploadedPath($path));
                            $preview = $service->preview($rows, $bankAccountId);

                            return new HtmlString(view('filament.transactions.import-preview', [
                                'preview' => $preview,
                            ])->render());
                        } catch (\Throwable $e) {
                            return new HtmlString('<p class="text-sm text-danger-600">'.e($e->getMessage()).'</p>');
                        }
                    }),
            ])
            ->action(function (array $data) use ($bankAccountId): void {
                $file = $data['file'] ?? null;
                $path = is_array($file) ? ($file[0] ?? null) : $file;

                if (blank($path)) {
                    Notification::make()
                        ->title('No file uploaded')
                        ->danger()
                        ->send();

                    return;
                }

                $service = app(TransactionImportService::class);
                $absolutePath = $service->resolveUploadedPath($path);
                $originalName = basename($path);

                try {
                    $rows = $service->parseRowsFromPath($absolutePath);
                    $result = $service->import($rows, $bankAccountId, $originalName);

                    $body = sprintf(
                        "Imported: %d\nSkipped (existing): %d\nSkipped (in file): %d\nFailed: %d\nBatch #%d",
                        $result['imported'],
                        $result['skipped_existing'],
                        $result['skipped_in_file'],
                        $result['failed'],
                        $result['batch_id'],
                    );

                    if ($result['errors'] !== []) {
                        $body .= "\n\nErrors (sample):\n".implode("\n", array_slice($result['errors'], 0, 5));
                    }

                    $notification = Notification::make()
                        ->title($result['failed'] > 0 ? 'Import completed with errors' : 'Import completed')
                        ->body($body);

                    if ($result['failed'] > 0) {
                        $notification->warning()->send();
                    } else {
                        $notification->success()->send();
                    }
                } catch (\Throwable $e) {
                    Notification::make()
                        ->title('Import failed')
                        ->body($e->getMessage())
                        ->danger()
                        ->send();
                }
            });
    }

    public static function downloadTemplateAction(): Actions\Action
    {
        return Actions\Action::make('downloadImportTemplate')
            ->label('Download import template')
            ->icon('heroicon-o-document-arrow-down')
            ->color('gray')
            ->action(function () {
                $filename = 'bank-transaction-import-template-'.now()->format('Y-m-d').'.xlsx';

                return Excel::download(new BankStatementImportTemplateExport, $filename);
            });
    }
}
