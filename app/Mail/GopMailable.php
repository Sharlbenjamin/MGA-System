<?php

namespace App\Mail;

use App\Models\Gop;
use Filament\Notifications\Notification;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Services\UploadGopToGoogleDrive;
use Illuminate\Support\Facades\Log;
use App\Services\GoogleDriveFolderService;

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
        try {
            Log::info('Starting GOP mailable build', [
                'gop_id' => $this->gop->id,
                'reference' => $this->gop->file->mga_reference
            ]);

            // Generate the GOP draft PDF
            $pdf = PDF::loadView('pdf.gop', ['gop' => $this->gop]);
            Log::info('PDF generated');

            // Upload the generated pdf to google drive
            $uploadService = new UploadGopToGoogleDrive(new GoogleDriveFolderService());
            Log::info('Upload service instantiated');

            $uploadResult = $uploadService->uploadGopToGoogleDrive(
                $pdf->output(),
                'GOP Out ' . $this->gop->file->mga_reference . ' - ' . $this->gop->file->patient->name . '.pdf',
                $this->gop
            );
            Log::info('Upload attempt completed', ['result' => $uploadResult]);

            // Debug log to file
            file_put_contents(storage_path('logs/gop_debug.txt'), 'Attempting upload for GOP: ' . $this->gop->file->mga_reference . "\n", FILE_APPEND);

            if ($uploadResult === false) {
                Log::error('Failed to upload GOP to Google Drive', [
                    'gop_id' => $this->gop->id,
                    'reference' => $this->gop->file->mga_reference
                ]);
            }

            // Get the appropriate email template and subject based on GOP status
            $template = match($this->gop->status) {
                'Cancelled' => 'cancel-gop-mail',
                'Updated' => 'update-gop-mail',
                default => 'new-gop-mail',
            };

            $subject = match($this->gop->status) {
                'Cancelled' => 'GOP Cancellation - ' . $this->gop->file->mga_reference,
                'Updated' => 'GOP Update - ' . $this->gop->file->mga_reference,
                default => 'MGA GOP for patient - ' . $this->gop->file->patient->name . ' - ' . $this->gop->file->mga_reference,
            };

            // Build the email with the normal template and attach the PDF
            return $this->subject($subject)->view('emails.gop.' . $template)->attachData($pdf->output(),'GOP_' . $this->gop->file->mga_reference . '.pdf',['mime' => 'application/pdf']);
        } catch (\Exception $e) {
            Log::error('Error in GopMailable build', ['error' => $e->getMessage()]);
            return $this->subject('Error in GOP Mailable')->view('emails.gop.error');
        }
    }
}
