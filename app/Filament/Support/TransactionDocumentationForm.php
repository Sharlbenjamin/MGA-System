<?php

namespace App\Filament\Support;

use App\Models\Bill;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Provider;
use App\Models\ProviderBranch;
use App\Models\Transaction;
use App\Models\TransactionAttachment;
use App\Services\GenerateTrxInPdfService;
use App\Services\GenerateTrxOutPdfService;
use App\Services\TransactionDocumentationService;
use Filament\Forms;
use Filament\Forms\Get;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;

class TransactionDocumentationForm
{
    public static function checklistPlaceholder(Transaction $record): Forms\Components\Placeholder
    {
        return Forms\Components\Placeholder::make('documentation_checklist')
            ->label('Documentation checklist')
            ->content(function () use ($record) {
                $record->refresh();
                $service = app(TransactionDocumentationService::class);
                $tasks = $service->getMissingTasks($record);
                $done = collect($tasks)->where('status', 'done')->count();
                $total = count($tasks);

                $lines = collect($tasks)->map(function ($task) {
                    $icon = $task['status'] === 'done' ? '✓' : '⚠';

                    return "{$icon} {$task['label']}";
                })->implode("\n");

                return "Progress: {$done} of {$total} complete\n\n{$lines}";
            })
            ->columnSpanFull();
    }

    public static function schema(Transaction $record): array
    {
        $service = app(TransactionDocumentationService::class);
        $pendingKeys = collect($service->getMissingTasks($record))
            ->where('status', 'pending')
            ->pluck('key')
            ->all();

        return array_filter([
            self::checklistPlaceholder($record),

            Forms\Components\Select::make('related_type')
                ->label('Related type')
                ->options([
                    'Client' => 'Client',
                    'Provider' => 'Provider',
                    'Branch' => 'Branch',
                ])
                ->visible(fn () => in_array('missing_linked_client', $pendingKeys, true)
                    || in_array('missing_linked_provider', $pendingKeys, true))
                ->live(),

            Forms\Components\Select::make('related_id')
                ->label(fn (Get $get) => match ($get('related_type')) {
                    'Provider' => 'Provider',
                    'Branch' => 'Branch',
                    default => 'Client',
                })
                ->options(function (Get $get) {
                    return match ($get('related_type')) {
                        'Provider' => Provider::query()->orderBy('name')->pluck('name', 'id'),
                        'Branch' => ProviderBranch::query()->orderBy('name')->pluck('name', 'id'),
                        default => Client::query()->orderBy('company_name')->pluck('company_name', 'id'),
                    };
                })
                ->searchable()
                ->visible(fn () => in_array('missing_linked_client', $pendingKeys, true)
                    || in_array('missing_linked_provider', $pendingKeys, true)),

            Forms\Components\Select::make('invoices')
                ->label('Link invoices')
                ->multiple()
                ->options(function () use ($record) {
                    $clientId = $record->related_type === 'Client' ? $record->related_id : null;

                    $query = Invoice::query()->with('patient');

                    if ($clientId) {
                        $query->whereHas('patient', fn ($q) => $q->where('client_id', $clientId));
                    }

                    return $query->orderByDesc('id')->limit(200)->get()
                        ->mapWithKeys(fn (Invoice $invoice) => [
                            $invoice->id => $invoice->name . ' — ' . ($invoice->patient?->name ?? ''),
                        ])->all();
                })
                ->default(fn () => $record->invoices()->pluck('invoices.id')->all())
                ->visible(fn () => in_array('missing_linked_invoices', $pendingKeys, true)),

            Forms\Components\Select::make('bills')
                ->label('Link bills')
                ->multiple()
                ->options(function () use ($record) {
                    return Bill::query()->orderByDesc('id')->limit(200)->pluck('name', 'id')->all();
                })
                ->default(fn () => $record->bills()->pluck('bills.id')->all())
                ->visible(fn () => in_array('missing_linked_bills', $pendingKeys, true)),

            Forms\Components\FileUpload::make('attachment')
                ->label(fn () => in_array('missing_expense_receipt', $pendingKeys, true)
                    ? 'Expense receipt'
                    : 'Card payment receipt')
                ->acceptedFileTypes(['application/pdf', 'image/*'])
                ->disk('public')
                ->directory('transactions/receipts')
                ->visibility('public')
                ->maxFiles(1)
                ->preserveFilenames()
                ->downloadable()
                ->openable()
                ->required(fn () => in_array('missing_expense_receipt', $pendingKeys, true)
                    || in_array('missing_card_receipt', $pendingKeys, true))
                ->visible(fn () => in_array('missing_expense_receipt', $pendingKeys, true)
                    || in_array('missing_card_receipt', $pendingKeys, true))
                ->default(fn () => self::localAttachmentDefault($record)),

            Forms\Components\Placeholder::make('undocumented_invoices')
                ->label('Invoices missing documents')
                ->content(function () use ($record, $pendingKeys) {
                    if (! in_array('missing_invoice_documents', $pendingKeys, true)) {
                        return '';
                    }

                    return $record->invoices
                        ->filter(fn (Invoice $invoice) => ! app(TransactionDocumentationService::class)->invoiceHasDocument($invoice))
                        ->map(fn (Invoice $invoice) => $invoice->name . ' — edit invoice to upload document')
                        ->implode("\n") ?: 'None';
                })
                ->visible(fn () => in_array('missing_invoice_documents', $pendingKeys, true))
                ->columnSpanFull(),

            Forms\Components\Placeholder::make('undocumented_bills')
                ->label('Bills missing documents')
                ->content(function () use ($record, $pendingKeys) {
                    if (! in_array('missing_bill_documents', $pendingKeys, true)) {
                        return '';
                    }

                    return $record->bills
                        ->filter(fn (Bill $bill) => ! app(TransactionDocumentationService::class)->billHasDocument($bill))
                        ->map(fn (Bill $bill) => $bill->name . ' — edit bill to upload document')
                        ->implode("\n") ?: 'None';
                })
                ->visible(fn () => in_array('missing_bill_documents', $pendingKeys, true))
                ->columnSpanFull(),

            Forms\Components\Toggle::make('generate_trx_in_pdf')
                ->label('Generate Trx In PDF')
                ->default(true)
                ->visible(fn () => in_array('missing_trx_in_pdf', $pendingKeys, true)),

            Forms\Components\Toggle::make('generate_trx_out_pdf')
                ->label('Generate Trx Out PDF')
                ->default(true)
                ->visible(fn () => in_array('missing_trx_out_pdf', $pendingKeys, true)),
        ]);
    }

