<?php

namespace App\Mail;

use App\Models\Invoice;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Mail\Mailables\Attachment;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;

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
        // Use user SMTP credentials if available, otherwise use system default
        Config::set('mail.mailers.smtp.username', $this->user->smtp_username ?? Config::get('mail.mailers.smtp.username'));
        Config::set('mail.mailers.smtp.password', $this->user->smtp_password ?? Config::get('mail.mailers.smtp.password'));

        return $this->view('emails.financial.send-invoice')
                   ->from($this->user->smtp_username, $this->user->name)
                   ->subject('# Invoice' . $this->invoice->name);
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address($this->user->smtp_username, $this->user->name),
            subject: '# Invoice' . $this->invoice->name,
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.financial.send-invoice',
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        $pdf = Pdf::loadView('pdf.invoice', ['invoice' => $this->invoice]);

        return [
            Attachment::fromData(
                fn () => $pdf->output(),
                $this->invoice->name . '.pdf'
            )->withMime('application/pdf'),
        ];
    }
}
