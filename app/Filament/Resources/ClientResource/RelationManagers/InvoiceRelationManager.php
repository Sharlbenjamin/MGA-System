<?php

namespace App\Filament\Resources\ClientResource\RelationManagers;

use App\Filament\Resources\InvoiceResource;
use App\Mail\SendInvoice;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Actions\ViewAction;
use Illuminate\Support\Facades\Mail;
use App\Filament\Resources\FileResource;
use App\Filament\Resources\FileResource\Pages;
use App\Models\Country;
use App\Models\File;
use App\Models\Invoice;
use App\Models\Patient;
use App\Services\UploadInvoiceToGoogleDrive;
use Filament\Tables\Actions\Action;
use Filament\Tables\Table;
use Filament\Tables\Filters\SelectFilter;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;

class InvoiceRelationManager extends RelationManager
{
    protected static string $relationship = 'invoices';

    protected static ?string $recordTitleAttribute = 'number';

    public function table(Table $table): Table
    {
        return $table
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
                Tables\Columns\TextColumn::make('Remaining_Amount')->state(fn (Invoice $record) => $record->total_amount - $record->paid_amount)->sortable()->searchable()->money('EUR'),
                Tables\Columns\TextColumn::make('invoice_google_link')
                    ->label('PDF')
                    ->weight('underline')->color('info')
                    ->state(fn (Invoice $record) => $record->invoice_google_link ? 'View Invoice' : '')
                    ->url(fn (Invoice $record) => $record->invoice_google_link)
                    ->openUrlInNewTab(false),
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

                            // Get the authenticated user
                            $user = Auth::user();
                            if (!$user) {
                                Log::error("No authenticated user found!");
                                return;
                            }

                            // Fetch updated user info from the database
                            $user = \App\Models\User::find($user->id);

                            // Get SMTP credentials (use system default if user's credentials are missing)
                            $smtpUsername = $user->smtp_username ?? Config::get('mail.mailers.smtp.username');
                            $smtpPassword = $user->smtp_password ?? Config::get('mail.mailers.smtp.password');

                            // Ensure SMTP credentials are set correctly
                            if (!$smtpUsername || !$smtpPassword) {
                                Log::error("SMTP credentials missing for user: {$user->id}");
                                return;
                            }

                            // Dynamically set the mail configuration
                            Config::set('mail.mailers.smtp.username', $smtpUsername);
                            Config::set('mail.mailers.smtp.password', $smtpPassword);

                            // Send email
                            if($record->patient->client->financialContact->preferred_contact == 'Email'){
                                Mail::to($record->patient->client->financialContact->email)->send(new SendInvoice($record, $user));
                            }elseIf ($record->patient->client->financialContact->preferred_contact == 'Second Email'){
                                Mail::to($record->patient->client->financialContact->second_email)->send(new SendInvoice($record, $user));
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
                Tables\Actions\Action::make('view')
                ->icon('heroicon-o-eye')
                ->url(fn (Invoice $record) => route('invoice.view', $record))
                ->openUrlInNewTab(),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ])->headerActions([
                Action::make('createInvoice')
                    ->openUrlInNewTab(false)
                    ->url(fn () => InvoiceResource::getUrl('create', [
                        'patient_id' => $this->ownerRecord->id
                    ])),
                Action::make('ExportBalance')
                    ->color('success')
                    ->icon('heroicon-o-document-text')
                    ->action(function () {
                        // Export balance using the client directly
                        $pdf = Pdf::loadView('pdf.client_balance', ['client' => $this->ownerRecord]);
                        $content = $pdf->output();
                        $fileName = $this->ownerRecord->name . '_balance.pdf';
                        return response()->streamDownload(
                            fn () => print($pdf->output()),
                            $fileName
                        );
                    })
            ]);
    }
}
