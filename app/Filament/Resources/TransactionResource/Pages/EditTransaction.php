<?php

namespace App\Filament\Resources\TransactionResource\Pages;

use App\Filament\Resources\BankAccountResource;
use App\Filament\Resources\TransactionResource;
use App\Filament\Support\TransactionDocumentationForm;
use App\Filament\Support\TransactionEditPageRefresh;
use App\Services\GenerateTrxInPdfService;
use App\Services\GenerateTrxOutPdfService;
use App\Services\TransactionDocumentationService;
use App\Services\TransactionDocumentationStatsService;
use Filament\Actions;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class EditTransaction extends EditRecord
{
    protected static string $resource = TransactionResource::class;

    /** @var array<int, int> */
    protected array $billsToSync = [];

    /** @var array<int, int> */
    protected array $previousBillsToSync = [];

    protected ?string $documentationCategory = null;

    protected ?string $previousDocumentationCategory = null;

    protected ?string $relatedTypeForSync = null;

    public function getBreadcrumbs(): array
    {
        return [
            BankAccountResource::getUrl('index') => BankAccountResource::getBreadcrumb(),
            TransactionResource::indexUrlFor($this->record->bank_account_id) => 'Bank Transactions',
            '#' => $this->getTitle(),
        ];
    }

    protected function getRedirectUrl(): ?string
    {
        return null;
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['bills'] = $this->record->bills()->pluck('bills.id')->all();

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['updated_by'] = Auth::id();

        $this->previousDocumentationCategory = $this->record->documentation_category
            ?? TransactionDocumentationStatsService::resolveCategoryKey($this->record);
        $this->previousBillsToSync = TransactionDocumentationStatsService::normalizeLinkIds(
            $this->record->bills()->pluck('bills.id')->all(),
        );

        $this->relatedTypeForSync = $data['related_type'] ?? $this->record->related_type;
        $this->billsToSync = TransactionDocumentationStatsService::normalizeLinkIds($data['bills'] ?? []);
        $this->documentationCategory = $data['documentation_category'] ?? null;

        unset($data['bills']);

        if (blank($data['documentation_category'] ?? null)) {
            $data['documentation_category'] = TransactionDocumentationStatsService::defaultCategoryFor(
                $data['type'] ?? $this->record->type,
                $data['related_type'] ?? $this->record->related_type,
            ) ?? TransactionDocumentationStatsService::resolveCategoryKey($this->record);
        }

        return $data;
    }

    protected function afterSave(): void
    {
        $transaction = $this->record->fresh();
        $statsService = app(TransactionDocumentationStatsService::class);

        $categoryChanged = $this->documentationCategory !== $this->previousDocumentationCategory;
        $billsChanged = $this->billsToSync !== $this->previousBillsToSync;

        if ($categoryChanged && filled($this->documentationCategory)) {
            try {
                $statsService->applyCategory(
                    $transaction,
                    $this->documentationCategory,
                    $this->billsToSync,
                );
                $transaction = $transaction->fresh();
            } catch (ValidationException $exception) {
                Notification::make()
                    ->danger()
                    ->title('Category could not be applied')
                    ->body(collect($exception->errors())->flatten()->first() ?? 'Validation failed.')
                    ->persistent()
                    ->send();

                $this->refreshFormAfterSideEffects();

                return;
            }
        } elseif ($billsChanged && in_array($this->relatedTypeForSync, ['Provider', 'Branch'], true)) {
            $statsService->syncBills($transaction, $this->billsToSync);
        }

        $this->refreshFormAfterSideEffects();
    }

    public function refreshRecordOnPage(): void
    {
        $this->record = $this->record->fresh(['bills', 'invoices', 'attachments']);

        $this->refreshFormData(TransactionEditPageRefresh::FORM_FIELDS);

        $this->data['bills'] = $this->record->bills->pluck('id')->all();
    }

    protected function refreshFormAfterSideEffects(): void
    {
        $this->refreshRecordOnPage();
    }

    protected function getHeaderActions(): array
    {
        $docService = app(TransactionDocumentationService::class);

        return [
            TransactionDocumentationForm::makeHeaderAction(),
            Action::make('trxInPdfBlocked')
                ->label(fn (): string => 'Trx In PDF: '.($docService->getTrxInSkipReason($this->record) ?? 'Not ready'))
                ->icon('heroicon-o-exclamation-triangle')
                ->color('warning')
                ->visible(fn (): bool => (bool) $docService->getTrxInBlockedMessage($this->record))
                ->modalHeading('Trx In PDF not ready')
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Close')
                ->modalContent(fn (): \Illuminate\Contracts\View\View => view('filament.modals.plain-text', [
                    'text' => $docService->getTrxInBlockedMessage($this->record),
                ])),
            Action::make('generateTrxInPdf')
                ->label(fn (): string => $this->record->trx_in_pdf_path ? 'Regenerate Trx In PDF' : 'Generate Trx In PDF')
                ->icon('heroicon-o-arrow-path')
                ->visible(fn (): bool => $docService->canGenerateTrxIn($this->record))
                ->action(function () use ($docService): void {
                    if (! $docService->canGenerateTrxIn($this->record)) {
                        Notification::make()->warning()->title('Cannot generate Trx In PDF')->body($docService->getTrxInSkipReason($this->record))->send();

                        return;
                    }

                    app(GenerateTrxInPdfService::class)->generate($this->record);
                    $this->record = $this->record->fresh();
                    $this->refreshRecordOnPage();
                }),
            Action::make('trxOutPdfBlocked')
                ->label(fn (): string => 'Trx Out PDF: '.($docService->getTrxOutSkipReason($this->record) ?? 'Not ready'))
                ->icon('heroicon-o-exclamation-triangle')
                ->color('warning')
                ->visible(fn (): bool => (bool) $docService->getTrxOutBlockedMessage($this->record))
                ->modalHeading('Trx Out PDF not ready')
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Close')
                ->modalContent(fn (): \Illuminate\Contracts\View\View => view('filament.modals.plain-text', [
                    'text' => $docService->getTrxOutBlockedMessage($this->record),
                ])),
            Action::make('generateTrxOutPdf')
                ->label(fn (): string => $this->record->trx_out_pdf_path ? 'Regenerate Trx Out PDF' : 'Generate Trx Out PDF')
                ->icon('heroicon-o-arrow-path')
                ->visible(fn (): bool => $docService->canGenerateTrxOut($this->record))
                ->action(function () use ($docService): void {
                    if (! $docService->canGenerateTrxOut($this->record)) {
                        Notification::make()->warning()->title('Cannot generate Trx Out PDF')->body($docService->getTrxOutSkipReason($this->record))->send();

                        return;
                    }

                    app(GenerateTrxOutPdfService::class)->generate($this->record);
                    $this->record = $this->record->fresh();
                    $this->refreshRecordOnPage();
                }),
            Action::make('viewTrxInPdf')
                ->label('View Trx In PDF')
                ->icon('heroicon-o-document-text')
                ->color('info')
                ->url(fn () => $this->record->getTrxInPdfUrl())
                ->openUrlInNewTab()
                ->visible(fn () => (bool) $this->record->getTrxInPdfUrl()),
            Action::make('viewTrxOutPdf')
                ->label('View Trx Out PDF')
                ->icon('heroicon-o-document-text')
                ->color('info')
                ->url(fn () => $this->record->getTrxOutPdfUrl())
                ->openUrlInNewTab()
                ->visible(fn () => (bool) $this->record->getTrxOutPdfUrl()),
            Action::make('finalizeTransaction')
                ->label('Confirm payment (finalize)')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading('Confirm payment')
                ->modalDescription('Mark attached bills as paid and complete this transaction. Use after the bank statement confirms the payment.')
                ->modalSubmitActionLabel('Confirm payment')
                ->visible(fn () => $this->record->status === 'Draft')
                ->action(function (): void {
                    try {
                        $this->record->finalizeTransaction();
                        $this->record = $this->record->fresh();
                        $this->refreshRecordOnPage();

                        Notification::make()
                            ->success()
                            ->title('Payment confirmed')
                            ->body('Transaction finalized and bills marked as paid.')
                            ->send();
                    } catch (\Exception $e) {
                        Notification::make()
                            ->danger()
                            ->title('Finalization failed')
                            ->body($e->getMessage())
                            ->send();
                    }
                }),
            Action::make('viewDocument')
                ->label('View Document')
                ->icon('heroicon-o-eye')
                ->color('info')
                ->url(fn () => $this->record->getAttachmentUrl())
                ->openUrlInNewTab()
                ->visible(fn () => (bool) $this->record->getAttachmentUrl()),
            Actions\Action::make('view_bill')
                ->label('View Bill')
                ->icon('heroicon-o-document-text')
                ->color('primary')
                ->visible(fn () => $this->record->bills()->exists())
                ->action(function () {
                    $bill = $this->record->bills()->first();
                    if ($bill) {
                        return redirect()->route('filament.admin.resources.bills.edit', $bill);
                    }
                }),
            Actions\Action::make('view_file')
                ->label('View File')
                ->icon('heroicon-o-folder')
                ->color('success')
                ->visible(fn () => $this->record->bills()->exists() && $this->record->bills()->first()->file)
                ->action(function () {
                    $bill = $this->record->bills()->first();
                    if ($bill && $bill->file) {
                        return redirect()->route('filament.admin.resources.files.edit', $bill->file);
                    }
                }),
            Actions\DeleteAction::make(),
        ];
    }
}
