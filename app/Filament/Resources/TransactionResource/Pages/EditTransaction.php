<?php

namespace App\Filament\Resources\TransactionResource\Pages;

use App\Filament\Resources\TransactionResource;
use App\Filament\Support\TransactionDocumentationForm;
use App\Services\GenerateTrxInPdfService;
use App\Services\GenerateTrxOutPdfService;
use App\Services\TransactionDocumentationService;
use App\Services\TransactionDocumentationStatsService;
use Filament\Actions;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Auth;

class EditTransaction extends EditRecord
{
    protected static string $resource = TransactionResource::class;

    /** @var array<int, int|string> */
    protected array $billsToSync = [];

    protected ?string $documentationCategory = null;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['updated_by'] = Auth::id();

        $markAsRevised = ! empty($data['mark_as_revised']);
        $wasRevised = $this->record->documentation_status === 'revised';

        if ($markAsRevised) {
            $data['documentation_status'] = 'revised';
        } elseif ($wasRevised) {
            $data['documentation_status'] = 'incomplete';
        }

        $this->billsToSync = $data['bills'] ?? [];
        $this->documentationCategory = $data['documentation_category'] ?? null;

        unset($data['bills'], $data['documentation_category'], $data['mark_as_revised']);

        return $data;
    }

    protected function afterSave(): void
    {
        $transaction = $this->record->fresh();

        if ($this->documentationCategory) {
            app(TransactionDocumentationStatsService::class)->applyCategory(
                $transaction,
                $this->documentationCategory,
                $this->billsToSync,
            );
        } elseif ($this->billsToSync !== []) {
            app(TransactionDocumentationStatsService::class)->syncBills($transaction, $this->billsToSync);
        }

        if ($transaction->fresh()->documentation_status !== 'revised') {
            app(TransactionDocumentationService::class)->syncAndRecalculate($transaction->fresh());
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            TransactionDocumentationForm::makeHeaderAction(),
            Action::make('resetDocumentationStatus')
                ->label('Reset documentation status')
                ->icon('heroicon-o-arrow-path')
                ->color('gray')
                ->visible(fn () => $this->record->documentation_status === 'revised')
                ->requiresConfirmation()
                ->modalDescription('Recalculate documentation status from the checklist. This clears the revised review mark.')
                ->action(function (): void {
                    $this->record->update(['documentation_status' => 'incomplete']);
                    app(TransactionDocumentationService::class)->syncAndRecalculate($this->record->fresh());
                    $this->refreshFormData(['documentation_status', 'mark_as_revised']);

                    Notification::make()
                        ->success()
                        ->title('Documentation status recalculated')
                        ->send();
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
            Action::make('regenerateTrxInPdf')
                ->label('Regenerate Trx In PDF')
                ->icon('heroicon-o-arrow-path')
                ->visible(fn () => $this->record->type === 'Income')
                ->action(function () {
                    app(GenerateTrxInPdfService::class)->generate($this->record);
                    $this->refreshFormData(['trx_in_pdf_path', 'documentation_status']);
                }),
            Action::make('regenerateTrxOutPdf')
                ->label('Regenerate Trx Out PDF')
                ->icon('heroicon-o-arrow-path')
                ->visible(fn () => $this->record->type === 'Outflow' && $this->record->bills()->exists())
                ->action(function () {
                    app(GenerateTrxOutPdfService::class)->generate($this->record);
                    $this->refreshFormData(['trx_out_pdf_path', 'documentation_status']);
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
