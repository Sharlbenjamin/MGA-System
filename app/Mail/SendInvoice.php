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
        // Fetch updated user info from the database
        $user = \App\Models\User::find($this->user->id);

        // Get SMTP credentials (use system default if user's credentials are missing)
        $smtpUsername = $user->smtp_username ?? Config::get('mail.mailers.smtp.username');
        $smtpPassword = $user->smtp_password ?? Config::get('mail.mailers.smtp.password');

        // Ensure SMTP credentials are set correctly
        if (!$smtpUsername || !$smtpPassword) {
            Log::error("SMTP credentials missing for user: {$user->id}");
            return;
        }

        // Dynamically set the mail configuration
        Config::set('mail.mailers.smtp.username', $smtpUsername);
        Config::set('mail.mailers.smtp.password', $smtpPassword);

        // Generate PDF
        $pdf = Pdf::loadView('pdf.invoice', ['invoice' => $this->invoice]);
        $pdfContent = $pdf->output();

        // Log final email details
        Log::info('SendInvoice - Final email details', [
            'from' => $user->email,
            'from_name' => $user->name,
            'subject' => '# Invoice' . $this->invoice->name,
            'view' => 'emails.financial.send-invoice',
            'attachment' => $this->invoice->name . '.pdf',
            'current_mail_config' => Config::get('mail')
        ]);

        return $this->view('emails.financial.send-invoice')
                   ->from($user->email, $user->name)
                   ->subject('Invoice #' . $this->invoice->name)
                   ->attachData(
                       $pdfContent,
                       $this->invoice->name . '.pdf',
                       ['mime' => 'application/pdf']
                   );
    }
}
