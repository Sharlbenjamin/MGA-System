<?php

namespace App\Filament\Resources\FileResource\RelationManagers;

use App\Filament\Resources\InvoiceResource;
use App\Models\Invoice;
use App\Services\UploadInvoiceToGoogleDrive;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Actions\Action;
use Filament\Notifications\Notification;
use Barryvdh\DomPDF\Facade\Pdf;

class InvoiceRelationManager extends RelationManager
{
    protected static string $relationship = 'invoices';

    protected static ?string $model = Invoice::class;

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('name')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('status')->sortable()->searchable()->badge()->color(fn ($state) => match ($state) {
                    'Draft' => 'gray',
                    'Sent' => 'info',
                    'Overdue' => 'danger',
                    'Paid' => 'success',
                    'Posted' => 'primary',
                    'Unpaid' => 'danger',
                }),
                Tables\Columns\TextColumn::make('due_date')->sortable()->searchable()->date(),
                Tables\Columns\TextColumn::make('total_amount')->sortable()->searchable()->money('EUR'),
                Tables\Columns\TextColumn::make('paid_amount')->sortable()->searchable()->money('EUR'),
                Tables\Columns\TextColumn::make('remaining_amount')->state(fn (Invoice $record) => $record->total_amount - $record->paid_amount)->sortable()->searchable()->money('EUR'),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'Draft' => 'Draft',
                        'Posted' => 'Posted',
                        'Sent' => 'Sent',
                        'Unpaid' => 'Unpaid',
                        'Overdue' => 'Overdue',
                        'Paid' => 'Paid',
                    ]),
            ])
            ->actions([
                Action::make('viewDocument')
                    ->label('View')
                    ->icon('heroicon-o-eye')
                    ->color('info')
                    ->url(fn ($record) => $record->getDocumentSignedUrl())
                    ->openUrlInNewTab()
                    ->visible(fn ($record) => $record->hasLocalDocument()),
                Action::make('downloadDocument')
                    ->label('Download')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('success')
                    ->url(fn ($record) => $record->getDocumentSignedUrl())
                    ->openUrlInNewTab()
                    ->visible(fn ($record) => $record->hasLocalDocument()),
                Action::make('edit')->color('gray')->icon('heroicon-o-pencil')
                    ->url(fn ($record) => InvoiceResource::getUrl('edit', [
                        'record' => $record->id
                    ])),
                Action::make('generate')
                    ->modalHeading('Generate Invoice')
                    ->modalSubmitActionLabel('Generate')
                    ->color('success')
                    ->icon('heroicon-o-document-arrow-up')
                    ->requiresConfirmation()
                    ->modalDescription('This will generate and upload the invoice to Google Drive.')
                    ->visible(fn (Invoice $record): bool => $record->status === 'Draft')
                    ->action(function (Invoice $record) {
                        // First generate PDF
                        $pdf = Pdf::loadView('pdf.invoice', ['invoice' => $record]);
                        $content = $pdf->output();
                        $fileName = $record->name . '.pdf';

                        // Save to local storage using DocumentPathResolver
                        $resolver = app(\App\Services\DocumentPathResolver::class);
                        $localPath = $resolver->ensurePathFor($record->file, 'invoices', $fileName);
                        \Illuminate\Support\Facades\Storage::disk('public')->put($localPath, $content);
                        
                        // Update invoice with local document path
                        $record->invoice_document_path = $localPath;

                        // Upload to Google Drive (keep as secondary)
                        $uploader = app(UploadInvoiceToGoogleDrive::class);
                        $result = $uploader->uploadInvoiceToGoogleDrive(
                            $content,
                            $fileName,
                            $record
                        );

                        if ($result !== false) {
                            // Update invoice with Google Drive link if upload successful
                            $record->invoice_google_link = $result['webViewLink'];
                        }

                        $record->status = 'Posted';
                        $record->save();

                        Notification::make()
                            ->success()
                            ->title('Invoice generated and uploaded successfully')
                            ->body('Invoice has been uploaded to Google Drive.')
                            ->send();
                    }),
                Action::make('markAsSent')
                    ->label('Mark as Sent')
                    ->color('primary')
                    ->icon('heroicon-o-paper-airplane')
                    ->requiresConfirmation()
                    ->modalHeading('Mark Invoice as Sent')
                    ->modalDescription('Are you sure you want to mark this invoice as Sent?')
                    ->modalSubmitActionLabel('Mark as Sent')
                    ->visible(fn (Invoice $record): bool => $record->status === 'Posted')
                    ->action(function (Invoice $record) {
                        $record->status = 'Sent';
                        $record->save();

                        Notification::make()
                            ->success()
                            ->title('Invoice marked as Sent')
                            ->body('Invoice status has been updated to Sent.')
                            ->send();
                    }),
                Tables\Actions\Action::make('view')
                    ->icon('heroicon-o-eye')
                    ->url(fn (Invoice $record) => route('invoice.view', $record))
                    ->openUrlInNewTab(),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ])
            ->headerActions([
                Action::make('createInvoice')
                    ->openUrlInNewTab(false)
                    ->url(fn () => InvoiceResource::getUrl('create', [
                        'file_id' => $this->ownerRecord->id,
                        'patient_id' => $this->ownerRecord->patient_id
                    ])),
            ]);
    }
} 