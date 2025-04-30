<?php

namespace App\Mail;

use App\Models\Invoice;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Mail\Mailables\Attachment;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;

class SendInvoice extends Mailable
{
    use Queueable, SerializesModels;

    public $user;
    public Invoice $invoice;

    /**
     * Create a new message instance.
     */
    public function __construct(
        Invoice $invoice,
        $user
    ) {
        $this->invoice = $invoice;
        $this->user = $user;
    }

    public function build()
    {
        // Generate PDF
        $pdf = Pdf::loadView('pdf.invoice', ['invoice' => $this->invoice]);
        $pdfContent = $pdf->output();

        return $this->view('emails.financial.send-invoice')
                   ->subject('Invoice ' . $this->invoice->name . 'from MedGuard')
                   ->attachData(
                       $pdfContent,
                       $this->invoice->name . '.pdf',
                       ['mime' => 'application/pdf']
                   );
    }
}
