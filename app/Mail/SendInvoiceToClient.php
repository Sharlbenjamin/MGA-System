<?php

namespace App\Mail;

use App\Models\Invoice;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Barryvdh\DomPDF\Facade\Pdf;

class SendInvoiceToClient extends Mailable
{
    use Queueable, SerializesModels;

    public $invoice;
    public $attachmentTypes; // Renamed to avoid conflict with Laravel's $attachments property
    public $emailBody;

    /**
     * Create a new message instance.
     */
    public function __construct(
        Invoice $invoice,
        $attachments,
        $emailBody
    ) {
        $this->invoice = $invoice;
        // Ensure attachmentTypes is always an array
        $this->attachmentTypes = is_array($attachments) ? $attachments : [];
        $this->emailBody = is_string($emailBody) ? $emailBody : '';
    }

    public function build()
    {
        // Ensure attachmentTypes is always an array - handle all possible types
        $attachmentTypes = [];
        if (is_array($this->attachmentTypes)) {
            $attachmentTypes = $this->attachmentTypes;
        } elseif (is_string($this->attachmentTypes)) {
            // If it's a string, try to decode it (might be JSON)
            $decoded = json_decode($this->attachmentTypes, true);
            if (is_array($decoded)) {
                $attachmentTypes = $decoded;
            }
        }
        
        // Ensure invoice relationships are loaded
        if (!$this->invoice->relationLoaded('file')) {
            $this->invoice->load('file');
        }
        
        $file = $this->invoice->file;
        if (!$file) {
            throw new \Exception('Invoice file relationship not found');
        }
        
        $patient = $file->patient ?? null;
        $patientName = $patient->name ?? '';
        
        $mail = $this->view('emails.financial.send-invoice-to-client')
            ->subject('MGA Invoice ' . $this->invoice->name . ' for ' . $patientName . ' | ' . ($file->client_reference ?? '') . ' | ' . ($file->mga_reference ?? ''));

        // Attach invoice PDF if selected - double check attachmentTypes is array
        try {
            if (is_array($attachmentTypes) && in_array('invoice', $attachmentTypes) && $this->invoice->hasLocalDocument()) {
                $invoicePath = Storage::disk('public')->path($this->invoice->invoice_document_path);
                if (file_exists($invoicePath)) {
                    $mail->attach($invoicePath, [
                        'as' => $this->invoice->name . '.pdf',
                        'mime' => 'application/pdf',
                    ]);
                }
            }
        } catch (\Exception $e) {
            Log::error('Error attaching invoice', [
                'error' => $e->getMessage(),
                'attachmentTypes_type' => gettype($attachmentTypes),
                'attachmentTypes' => $attachmentTypes,
            ]);
        }

        // Attach bill PDF if selected - double check attachmentTypes is array
        if (is_array($attachmentTypes) && in_array('bill', $attachmentTypes) && $file) {
            $bills = $file->bills()->whereNotNull('bill_document_path')->get();
            foreach ($bills as $bill) {
                if ($bill && $bill->bill_document_path) {
                    $billPath = Storage::disk('public')->path($bill->bill_document_path);
                    if (file_exists($billPath)) {
                        $patientName = $file->patient->name ?? 'Unknown';
                        $mgaRef = $file->mga_reference ?? '';
                        $mail->attach($billPath, [
                            'as' => 'Bill for ' . $patientName . ' | ' . $mgaRef . '.pdf',
                            'mime' => 'application/pdf',
                        ]);
                    }
                }
            }
        }

        // Attach medical report PDF if selected - double check attachmentTypes is array
        if (is_array($attachmentTypes) && in_array('medical_report', $attachmentTypes) && $file) {
            $medicalReports = $file->medicalReports()->whereNotNull('document_path')->get();
            foreach ($medicalReports as $report) {
                if ($report && $report->document_path) {
                    $reportPath = Storage::disk('public')->path($report->document_path);
                    if (file_exists($reportPath)) {
                        $patientName = $file->patient->name ?? 'Unknown';
                        $mgaRef = $file->mga_reference ?? '';
                        $mail->attach($reportPath, [
                            'as' => 'Medical Report for ' . $patientName . ' | ' . $mgaRef . '.pdf',
                            'mime' => 'application/pdf',
                        ]);
                    }
                }
            }
        }

        // Attach GOP In PDF if selected - double check attachmentTypes is array
        if (is_array($attachmentTypes) && in_array('gop', $attachmentTypes) && $file) {
            $gops = $file->gops()->where('type', 'In')->whereNotNull('document_path')->get();
            foreach ($gops as $gop) {
                if ($gop && $gop->document_path) {
                    $gopPath = Storage::disk('public')->path($gop->document_path);
                    if (file_exists($gopPath)) {
                        $patientName = $file->patient->name ?? 'Unknown';
                        $mgaRef = $file->mga_reference ?? '';
                        $mail->attach($gopPath, [
                            'as' => 'GOP for ' . $patientName . ' | ' . $mgaRef . '.pdf',
                            'mime' => 'application/pdf',
                        ]);
                    }
                }
            }
        }

        return $mail;
    }
}