    public static function apply(Transaction $record, array $data): void
    {
        if (! empty($data['related_type']) && ! empty($data['related_id'])) {
            $record->related_type = $data['related_type'];
            $record->related_id = $data['related_id'];
        }

        if (! empty($data['invoices'])) {
            $sync = [];
            foreach ($data['invoices'] as $invoiceId) {
                $invoice = Invoice::find($invoiceId);
                if ($invoice) {
                    $sync[$invoiceId] = ['amount_paid' => $invoice->total_amount];
                }
            }
            $record->invoices()->sync($sync);
        }

        if (! empty($data['bills'])) {
            $sync = [];
            foreach ($data['bills'] as $billId) {
                $bill = Bill::find($billId);
                if ($bill) {
                    $sync[$billId] = ['amount_paid' => $bill->total_amount];
                }
            }
            $record->bills()->sync($sync);
        }

        $path = self::normalizeUploadedFilePath($data['attachment'] ?? null);
        if ($path) {
            $type = match (true) {
                $record->type === 'Expense' => 'expense_receipt',
                $record->type === 'Outflow' && $record->bills()->exists() => 'payment_proof',
                default => 'card_receipt',
            };

            $record->attachment_path = $path;

            TransactionAttachment::updateOrCreate(
                [
                    'transaction_id' => $record->id,
                    'type' => $type,
                ],
                [
                    'file_path' => $path,
                    'original_name' => basename($path),
                    'uploaded_by' => Auth::id(),
                ],
            );
        }

        $record->updated_by = Auth::id();
        $record->save();

        $record->refresh();

        if (! empty($data['generate_trx_in_pdf']) && $record->type === 'Income') {
            app(GenerateTrxInPdfService::class)->generate($record);
        }

        if (! empty($data['generate_trx_out_pdf']) && $record->type === 'Outflow' && $record->bills()->exists()) {
            app(GenerateTrxOutPdfService::class)->generate($record);
        }

        app(TransactionDocumentationService::class)->syncAndRecalculate($record->fresh());

        Notification::make()
            ->success()
            ->title('Documentation updated')
            ->body('Transaction documentation has been saved.')
            ->send();
    }

