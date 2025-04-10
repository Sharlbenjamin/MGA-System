<?php

namespace App\Filament\Resources\PatientResource\RelationManagers;

use App\Filament\Resources\FileResource;
use App\Filament\Resources\FileResource\Pages;
use App\Filament\Resources\InvoiceResource;
use App\Models\Country;
use App\Models\File;
use App\Models\Invoice;
use App\Models\Patient;
use App\Services\UploadInvoiceToGoogleDrive;
use Filament\Tables\Actions\Action;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Actions\CreateAction;
use Filament\Tables\Table;
use Filament\Tables\Filters\SelectFilter;
use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Mail;
use App\Mail\SendInvoice;

class InvoiceRelationManager extends RelationManager
{
    protected static string $relationship = 'invoices';

    protected static ?string $model = Invoice::class;


    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('status')->sortable()->searchable()->badge()->color(fn ($state) => match ($state) {
                    'Draft' => 'warning',
                    'Sent' => 'info',
                    'Overdue' => 'danger',
                    'Paid' => 'success',
                    'Posted' => 'primary',
                }),
                Tables\Columns\TextColumn::make('due_date')->sortable()->searchable()->date(),
                Tables\Columns\TextColumn::make('final_total')->sortable()->searchable()->money('EUR'),
                Tables\Columns\TextColumn::make('paid_amount')->sortable()->searchable()->money('EUR'),
                Tables\Columns\TextColumn::make('remaining_amount')->sortable()->searchable()->money('EUR'),
                Tables\Columns\TextColumn::make('invoice_google_link')->sortable()->searchable()->url(fn (Invoice $record) => $record->invoice_google_link)
                    ->openUrlInNewTab(false),

            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'Draft' => 'Draft',
                        'Sent' => 'Sent',
                        'Overdue' => 'Overdue',
                        'Paid' => 'Paid',
                        'Posted' => 'Posted',
                    ]),
                    // due date filter when true fetch invoices with due date before today
            ])->actions([
                Action::make('edit')->color('gray')->icon('heroicon-o-pencil')
                    ->url(fn ($record) => InvoiceResource::getUrl('edit', [
                        'record' => $record->id
                    ])),
                Action::make('send')
                    ->color('success')
                    ->icon('heroicon-o-paper-airplane')
                    ->action(function (Invoice $record) {
                        try {
                            // First generate PDF
                            $pdf = Pdf::loadView('pdf.invoice', ['invoice' => $record]);
                            $content = $pdf->output();
                            $fileName = $record->name . '.pdf';

                            // Upload to Google Drive
                            $uploader = app(UploadInvoiceToGoogleDrive::class);
                            $result = $uploader->uploadInvoiceToGoogleDrive(
                                $content,
                                $fileName,
                                $record
                            );

                            if ($result === false) {
                                Notification::make()
                                    ->danger()
                                    ->title('Upload failed')
                                    ->body('Check logs for more details')
                                    ->send();
                                return;
                            }

                            // Update invoice with new Google Drive link
                            $record->invoice_google_link = $result['webViewLink'];
                            $record->status = 'Sent';
                            $record->save();

                            // Send email
                            if($record->patient->client->financialContact()->preferred_contact == 'Email'){
                                Mail::to($record->patient->client->financialContact()->email)->send(new SendInvoice($record));
                            }elseIf ($record->patient->client->financialContact()->preferred_contact == 'Second Email'){
                                Mail::to($record->patient->client->financialContact()->second_email)->send(new SendInvoice($record));
                            }else{
                                Notification::make()->title("No Financial Contact Found")->body("No Financial Contact Found")->danger()->send();
                                return;
                            }

                            Notification::make()
                                ->success()
                                ->title('Invoice generated and sent successfully')
                                ->send();

                        } catch (\Exception $e) {
                            Notification::make()
                                ->danger()
                                ->title('Error occurred')
                                ->body($e->getMessage())
                                ->send();
                        }
                    }),
                // generate pdf onliy appear if the status is Posted
                Action::make('generate')
                    ->color('info')
                    ->icon('heroicon-o-document-text')
                    ->hidden(fn (Invoice $record) => $record->status !== 'Posted')
                    ->action(function (Invoice $record) {
                        try {
                            // First generate PDF
                            $pdf = Pdf::loadView('pdf.invoice', ['invoice' => $record]);
                            $content = $pdf->output();
                            $fileName = $record->name . '.pdf';

                            $uploader = app(UploadInvoiceToGoogleDrive::class);
                            $result = $uploader->uploadInvoiceToGoogleDrive(
                                $content,
                                $fileName,
                                $record
                            );

                            if ($result === false) {
                                Notification::make()
                                    ->danger()
                                    ->title('Upload failed')
                                    ->body('Check logs for more details')
                                    ->send();
                                return;
                            }

                            // Update invoice with Google Drive link
                            $record->invoice_google_link = $result['webViewLink'];
                            $record->save();

                            Notification::make()
                                ->success()
                                ->title('Invoice uploaded successfully')
                                ->send();

                            // Return PDF download response
                            return response()->streamDownload(
                                fn () => print($pdf->output()),
                                $fileName
                            );

                        } catch (\Exception $e) {
                            Notification::make()
                                ->danger()
                                ->title('Error occurred')
                                ->body($e->getMessage())
                                ->send();
                            return;
                        }
                    })
                    ->requiresConfirmation()
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ])->headerActions([
                Action::make('createInvoice')
                    ->openUrlInNewTab(false)
                    ->url(fn () => InvoiceResource::getUrl('create', [
                        'patient_id' => $this->ownerRecord->id
                    ])),
            ]);
    }

}
