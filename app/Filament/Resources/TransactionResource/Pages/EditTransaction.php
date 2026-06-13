<?php

namespace App\Filament\Resources\TransactionResource\Pages;

use App\Filament\Resources\TransactionResource;
use App\Filament\Support\TransactionDocumentationForm;
use App\Services\GenerateTrxInPdfService;
use App\Services\GenerateTrxOutPdfService;
use Filament\Actions;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Auth;

class EditTransaction extends EditRecord
{
    protected static string $resource = TransactionResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['updated_by'] = Auth::id();

        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [
            TransactionDocumentationForm::makeHeaderAction(),
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
            Action::make('uploadDocument')
                ->label('Upload Document')
                ->icon('heroicon-o-document-arrow-up')
                ->color('success')
                ->modalHeading('Upload Transaction Document')
                ->modalDescription('Upload the transaction document (PDF or image).')
                ->modalSubmitActionLabel('Upload')
                ->form(TransactionResource::documentUploadFormSchema())
                ->action(function (array $data = []): void {
                    if (empty($data['transaction_document'])) {
                        \Filament\Notifications\Notification::make()
                            ->danger()
                            ->title('No document uploaded')
                            ->body('Please upload a document first.')
                            ->send();

                        return;
                    }

                    TransactionResource::saveUploadedDocument($this->record, $data['transaction_document']);
                    $this->refreshFormData(['attachment_path', 'documentation_status']);
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
