<?php

namespace App\Mail;

use App\Models\Gop;
use Filament\Notifications\Notification;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Config;
use Barryvdh\DomPDF\Facade\Pdf;

use function Livewire\of;

class GopMailable extends Mailable
{
    use Queueable, SerializesModels;

    public $gop;

    public function __construct(Gop $gop)
    {
        $this->gop = $gop;
    }

    public function build()
    {
        // Generate the GOP draft PDF
        $pdf = PDF::loadView('pdf.gop', ['gop' => $this->gop]);

        // Get the appropriate email template and subject based on GOP status
        $template = match($this->gop->status) {
            'Cancelled' => 'cancel-gop-mail',
            'Updated' => 'update-gop-mail',
            default => 'new-gop-mail',
        };

        $subject = match($this->gop->status) {
            'Cancelled' => 'GOP Cancellation - ' . $this->gop->file->mga_reference,
            'Updated' => 'GOP Update - ' . $this->gop->file->mga_reference,
            default => 'New GOP Request - ' . $this->gop->file->mga_reference,
        };

        // Build the email with the normal template and attach the PDF
        return $this->subject($subject)
                    ->view('emails.gop.' . $template)
                    ->attachData(
                        $pdf->output(),
                        'GOP_' . $this->gop->file->mga_reference . '.pdf',
                        ['mime' => 'application/pdf']
                    );
    }
}
