<?php

namespace App\Mail;

use App\Models\Invoice;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Barryvdh\DomPDF\Facade\Pdf;

class SendBalance extends Mailable
{
    use Queueable, SerializesModels;

    public $type;
    public $invoices;
    public $msg;

    /**
     * Create a new message instance.
     */
    public function __construct($type, $invoices, $msg) {
        $this->type = $type;
        $this->invoices = $invoices;
        $this->msg = $msg;
    }

    public function build()
    {
        $mail = $this->view('emails.financial.send-balance')
                    ->subject('Balance Update from MedGuard to ' . $this->invoices->first()->patient->client->company_name);

        // Attach each invoice PDF
        foreach ($this->invoices as $invoice) {
            $pdfContent = Pdf::loadView('pdf.invoice', ['invoice' => $invoice])->output();
            $mail->attachData($pdfContent, $invoice->name . '.pdf', ['mime' => 'application/pdf']);
        }

        return $mail;
    }
}
