<?php

namespace App\Filament\Support;

use App\Filament\Resources\TransactionResource;
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
use App\Services\TransactionDocumentationStatsService;
use App\Services\TransactionSettlementService;
use Filament\Forms;
use Filament\Forms\Get;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class TransactionDocumentationForm
{
    public static function checklistPlaceholder(Transaction $record): Forms\Components\Placeholder
    {
        return Forms\Components\Placeholder::make('documentation_checklist')
            ->label('Documentation checklist')
            ->content(function () use ($record) {
                $service = app(TransactionDocumentationService::class);

                if ($service->isDocumentationSkipped($record)) {
                    $reason = filled($record->documentation_skip_reason)
                        ? "\n\nReason: {$record->documentation_skip_reason}"
                        : '';

                    return 'Documentation skipped — counted as complete.'.$reason;
                }

                if ($service->missingTasksOnHold()) {
                    $status = $record->documentation_status ?? 'incomplete';

                    return 'Checklist recalculation is paused for performance.'."\n\n"
                        .'Stored status: '.$service->formatDocumentationStatusLabel($status);
                }

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
        $pendingKeys = $service->getFormPendingTaskKeys($record);

        $showField = static fn (string $key): bool => in_array($key, $pendingKeys, true);

        return array_filter([
            self::checklistPlaceholder($record),

            Forms\Components\Select::make('related_type')
                ->label('Related type')
                ->options([
                    'Client' => 'Client',
                    'Provider' => 'Provider',
                    'Branch' => 'Branch',
                ])
                ->visible(fn () => $showField('missing_linked_client') || $showField('missing_linked_provider'))
                ->live(),

            Forms\Components\Select::make('related_id')
                ->label(fn (Get $get) => match ($get('related_type')) {
                    'Provider' => 'Provider',
                    'Branch' => 'Branch',
                    default => 'Client',
                })
                ->searchable()
                ->getSearchResultsUsing(function (Get $get, string $search): array {
                    return match ($get('related_type')) {
                        'Provider' => TransactionResource::searchProviderOptions($search),
                        'Branch' => TransactionResource::searchBranchOptions($search),
                        default => TransactionResource::searchClientOptions($search),
                    };
                })
                ->getOptionLabelUsing(function ($value, Get $get): ?string {
                    if (! $value) {
                        return null;
                    }

                    return match ($get('related_type')) {
                        'Provider' => Provider::query()->whereKey($value)->value('name'),
                        'Branch' => ProviderBranch::query()->whereKey($value)->value('name'),
                        default => Client::query()->whereKey($value)->value('company_name'),
                    };
                })
                ->visible(fn () => $showField('missing_linked_client') || $showField('missing_linked_provider')),

            Forms\Components\Select::make('invoices')
                ->label('Link invoices')
                ->multiple()
                ->searchable()
                ->getSearchResultsUsing(function (string $search) use ($record): array {
                    if ($record->related_type !== 'Client' || ! $record->related_id) {
                        return [];
                    }

                    return TransactionResource::searchInvoiceOptions(
                        (int) $record->related_id,
                        $record->id,
                        $search,
                    );
                })
                ->getOptionLabelsUsing(function (array $values): array {
                    if ($values === []) {
                        return [];
                    }

                    return Invoice::query()
                        ->whereIn('id', $values)
                        ->orderByDesc('id')
                        ->get()
                        ->mapWithKeys(fn (Invoice $invoice) => [
                            $invoice->id => TransactionResource::formatInvoiceOptionLabel($invoice),
                        ])
                        ->all();
                })
                ->default(fn () => $record->invoices()->pluck('invoices.id')->all())
                ->visible(fn () => $showField('missing_linked_invoices')),

            Forms\Components\Select::make('bills')
                ->label('Link bills')
                ->multiple()
                ->searchable()
                ->getSearchResultsUsing(function (string $search) use ($record): array {
                    if (! in_array($record->related_type, ['Provider', 'Branch'], true) || ! $record->related_id) {
                        return [];
                    }

                    return TransactionResource::searchBillOptions(
                        $record->related_type,
                        (int) $record->related_id,
                        $record->id,
                        $search,
                    );
                })
                ->getOptionLabelsUsing(function (array $values): array {
                    if ($values === []) {
                        return [];
                    }

                    return Bill::query()
                        ->whereIn('id', $values)
                        ->orderByDesc('id')
                        ->get()
                        ->mapWithKeys(fn (Bill $bill) => [
                            $bill->id => TransactionResource::formatBillOptionLabel($bill),
                        ])
                        ->all();
                })
                ->default(fn () => $record->bills()->pluck('bills.id')->all())
                ->visible(fn () => $showField('missing_linked_bills')),

            Forms\Components\FileUpload::make('attachment')
                ->label(fn () => $showField('missing_expense_receipt')
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
                ->required(fn () => $showField('missing_expense_receipt') || $showField('missing_card_receipt'))
                ->visible(fn () => $showField('missing_expense_receipt') || $showField('missing_card_receipt'))
                ->default(fn () => self::localAttachmentDefault($record)),

            Forms\Components\Placeholder::make('undocumented_invoices')
                ->label('Invoices missing documents')
                ->content(function () use ($record, $showField) {
                    if (! $showField('missing_invoice_documents')) {
                        return '';
                    }

                    $record->loadMissing('invoices');

                    return $record->invoices
                        ->filter(fn (Invoice $invoice) => ! app(TransactionDocumentationService::class)->invoiceHasDocument($invoice))
                        ->map(fn (Invoice $invoice) => $invoice->name.' — edit invoice to upload document')
                        ->implode("\n") ?: 'None';
                })
                ->visible(fn () => $showField('missing_invoice_documents'))
                ->columnSpanFull(),

            Forms\Components\Placeholder::make('undocumented_bills')
                ->label('Bills missing documents')
                ->content(function () use ($record, $showField) {
                    if (! $showField('missing_bill_documents')) {
                        return '';
                    }

                    $record->loadMissing('bills');

                    return $record->bills
                        ->filter(fn (Bill $bill) => ! app(TransactionDocumentationService::class)->billHasDocument($bill))
                        ->map(fn (Bill $bill) => $bill->name.' — edit bill to upload document')
                        ->implode("\n") ?: 'None';
                })
                ->visible(fn () => $showField('missing_bill_documents'))
                ->columnSpanFull(),

            Forms\Components\Toggle::make('generate_trx_in_pdf')
                ->label('Generate Trx In PDF')
                ->default(true)
                ->visible(fn () => $showField('missing_trx_in_pdf')),

            Forms\Components\Toggle::make('generate_trx_out_pdf')
                ->label('Generate Trx Out PDF')
                ->default(true)
                ->visible(fn () => $showField('missing_trx_out_pdf')),
        ]);
    }

    public static function apply(Transaction $record, array $data): void
    {
        $pivotChanged = array_key_exists('invoices', $data) || array_key_exists('bills', $data);

        if (! empty($data['related_type']) && ! empty($data['related_id'])) {
            $record->related_type = $data['related_type'];
            $record->related_id = $data['related_id'];
        }

        if (array_key_exists('invoices', $data)) {
            app(TransactionDocumentationStatsService::class)
                ->syncInvoicesWithInitialAmounts($record, $data['invoices'] ?? []);
        }

        if (array_key_exists('bills', $data)) {
            app(TransactionDocumentationStatsService::class)
                ->syncBills($record, $data['bills'] ?? []);
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

        $record->refresh();

        $settlement = app(TransactionSettlementService::class);

        if ($pivotChanged) {
            $settlement->syncAfterPivotChange($record);
        } else {
            $settlement->syncDocumentation($record);
        }

        if ($record->bank_account_id) {
            TransactionDocumentationStatsService::forgetBankAccountCache((int) $record->bank_account_id);
        }

        Notification::make()
            ->success()
            ->title('Documentation updated')
            ->body('Transaction documentation has been saved.')
            ->send();
    }

    public static function skip(Transaction $record, ?string $reason = null): void
    {
        app(TransactionDocumentationService::class)->skipDocumentation(
            $record,
            $reason,
            Auth::id(),
        );

        Notification::make()
            ->success()
            ->title('Documentation skipped')
            ->body('This transaction is marked complete without links or documents.')
            ->send();
    }

    public static function undoSkip(Transaction $record): void
    {
        app(TransactionDocumentationService::class)->undoSkipDocumentation(
            $record,
            Auth::id(),
        );

        Notification::make()
            ->success()
            ->title('Skip removed')
            ->body('Documentation requirements have been restored for this transaction.')
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
            ->visible(fn (Transaction $record) => self::canShowCompleteDocumentation($record))
            ->modalHeading('Complete documentation')
            ->modalDescription('Resolve missing links, attachments, and PDFs in one place.')
            ->modalSubmitActionLabel('Save & update')
            ->extraModalFooterActions(fn (Transaction $record): array => [
                self::makeSkipModalAction($record),
            ])
            ->fillForm(fn (Transaction $record) => [
                'related_type' => $record->related_type,
                'related_id' => $record->related_id,
                'invoices' => $record->invoices()->pluck('invoices.id')->all(),
                'bills' => $record->bills()->pluck('bills.id')->all(),
                'attachment' => self::localAttachmentDefault($record),
            ])
            ->form(fn (Transaction $record) => self::schema($record))
            ->action(function (Transaction $record, array $data, Component $livewire): void {
                self::apply($record, $data);

                $livewire->dispatch('refresh-transaction-documentation-stats');
            });
    }

    public static function makeHeaderAction(): \Filament\Actions\Action
    {
        return \Filament\Actions\Action::make('completeDocumentation')
            ->label('Complete documentation')
            ->icon('heroicon-o-clipboard-document-check')
            ->color('warning')
            ->visible(fn (Transaction $record) => self::canShowCompleteDocumentation($record))
            ->modalHeading('Complete documentation')
            ->modalSubmitActionLabel('Save & update')
            ->extraModalFooterActions(fn (Transaction $record): array => [
                self::makeSkipModalAction($record),
            ])
            ->fillForm(fn (Transaction $record) => [
                'related_type' => $record->related_type,
                'related_id' => $record->related_id,
                'invoices' => $record->invoices()->pluck('invoices.id')->all(),
                'bills' => $record->bills()->pluck('bills.id')->all(),
                'attachment' => self::localAttachmentDefault($record),
            ])
            ->form(fn (Transaction $record) => self::schema($record))
            ->action(function (array $data, Component $livewire): void {
                if (! method_exists($livewire, 'getRecord')) {
                    return;
                }

                $record = $livewire->getRecord();
                if (! $record instanceof Transaction) {
                    return;
                }

                self::apply($record, $data);

                TransactionEditPageRefresh::refresh($livewire);
            });
    }

    public static function makeSkipTableAction(): \Filament\Tables\Actions\Action
    {
        return \Filament\Tables\Actions\Action::make('skipDocumentation')
            ->label('Skip documentation')
            ->icon('heroicon-o-forward')
            ->color('gray')
            ->visible(fn (Transaction $record) => app(TransactionDocumentationService::class)->canSkipDocumentation($record))
            ->requiresConfirmation()
            ->modalHeading('Skip documentation')
            ->modalDescription('Mark this transaction as complete without client links, invoice links, or documents. Only for Income/Expense that will never be documented.')
            ->form([
                Forms\Components\Textarea::make('reason')
                    ->label('Reason (optional)')
                    ->rows(2)
                    ->maxLength(1000),
            ])
            ->action(function (Transaction $record, array $data, Component $livewire): void {
                self::skip($record, $data['reason'] ?? null);

                $livewire->dispatch('refresh-transaction-documentation-stats');
            });
    }

    public static function makeUndoSkipTableAction(): \Filament\Tables\Actions\Action
    {
        return \Filament\Tables\Actions\Action::make('undoSkipDocumentation')
            ->label('Undo skip')
            ->icon('heroicon-o-arrow-uturn-left')
            ->color('gray')
            ->visible(fn (Transaction $record) => app(TransactionDocumentationService::class)->isDocumentationSkipped($record))
            ->requiresConfirmation()
            ->modalHeading('Undo documentation skip')
            ->modalDescription('Restore normal documentation requirements for this transaction.')
            ->action(function (Transaction $record, Component $livewire): void {
                self::undoSkip($record);

                $livewire->dispatch('refresh-transaction-documentation-stats');
            });
    }

    public static function makeSkipHeaderAction(): \Filament\Actions\Action
    {
        return \Filament\Actions\Action::make('skipDocumentation')
            ->label('Skip documentation')
            ->icon('heroicon-o-forward')
            ->color('gray')
            ->visible(fn (Transaction $record) => app(TransactionDocumentationService::class)->canSkipDocumentation($record))
            ->requiresConfirmation()
            ->modalHeading('Skip documentation')
            ->modalDescription('Mark this transaction as complete without client links, invoice links, or documents.')
            ->form([
                Forms\Components\Textarea::make('reason')
                    ->label('Reason (optional)')
                    ->rows(2)
                    ->maxLength(1000),
            ])
            ->action(function (array $data, Component $livewire): void {
                if (! method_exists($livewire, 'getRecord')) {
                    return;
                }

                $record = $livewire->getRecord();
                if (! $record instanceof Transaction) {
                    return;
                }

                self::skip($record, $data['reason'] ?? null);

                TransactionEditPageRefresh::refresh($livewire);
            });
    }

    public static function makeUndoSkipHeaderAction(): \Filament\Actions\Action
    {
        return \Filament\Actions\Action::make('undoSkipDocumentation')
            ->label('Undo skip')
            ->icon('heroicon-o-arrow-uturn-left')
            ->color('gray')
            ->visible(fn (Transaction $record) => app(TransactionDocumentationService::class)->isDocumentationSkipped($record))
            ->requiresConfirmation()
            ->modalHeading('Undo documentation skip')
            ->modalDescription('Restore normal documentation requirements for this transaction.')
            ->action(function (Component $livewire): void {
                if (! method_exists($livewire, 'getRecord')) {
                    return;
                }

                $record = $livewire->getRecord();
                if (! $record instanceof Transaction) {
                    return;
                }

                self::undoSkip($record);

                TransactionEditPageRefresh::refresh($livewire);
            });
    }

    protected static function canShowCompleteDocumentation(Transaction $record): bool
    {
        $service = app(TransactionDocumentationService::class);

        return ! $service->isDocumentationSkipped($record)
            && ($record->documentation_status ?? 'incomplete') !== 'complete';
    }

    protected static function makeSkipModalAction(Transaction $record): \Filament\Actions\Action
    {
        return \Filament\Actions\Action::make('skipDocumentationInModal')
            ->label('Skip documentation')
            ->color('gray')
            ->visible(fn () => app(TransactionDocumentationService::class)->canSkipDocumentation($record))
            ->requiresConfirmation()
            ->modalHeading('Skip documentation')
            ->modalDescription('Mark as complete without links or documents. Only for Income/Expense that will never be documented.')
            ->form([
                Forms\Components\Textarea::make('reason')
                    ->label('Reason (optional)')
                    ->rows(2)
                    ->maxLength(1000),
            ])
            ->action(function (array $data, Component $livewire) use ($record): void {
                self::skip($record, $data['reason'] ?? null);

                if ($livewire instanceof \Filament\Resources\Pages\EditRecord) {
                    TransactionEditPageRefresh::refresh($livewire);
                } else {
                    $livewire->dispatch('refresh-transaction-documentation-stats');
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
