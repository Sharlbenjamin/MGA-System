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
use App\Mail\SendBalance;
use App\Models\Country;
use App\Models\File;
use App\Models\Invoice;
use App\Models\Patient;
use App\Services\UploadInvoiceToGoogleDrive;
use Filament\Tables\Actions\Action;
use Filament\Tables\Table;
use Filament\Tables\Filters\SelectFilter;
use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Actions\BulkAction;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use App\Exports\ClientBalanceExport;
use Maatwebsite\Excel\Facades\Excel;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\Summarizers\Summarizer;

class InvoiceRelationManager extends RelationManager
{
    protected static string $relationship = 'invoices';

    protected static ?string $recordTitleAttribute = 'number';

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('name')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('file.mga_reference')
                    ->label('MGA Reference')
                    ->sortable()
                    ->searchable()
                    ->copyable(),
                Tables\Columns\TextColumn::make('file.client_reference')
                    ->label('Client Reference')
                    ->sortable()
                    ->searchable()
                    ->copyable(),
                Tables\Columns\TextColumn::make('file.country.name')
                    ->label('Country')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('file.city.name')
                    ->label('City')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('file.patient.name')
                    ->label('Patient')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('file.serviceType.name')
                    ->label('Service Type')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('file.provider.name')
                    ->label('Provider')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('file.bills_sum')
                    ->label('Bills Total')
                    ->state(fn (Invoice $record) => $record->file?->bills()->sum('total_amount') ?? 0)
                    ->sortable()
                    ->money('EUR'),
                Tables\Columns\TextColumn::make('file.bills_first_status')
                    ->label('Bill Status')
                    ->state(fn (Invoice $record) => $record->file?->bills()->first()?->status ?? '-')
                    ->sortable()
                    ->badge()
                    ->color(fn ($state) => match ($state) {
                        'Draft' => 'gray',
                        'Sent' => 'info',
                        'Overdue' => 'danger',
                        'Paid' => 'success',
                        'Partial' => 'warning',
                        'Posted' => 'primary',
                        'Unpaid' => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('status')->sortable()->searchable()->badge()->color(fn ($state) => match ($state) {
                    'Draft' => 'gray',
                    'Sent' => 'info',
                    'Overdue' => 'danger',
                    'Paid' => 'success',
                    'Partial' => 'warning',
                    'Posted' => 'primary',
                    'Unpaid' => 'danger',
                }),
                Tables\Columns\TextColumn::make('due_date')->sortable()->searchable()->date(),
                Tables\Columns\TextColumn::make('total_amount')
                    ->sortable()
                    ->searchable()
                    ->money('EUR')
                    ->summarize(Sum::make()->label('Total Amount')->money('EUR')),
                Tables\Columns\TextColumn::make('paid_amount')
                    ->sortable()
                    ->searchable()
                    ->money('EUR')
                    ->summarize(Sum::make()->label('Total Paid')->money('EUR')),
                Tables\Columns\TextColumn::make('Remaining_Amount')
                    ->state(fn (Invoice $record) => $record->total_amount - $record->paid_amount)
                    ->sortable()
                    ->searchable()
                    ->money('EUR')
                    ->summarize(
                        Summarizer::make()
                            ->label('Total Remaining')
                            ->using(fn ($query) => $query->sum('total_amount') - $query->sum('paid_amount'))
                            ->money('EUR')
                    ),
                Tables\Columns\TextColumn::make('invoice_google_link')
                    ->label('PDF')
                    ->weight('underline')->color('info')
                    ->state(fn (Invoice $record) => $record->invoice_google_link ? 'View Invoice' : '')
                    ->url(fn (Invoice $record) => $record->invoice_google_link)
                    ->openUrlInNewTab(false),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->multiple()
                    ->options([
                        'Draft' => 'Draft',
                        'Posted' => 'Posted',
                        'Sent' => 'Sent',
                        'Unpaid' => 'Unpaid',
                        'Overdue' => 'Overdue',
                        'Paid' => 'Paid',
                        'Partial' => 'Partial',
                    ]),
                    // due date filter when true fetch invoices with due date before today
            ])->actions([
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
                BulkAction::make('updateStatus')
                    ->label('Update Status')
                    ->color('primary')
                    ->icon('heroicon-o-pencil-square')
                    ->modalHeading('Update Invoice Status')
                    ->modalSubmitActionLabel('Update Status')
                    ->form([
                        Forms\Components\Select::make('status')
                            ->label('New Status')
                            ->options([
                                'Draft' => 'Draft',
                                'Posted' => 'Posted',
                                'Sent' => 'Sent',
                                'Unpaid' => 'Unpaid',
                                'Overdue' => 'Overdue',
                                'Paid' => 'Paid',
                                'Partial' => 'Partial',
                            ])
                            ->required()
                            ->default('Draft'),
                    ])
                    ->action(function (array $data) {
                        $invoices = $this->getSelectedTableRecords();
                        
                        foreach ($invoices as $invoice) {
                            $invoice->update(['status' => $data['status']]);
                        }
                        
                        Notification::make()
                            ->success()
                            ->title('Status Updated')
                            ->body(count($invoices) . ' invoice(s) status has been updated to ' . $data['status'])
                            ->send();
                    }),
                BulkAction::make('SendBalanceUpdate')
                    ->label('Send Balance Update')
                    ->color('success')
                    ->icon('heroicon-o-paper-airplane')
                    ->modalHeading('Send Balance Update')
                    ->modalSubmitActionLabel('Send')
                    ->form([
                        TextInput::make('msg')
                            ->label('Message')
                            ->placeholder('Enter message')
                            ->required(),
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
                    ->action(function ($data) {
                        // selected invoices
                        $invoices = $this->getSelectedTableRecords();
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
                            if($invoices->first()->patient->client->financialContact->preferred_contact == 'Email'){
                                Mail::mailer($mailer)->to($invoices->first()->patient->client->financialContact->email)->send(new SendBalance('Balance', $invoices, $data['msg']));
                            }elseIf ($invoices->first()->patient->client->financialContact->preferred_contact == 'Second Email'){
                                Mail::mailer($mailer)->to($invoices->first()->patient->client->financialContact->second_email)->send(new SendBalance('Balance', $invoices, $data['msg']));
                            }else{
                                Notification::make()->title("No Financial Contact Found")->body("No Financial Contact Found")->danger()->send();
                                return;
                            }
                        }else{
                            Mail::mailer($mailer)->to($data['email_to'])->send(new SendBalance('Balance', $invoices, $data['msg']));
                        }
                         Notification::make()->success()->title('Invoice generated and sent successfully')->send();
                    })
            ])->headerActions([
                Action::make('ExportBalance')->label('Export Balance PDF')
                    ->color('info')
                    ->icon('heroicon-o-document-text')
                    ->action(function () {
                        // Export balance using the client directly
                        $pdf = Pdf::loadView('pdf.client_balance', ['client' => $this->ownerRecord]);
                        $content = $pdf->output();
                        $fileName = $this->ownerRecord->name . ' Balance.pdf';
                        return response()->streamDownload(
                            fn () => print($pdf->output()),
                            $fileName
                        );
                    }),
                Action::make('ExportBalanceExcel')->label('Export Balance Excel')
                    ->color('success')
                    ->icon('heroicon-o-table-cells')
                    ->action(function () {
                        // Export balance to Excel using the client directly
                        $fileName = $this->ownerRecord->name . ' Balance.xlsx';
                        return Excel::download(new ClientBalanceExport($this->ownerRecord), $fileName);
                    })
            ]);
    }
}
