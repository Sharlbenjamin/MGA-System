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
use Illuminate\Support\Facades\Log;
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
                    Forms\Components\Checkbox::make('attach_invoice')
                        ->label('The generated draft invoice')
                        ->default(true)
                        ->visible(fn () => $this->record->hasLocalDocument()),
                    
                    Forms\Components\View::make('email_preview')
                        ->view('filament.forms.components.invoice-email-preview')
                        ->viewData([
                            'invoice' => $this->record,
                        ]),
                ])
                ->action(function ($data) {
                    // Start with empty attachments - we'll build it safely
                    $attachments = [];
                    
                    try {
                        // Debug: Log what we receive
                        Log::info('SendInvoice action called', [
                            'data_type' => gettype($data),
                            'data' => is_array($data) ? $data : 'not_array',
                            'data_string' => is_string($data) ? substr($data, 0, 100) : 'not_string',
                        ]);
                        
                        // Safely check for attach_invoice - use multiple methods
                        $attachInvoice = false;
                        
                        // Method 1: Check if data is array and has the key
                        if (is_array($data)) {
                            Log::info('Data is array', ['keys' => array_keys($data)]);
                            if (array_key_exists('attach_invoice', $data)) {
                                $value = $data['attach_invoice'];
                                Log::info('Found attach_invoice key', ['value' => $value, 'type' => gettype($value)]);
                                $attachInvoice = ($value === true || $value === 1 || $value === '1' || $value === 'true' || $value === 'on');
                            }
                        }
                        // Method 2: Try data_get as fallback
                        elseif (!is_string($data)) {
                            $value = data_get($data, 'attach_invoice', false);
                            $attachInvoice = ($value === true || $value === 1 || $value === '1' || $value === 'true' || $value === 'on');
                        }
                        
                        Log::info('Attachment check result', ['attachInvoice' => $attachInvoice]);
                        
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
                    
                        // Add invoice to attachments if checked and available
                        if ($attachInvoice && $invoice->hasLocalDocument()) {
                            $attachments[] = 'invoice';
                            Log::info('Added invoice to attachments');
                        }
                        
                        Log::info('Final attachments array', ['attachments' => $attachments, 'count' => count($attachments)]);
                    
                    // Build attachment list for email body
                    $attachmentList = [];
                    if (in_array('invoice', $attachments)) {
                        $attachmentList[] = '· Invoice ' . $invoice->name;
                    }
                    
                    // Build email body
                    $emailBody = "Dear team,\n\n";
                    $emailBody .= "Find Attached the Invoice {$invoice->name}:\n\n";
                    $emailBody .= "Your Reference : " . ($file->client_reference ?? '') . "\n";
                    $emailBody .= "Patient Name : " . ($patient->name ?? '') . "\n";
                    $emailBody .= "MGA Reference : " . ($file->mga_reference ?? '') . "\n";
                    $emailBody .= "Issue Date : " . ($invoice->invoice_date ? $invoice->invoice_date->format('d/m/Y') : '') . "\n";
                    $emailBody .= "Due Date : " . ($invoice->due_date ? $invoice->due_date->format('d/m/Y') : '') . "\n";
                    $emailBody .= "Total : " . number_format($invoice->total_amount ?? 0, 2) . "€\n";
                    $emailBody .= "GOP Total : " . number_format($gopTotal, 2) . "€\n";
                    
                    if (!empty($attachmentList)) {
                        $emailBody .= "\nAttachments\n";
                        $emailBody .= implode("\n", $attachmentList);
                    }
                    
                    // Use default smtp mailer for testing (uses MAIL_USERNAME/MAIL_PASSWORD from .env)
                    $mailer = 'smtp';
                    
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
                    
                        // Send email
                        try {
                            Log::info('Preparing to send email', [
                                'attachments' => $attachments,
                                'attachments_type' => gettype($attachments),
                                'is_array' => is_array($attachments),
                            ]);
                            
                            // Ensure attachments is definitely an array
                            $attachmentsArray = is_array($attachments) ? $attachments : [];
                            
                            Log::info('Sending email', [
                                'mailer' => $mailer,
                                'recipient' => $recipientEmail,
                                'attachments_array' => $attachmentsArray,
                            ]);
                            
                            Mail::mailer($mailer)->to($recipientEmail)->send(
                                new SendInvoiceToClient($invoice, $attachmentsArray, $emailBody)
                            );
                            
                            Notification::make()
                                ->title('Invoice Sent')
                                ->body('Invoice has been sent to the client successfully.')
                                ->success()
                                ->send();
                        } catch (\Exception $e) {
                            Log::error('Error sending email', [
                                'error' => $e->getMessage(),
                                'trace' => $e->getTraceAsString(),
                                'file' => $e->getFile(),
                                'line' => $e->getLine(),
                            ]);
                            
                            Notification::make()
                                ->title('Failed to Send Invoice')
                                ->body('Error: ' . $e->getMessage())
                                ->danger()
                                ->send();
                        }
                    } catch (\Exception $e) {
                        Log::error('Error in SendInvoice action', [
                            'error' => $e->getMessage(),
                            'trace' => $e->getTraceAsString(),
                            'file' => $e->getFile(),
                            'line' => $e->getLine(),
                        ]);
                        
                        Notification::make()
                            ->title('Error')
                            ->body('An error occurred: ' . $e->getMessage() . ' (Check logs for details)')
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