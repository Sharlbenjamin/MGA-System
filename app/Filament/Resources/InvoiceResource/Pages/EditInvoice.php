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
                ->modalDescription('Preview the email before sending')
                ->modalSubmitActionLabel('Send Invoice')
                ->modalSubmitAction(function ($action) {
                    return $action->color('primary');
                })
                ->form([
                    Forms\Components\View::make('email_preview')
                        ->view('filament.forms.components.invoice-email-preview')
                        ->viewData([
                            'invoice' => $this->record,
                        ]),
                ])
                ->action(function () {
                    $invoice = $this->record;
                    
                    // Ensure invoice relationships are loaded
                    $invoice->load(['file.patient.client', 'file.gops']);
                    
                    // Build email body
                    $file = $invoice->file;
                    if (!$file) {
                        Notification::make()
                            ->title('Error')
                            ->body('Invoice file relationship not found.')
                            ->danger()
                            ->send();
                        return;
                    }
                    
                    $gopTotal = $file->gops()->where('type', 'In')->sum('amount');
                    
                    $patient = $file->patient;
                    if (!$patient) {
                        Notification::make()
                            ->title('Error')
                            ->body('Patient relationship not found.')
                            ->danger()
                            ->send();
                        return;
                    }
                    
                    $client = $patient->client;
                    if (!$client) {
                        Notification::make()
                            ->title('Error')
                            ->body('Client relationship not found.')
                            ->danger()
                            ->send();
                        return;
                    }
                    
                    // Build email body - no attachments for now
                    $emailBody = "Dear team,\n\n";
                    $emailBody .= "Find Attached the Invoice {$invoice->name}:\n\n";
                    $emailBody .= "Your Reference : " . ($file->client_reference ?? '') . "\n";
                    $emailBody .= "Patient Name : " . ($patient->name ?? '') . "\n";
                    $emailBody .= "MGA Reference : " . ($file->mga_reference ?? '') . "\n";
                    $emailBody .= "Issue Date : " . ($invoice->invoice_date ? $invoice->invoice_date->format('d/m/Y') : '') . "\n";
                    $emailBody .= "Due Date : " . ($invoice->due_date ? $invoice->due_date->format('d/m/Y') : '') . "\n";
                    $emailBody .= "Total : " . number_format($invoice->total_amount ?? 0, 2) . "€\n";
                    $emailBody .= "GOP Total : " . number_format($gopTotal, 2) . "€\n";
                    
                    // Set mailer based on user's role and SMTP credentials
                    $mailer = 'financial';
                    $user = \App\Models\User::find(Auth::id());
                    $financialRoles = ['Financial Manager', 'Financial Supervisor', 'Financial Department'];
                    
                    if ($user && $user->hasRole($financialRoles) && $user->smtp_username && $user->smtp_password) {
                        Config::set('mail.mailers.financial.username', $user->smtp_username);
                        Config::set('mail.mailers.financial.password', $user->smtp_password);
                    }
                    
                    // Get recipient email from client
                    $recipientEmail = $client->email ?? null;
                    
                    if (!$recipientEmail) {
                        Notification::make()
                            ->title('No Email Found')
                            ->body('The client does not have an email address configured.')
                            ->danger()
                            ->send();
                        return;
                    }
                    
                    // Send email - no attachments for now
                    try {
                        $attachments = []; // Empty array for now
                        
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