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
                        ->visible(function () {
                            return $this->record->file !== null;
                        })
                        ->disabled(fn () => !$this->record->hasLocalDocument())
                        ->helperText(fn () => !$this->record->hasLocalDocument() ? 'No invoice attachment available' : null),
                    
                    Forms\Components\Checkbox::make('attach_gop')
                        ->label(function () {
                            $file = $this->record->file;
                            if (!$file) return 'GOP (Guarantee of Payment)';
                            
                            $gopsIn = $file->gops()->where('type', 'In')->get();
                            if ($gopsIn->isEmpty()) {
                                return 'GOP (Guarantee of Payment)';
                            }
                            
                            $totalAmount = $gopsIn->sum('amount');
                            return 'GOP (Guarantee of Payment) - Total: ' . number_format($totalAmount, 2) . '€';
                        })
                        ->default(false)
                        ->visible(function () {
                            return $this->record->file !== null;
                        })
                        ->disabled(function () {
                            $file = $this->record->file;
                            if (!$file) return true;
                            $hasGop = $file->gops()->where('type', 'In')->exists();
                            if (!$hasGop) return true;
                            $hasAttachment = $file->gops()->where('type', 'In')->whereNotNull('document_path')->exists();
                            return !$hasAttachment;
                        })
                        ->helperText(function () {
                            $file = $this->record->file;
                            if (!$file) return 'File not found';
                            $hasGop = $file->gops()->where('type', 'In')->exists();
                            $hasAttachment = $file->gops()->where('type', 'In')->whereNotNull('document_path')->exists();
                            
                            if (!$hasGop) {
                                return 'No GOP (type In) found in file';
                            } elseif (!$hasAttachment) {
                                $gopsIn = $file->gops()->where('type', 'In')->get();
                                $totalAmount = $gopsIn->sum('amount');
                                return 'GOP exists (Total: ' . number_format($totalAmount, 2) . '€) but no attachment available';
                            }
                            return null;
                        }),
                    
                    Forms\Components\Checkbox::make('attach_medical_report')
                        ->label('Medical Report')
                        ->default(false)
                        ->visible(function () {
                            return $this->record->file !== null;
                        })
                        ->disabled(function () {
                            $file = $this->record->file;
                            if (!$file) return true;
                            $hasMedicalReport = $file->medicalReports()->exists();
                            if (!$hasMedicalReport) return true;
                            $hasAttachment = $file->medicalReports()->whereNotNull('document_path')->exists();
                            return !$hasAttachment;
                        })
                        ->helperText(function () {
                            $file = $this->record->file;
                            if (!$file) return 'File not found';
                            $hasMedicalReport = $file->medicalReports()->exists();
                            $hasAttachment = $file->medicalReports()->whereNotNull('document_path')->exists();
                            
                            if (!$hasMedicalReport) {
                                return 'No medical report found in file';
                            } elseif (!$hasAttachment) {
                                return 'Medical report exists but no attachment available';
                            }
                            return null;
                        }),
                    
                    Forms\Components\Checkbox::make('attach_bill')
                        ->label('Bill')
                        ->default(false)
                        ->visible(function () {
                            return $this->record->file !== null;
                        })
                        ->disabled(function () {
                            $file = $this->record->file;
                            if (!$file) return true;
                            $hasBill = $file->bills()->exists();
                            if (!$hasBill) return true;
                            $hasAttachment = $file->bills()->whereNotNull('bill_document_path')->exists();
                            return !$hasAttachment;
                        })
                        ->helperText(function () {
                            $file = $this->record->file;
                            if (!$file) return 'File not found';
                            $hasBill = $file->bills()->exists();
                            $hasAttachment = $file->bills()->whereNotNull('bill_document_path')->exists();
                            
                            if (!$hasBill) {
                                return 'No bill found in file';
                            } elseif (!$hasAttachment) {
                                return 'Bill exists but no attachment available';
                            }
                            return null;
                        }),
                    
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
                        
                        // Helper function to safely check checkbox values
                        $getCheckboxValue = function ($key, $default = false) use ($data) {
                            if (is_array($data)) {
                                if (array_key_exists($key, $data)) {
                                    $value = $data[$key];
                                    return ($value === true || $value === 1 || $value === '1' || $value === 'true' || $value === 'on');
                                }
                            } elseif (!is_string($data)) {
                                $value = data_get($data, $key, $default);
                                return ($value === true || $value === 1 || $value === '1' || $value === 'true' || $value === 'on');
                            }
                            return $default;
                        };
                        
                        // Safely check for all attachment checkboxes
                        $attachInvoice = $getCheckboxValue('attach_invoice', false);
                        $attachGop = $getCheckboxValue('attach_gop', false);
                        $attachMedicalReport = $getCheckboxValue('attach_medical_report', false);
                        $attachBill = $getCheckboxValue('attach_bill', false);
                        
                        Log::info('Attachment check results', [
                            'attachInvoice' => $attachInvoice,
                            'attachGop' => $attachGop,
                            'attachMedicalReport' => $attachMedicalReport,
                            'attachBill' => $attachBill,
                        ]);
                        
                        $invoice = $this->record;
                        
                        // Ensure invoice relationships are loaded
                        $invoice->load(['file.patient.client', 'file.gops', 'file.medicalReports', 'file.bills']);
                    
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
                    
                    // Add GOP to attachments if checked and available
                    if ($attachGop && $file) {
                        $hasGopAttachment = $file->gops()->where('type', 'In')->whereNotNull('document_path')->exists();
                        if ($hasGopAttachment) {
                            $attachments[] = 'gop';
                            Log::info('Added GOP to attachments');
                        }
                    }
                    
                    // Add medical report to attachments if checked and available
                    if ($attachMedicalReport && $file) {
                        $hasMedicalReportAttachment = $file->medicalReports()->whereNotNull('document_path')->exists();
                        if ($hasMedicalReportAttachment) {
                            $attachments[] = 'medical_report';
                            Log::info('Added medical report to attachments');
                        }
                    }
                    
                    // Add bill to attachments if checked and available
                    if ($attachBill && $file) {
                        $hasBillAttachment = $file->bills()->whereNotNull('bill_document_path')->exists();
                        if ($hasBillAttachment) {
                            $attachments[] = 'bill';
                            Log::info('Added bill to attachments');
                        }
                    }
                    
                    Log::info('Final attachments array', ['attachments' => $attachments, 'count' => count($attachments)]);
                    
                    // Build attachment list for email body
                    $attachmentList = [];
                    if (in_array('invoice', $attachments)) {
                        $attachmentList[] = '· Invoice ' . $invoice->name;
                    }
                    if (in_array('gop', $attachments)) {
                        $attachmentList[] = '· GOP (Goods of Purchase)';
                    }
                    if (in_array('medical_report', $attachments)) {
                        $attachmentList[] = '· Medical Report';
                    }
                    if (in_array('bill', $attachments)) {
                        $attachmentList[] = '· Bill';
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
                    
                    // Use Operation Mailer (smtp) - same pattern as LeadResource
                    $mailer = 'smtp';
                    $user = \App\Models\User::find(Auth::id());
                    
                    // Get SMTP credentials (use user's credentials if available, otherwise system defaults)
                    $smtpUsername = $user && $user->smtp_username ? $user->smtp_username : Config::get('mail.mailers.smtp.username');
                    $smtpPassword = $user && $user->smtp_password ? $user->smtp_password : Config::get('mail.mailers.smtp.password');
                    
                    // Dynamically set the mail configuration for operation mailer
                    if ($smtpUsername && $smtpPassword) {
                        Config::set('mail.mailers.smtp.username', $smtpUsername);
                        Config::set('mail.mailers.smtp.password', $smtpPassword);
                    }
                    
                    // Debug: Log mailer configuration
                    Log::info('Mailer configuration check', [
                        'mailer' => $mailer,
                        'user_id' => $user->id ?? null,
                        'user_has_smtp' => $user && $user->smtp_username && $user->smtp_password,
                        'smtp_username' => $smtpUsername ? 'set' : 'not_set',
                        'smtp_host' => config('mail.mailers.smtp.host'),
                        'smtp_port' => config('mail.mailers.smtp.port'),
                    ]);
                    
                    // Debug: Log which mailer we're using
                    Log::info('Mailer selection', [
                        'mailer' => $mailer,
                        'smtp_config' => [
                            'host' => config('mail.mailers.smtp.host'),
                            'username' => config('mail.mailers.smtp.username') ? 'set' : 'not_set',
                            'port' => config('mail.mailers.smtp.port'),
                        ],
                    ]);
                    
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
                                'mailer_being_used' => $mailer,
                                'mailer_config' => config("mail.mailers.{$mailer}"),
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