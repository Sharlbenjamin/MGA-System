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
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;

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
                    'Unpaid' => 'danger',
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
                        'Unpaid' => 'Unpaid',
                    ]),
                    // due date filter when true fetch invoices with due date before today
            ])->actions([
                Action::make('edit')->color('gray')->icon('heroicon-o-pencil')
                    ->url(fn ($record) => InvoiceResource::getUrl('edit', [
                        'record' => $record->id
                    ])),
                    Action::make('send')
                    ->form([
                        Forms\Components\Select::make('email_type')->options([
                            'Financial Email' => 'Financial Email',
                            'Custom' => 'Custom',
                        ])->default('Financial Email')->live()
                        ->required(),
                        Forms\Components\TextInput::make('email_to')
                            ->label('Send To')->email()
                            ->visible(fn ($get) => $get('email_type') == 'Custom')
                            ->required()
                    ])
                    ->modalHeading('Send Invoice')
                    ->modalSubmitActionLabel('Send')
                    ->color('success')
                    ->icon('heroicon-o-paper-airplane')
                    ->action(function (Invoice $record, array $data) {
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

                            // Fetch updated user info from the database
                            $user = \App\Models\User::find(Auth::user()->id);

                            // Set mailer based on user's role and SMTP credentials
                            $mailer = 'financial';
                            $financialRoles = ['Financial Manager', 'Financial Supervisor', 'Financial Department'];

                            if($user->hasRole($financialRoles) && $user->smtp_username && $user->smtp_password) {
                                Config::set('mail.mailers.financial.username', $user->smtp_username);
                                Config::set('mail.mailers.financial.password', $user->smtp_password);
                            }

                            // Send email
                        if($data['email_type'] == 'Financial Email'){
                            if($record->patient->client->financialContact->preferred_contact == 'Email'){
                                Mail::mailer($mailer)->to($record->patient->client->financialContact->email)->send(new SendInvoice($record, $user));
                            }elseIf ($record->patient->client->financialContact->preferred_contact == 'Second Email'){
                                Mail::mailer($mailer)->to($record->patient->client->financialContact->second_email)->send(new SendInvoice($record, $user));
                            }else{
                                Notification::make()->title("No Financial Contact Found")->body("No Financial Contact Found")->danger()->send();
                                return;
                            }
                        }else{
                            Mail::mailer($mailer)->to($data['email_to'])->send(new SendInvoice($record, $user));
                        }
                            Notification::make()->success()->title('Invoice generated and sent successfully')->send();

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
            ]);
    }

}
