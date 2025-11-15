<?php

namespace App\Mail;

use App\Models\Invoice;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Barryvdh\DomPDF\Facade\Pdf;

class SendInvoiceToClient extends Mailable
{
    use Queueable, SerializesModels;

    public Invoice $invoice;
    public array $attachments;
    public string $emailBody;

    /**
     * Create a new message instance.
     */
    public function __construct(
        Invoice $invoice,
        array $attachments,
        string $emailBody
    ) {
        $this->invoice = $invoice;
        $this->attachments = $attachments;
        $this->emailBody = $emailBody;
    }

    public function build()
    {
        $mail = $this->view('emails.financial.send-invoice-to-client')
            ->subject('MGA Invoice ' . $this->invoice->name . ' for ' . $this->invoice->file->client_reference . ' | ' . $this->invoice->file->mga_reference);

        // Attach invoice PDF if selected
        if (in_array('invoice', $this->attachments) && $this->invoice->hasLocalDocument()) {
            $invoicePath = Storage::disk('public')->path($this->invoice->invoice_document_path);
            if (file_exists($invoicePath)) {
                $mail->attach($invoicePath, [
                    'as' => $this->invoice->name . '.pdf',
                    'mime' => 'application/pdf',
                ]);
            }
        }

        // Attach bill PDF if selected
        if (in_array('bill', $this->attachments)) {
            $bills = $this->invoice->file->bills()->whereNotNull('bill_document_path')->get();
            foreach ($bills as $bill) {
                $billPath = Storage::disk('public')->path($bill->bill_document_path);
                if (file_exists($billPath)) {
                    $mail->attach($billPath, [
                        'as' => 'Bill for ' . $this->invoice->file->patient->name . ' | ' . $this->invoice->file->mga_reference . '.pdf',
                        'mime' => 'application/pdf',
                    ]);
                }
            }
        }

        // Attach medical report PDF if selected
        if (in_array('medical_report', $this->attachments)) {
            $medicalReports = $this->invoice->file->medicalReports()->whereNotNull('document_path')->get();
            foreach ($medicalReports as $report) {
                $reportPath = Storage::disk('public')->path($report->document_path);
                if (file_exists($reportPath)) {
                    $mail->attach($reportPath, [
                        'as' => 'Medical Report for ' . $this->invoice->file->patient->name . ' | ' . $this->invoice->file->mga_reference . '.pdf',
                        'mime' => 'application/pdf',
                    ]);
                }
            }
        }

        // Attach GOP In PDF if selected
        if (in_array('gop', $this->attachments)) {
            $gops = $this->invoice->file->gops()->where('type', 'In')->whereNotNull('document_path')->get();
            foreach ($gops as $gop) {
                $gopPath = Storage::disk('public')->path($gop->document_path);
                if (file_exists($gopPath)) {
                    $mail->attach($gopPath, [
                        'as' => 'GOP for ' . $this->invoice->file->patient->name . ' | ' . $this->invoice->file->mga_reference . '.pdf',
                        'mime' => 'application/pdf',
                    ]);
                }
            }
        }

        return $mail;
    }
}