    public static function localAttachmentDefault(?Transaction $record): ?array
    {
        $existingPath = $record?->attachment_path;

        if (is_string($existingPath) && $existingPath !== ''
            && ! str_starts_with($existingPath, 'http')
            && ! str_contains($existingPath, 'drive.google.com')) {
            return [$existingPath];
        }

        return null;
    }

    public static function makeTableAction(): \Filament\Tables\Actions\Action
    {
        return \Filament\Tables\Actions\Action::make('completeDocumentation')
            ->label('Complete documentation')
            ->icon('heroicon-o-clipboard-document-check')
            ->color('warning')
            ->visible(fn (Transaction $record) => app(TransactionDocumentationService::class)->resolveDocumentationStatus($record) !== 'complete')
            ->modalHeading('Complete documentation')
            ->modalDescription('Resolve missing links, attachments, and PDFs in one place.')
            ->modalSubmitActionLabel('Save & update')
            ->fillForm(fn (Transaction $record) => [
                'related_type' => $record->related_type,
                'related_id' => $record->related_id,
                'invoices' => $record->invoices()->pluck('invoices.id')->all(),
                'bills' => $record->bills()->pluck('bills.id')->all(),
                'attachment' => self::localAttachmentDefault($record),
            ])
            ->form(fn (Transaction $record) => self::schema($record))
            ->action(function (Transaction $record, array $data) {
                self::apply($record, $data);
            });
    }

    public static function makeHeaderAction(): \Filament\Actions\Action
    {
        return \Filament\Actions\Action::make('completeDocumentation')
            ->label('Complete documentation')
            ->icon('heroicon-o-clipboard-document-check')
            ->color('warning')
            ->visible(fn (Transaction $record) => app(TransactionDocumentationService::class)->resolveDocumentationStatus($record) !== 'complete')
            ->modalHeading('Complete documentation')
            ->modalSubmitActionLabel('Save & update')
            ->fillForm(fn (Transaction $record) => [
                'related_type' => $record->related_type,
                'related_id' => $record->related_id,
                'invoices' => $record->invoices()->pluck('invoices.id')->all(),
                'bills' => $record->bills()->pluck('bills.id')->all(),
                'attachment' => self::localAttachmentDefault($record),
            ])
            ->form(fn (Transaction $record) => self::schema($record))
            ->action(function (array $data, \Livewire\Component $livewire): void {
                if (! method_exists($livewire, 'getRecord')) {
                    return;
                }

                $record = $livewire->getRecord();
                if (! $record instanceof Transaction) {
                    return;
                }

                self::apply($record, $data);

                if (method_exists($livewire, 'refreshFormData')) {
                    $livewire->refreshFormData([
                        'attachment_path',
                        'documentation_status',
                        'trx_in_pdf_path',
                        'trx_out_pdf_path',
                    ]);
                }
            });
    }

    public static function normalizeUploadedFilePath(mixed $upload): ?string
    {
        if (is_string($upload) && $upload !== '') {
            return $upload;
        }

        if (is_array($upload)) {
            foreach ($upload as $value) {
                if (is_string($value) && $value !== '') {
                    return $value;
                }
            }
        }

        return null;
    }
}
