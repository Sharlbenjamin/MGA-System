<?php

namespace App\Filament\Resources\InvoiceResource\Pages;

use App\Filament\Resources\InvoiceResource;
use App\Filament\Resources\TransactionResource;
use Filament\Resources\Pages\EditRecord;
use App\Filament\Resources\FileResource;
use App\Models\Invoice;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Auth;
use App\Mail\SendInvoiceToClient;
use Illuminate\Support\Facades\Storage;

class EditInvoice extends EditRecord
{
    protected static string $resource = InvoiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('file')
                ->label('View File')
                ->url(FileResource::getUrl('view', ['record' => $this->record->file_id]))
                ->icon('heroicon-o-document-text')->color('primary'),
            Actions\Action::make('view')
                ->url(fn (Invoice $record) => route('invoice.view', $record))
                ->icon('heroicon-o-eye')->color('primary'),

            Actions\Action::make('send_invoice')
                ->label('Send Invoice to Client')
                ->icon('heroicon-o-paper-airplane')
                ->color('warning')
                ->modalHeading('Sending invoice to client')
                ->modalDescription('Choose what to attachment in the email')
                ->modalSubmitActionLabel('Send Invoice')
                ->modalSubmitAction(function ($action) {
                    return $action->color('primary');
                })
                ->form([
                    Forms\Components\Checkbox::make('attach_invoice')
                        ->label('The generated draft invoice')
                        ->default(true)
                        ->visible(fn () => $this->record->hasLocalDocument()),
                    
                    Forms\Components\Checkbox::make('attach_bill')
                        ->label('PDF of the Bill')
                        ->default(false)
                        ->visible(fn () => $this->record->file->bills()->whereNotNull('bill_document_path')->exists()),
                    
                    Forms\Components\Checkbox::make('attach_medical_report')
                        ->label('Medical report uploaded PDF')
                        ->default(false)
                        ->visible(fn () => $this->record->file->medicalReports()->whereNotNull('document_path')->exists()),
                    
                    Forms\Components\Checkbox::make('attach_gop')
                        ->label('GOP in PDF')
                        ->default(false)
                        ->visible(fn () => $this->record->file->gops()->where('type', 'In')->whereNotNull('document_path')->exists()),
                    
                    Forms\Components\View::make('email_preview')
                        ->view('filament.forms.components.invoice-email-preview')
                        ->viewData([
                            'invoice' => $this->record,
                        ]),
                ])
                ->action(function (array $data) {
                    $invoice = $this->record;
                    
                    // Build attachments array
                    $attachments = [];
                    if (!empty($data['attach_invoice']) && $invoice->hasLocalDocument()) {
                        $attachments[] = 'invoice';
                    }
                    if (!empty($data['attach_bill'])) {
                        $attachments[] = 'bill';
                    }
                    if (!empty($data['attach_medical_report'])) {
                        $attachments[] = 'medical_report';
                    }
                    if (!empty($data['attach_gop'])) {
                        $attachments[] = 'gop';
                    }
                    
                    // Build email body
                    $gopTotal = $invoice->file->gops()->where('type', 'In')->sum('amount');
                    $attachmentList = [];
                    
                    if (in_array('invoice', $attachments)) {
                        $attachmentList[] = '· Invoice ' . $invoice->name;
                    }
                    if (in_array('medical_report', $attachments)) {
                        $attachmentList[] = '· Medical Report for ' . $invoice->file->patient->name . ' | ' . $invoice->file->mga_reference;
                    }
                    if (in_array('gop', $attachments)) {
                        $attachmentList[] = '· GOP for ' . $invoice->file->patient->name . ' | ' . $invoice->file->mga_reference;
                    }
                    if (in_array('bill', $attachments)) {
                        $attachmentList[] = '· Bill for ' . $invoice->file->patient->name . ' | ' . $invoice->file->mga_reference;
                    }
                    
                    $emailBody = "Dear team,\n\n";
                    $emailBody .= "Find Attached the Invoice {$invoice->name}:\n\n";
                    $emailBody .= "Your Reference: {$invoice->file->client_reference}\n";
                    $emailBody .= "Patient Name: {$invoice->file->patient->name}\n\n";
                    $emailBody .= "MGA Reference: {$invoice->file->mga_reference}\n\n";
                    $emailBody .= "Issue Date: " . $invoice->invoice_date->format('d/m/Y') . "\n";
                    $emailBody .= "Due Date: " . $invoice->due_date->format('d/m/Y') . "\n";
                    $emailBody .= "Total: " . number_format($invoice->total_amount, 2) . "€\n\n";
                    $emailBody .= "GOP Total: " . number_format($gopTotal, 2) . "€\n\n";
                    
                    if (!empty($attachmentList)) {
                        $emailBody .= "Attachments\n\n";
                        $emailBody .= implode("\n", $attachmentList);
                    }
                    
                    // Set mailer based on user's role and SMTP credentials
                    $mailer = 'financial';
                    $user = \App\Models\User::find(Auth::id());
                    $financialRoles = ['Financial Manager', 'Financial Supervisor', 'Financial Department'];
                    
                    if ($user->hasRole($financialRoles) && $user->smtp_username && $user->smtp_password) {
                        Config::set('mail.mailers.financial.username', $user->smtp_username);
                        Config::set('mail.mailers.financial.password', $user->smtp_password);
                    }
                    
                    // Get recipient email from client
                    $client = $invoice->file->patient->client;
                    $recipientEmail = $client->email;
                    
                    if (!$recipientEmail) {
                        Notification::make()
                            ->title('No Email Found')
                            ->body('The client does not have an email address configured.')
                            ->danger()
                            ->send();
                        return;
                    }
                    
                    // Send email
                    try {
                        Mail::mailer($mailer)->to($recipientEmail)->send(
                            new SendInvoiceToClient($invoice, $attachments, $emailBody)
                        );
                        
                        Notification::make()
                            ->title('Invoice Sent')
                            ->body('Invoice has been sent to the client successfully.')
                            ->success()
                            ->send();
                    } catch (\Exception $e) {
                        Notification::make()
                            ->title('Failed to Send Invoice')
                            ->body('Error: ' . $e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),

            Actions\Action::make('transaction')
                ->label('Invoice Paid')
                ->color('success')
                ->hidden(fn ($record) => $record->status === 'Paid')
                ->url(function () {
                    $invoice = $this->record;
                    $params = [
                        'type' => 'Income',
                        'amount' => $invoice->total_amount,
                        'name' => 'Payment for ' . $invoice->name. ' on ' . now()->format('d/m/Y'),
                        'date' => now()->format('Y-m-d'),
                        'invoice_id' => $invoice->id,
                    ];
                    
                    // Determine related type and ID based on invoice relationships
                    if ($invoice->patient && $invoice->patient->client) {
                        $params['related_type'] = 'Client';
                        $params['related_id'] = $invoice->patient->client_id;
                    }
                    
                    // Add bank account if available
                    if ($invoice->bank_account_id) {
                        $params['bank_account_id'] = $invoice->bank_account_id;
                    }
                    
                    return TransactionResource::getUrl('create', $params);
                }),
            Actions\DeleteAction::make(),
        ];
    }
}