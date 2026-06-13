<?php

namespace App\Mail;

use App\Models\File;
use App\Models\Gop;
use App\Models\ProviderBranch;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;

class AppointmentRequestMailable extends Mailable
{
    use Queueable, SerializesModels;

    public $file;
    public ?ProviderBranch $branch;
    public $customEmails;
    public ?Gop $gop;
    public ?User $user;
    public ?string $gopPdfContent;

    public function __construct(
        File $file,
        ?ProviderBranch $branch = null,
        array $customEmails = [],
        ?Gop $gop = null,
        ?User $user = null,
        ?string $gopPdfContent = null,
    ) {
        $this->file = $file;
        $this->branch = $branch;
        $this->customEmails = $customEmails;
        $this->gop = $gop;
        $this->user = $user;
        $this->gopPdfContent = $gopPdfContent;
    }

    public function build()
    {
        $user = $this->user ?? Auth::user();
        $username = $user?->smtp_username ?? config('mail.from.address');
        $name = $user?->name ?? config('mail.from.name');

        if ($user) {
            Config::set('mail.mailers.smtp.username', $user->smtp_username ?? Config::get('mail.mailers.smtp.username'));
            Config::set('mail.mailers.smtp.password', $user->smtp_password ?? Config::get('mail.mailers.smtp.password'));
        }

        $subject = 'Appointment Request - ' . $this->file->patient->name . ' - ' . $this->file->mga_reference;

        $mailBuilder = $this->view('emails.request-appointment')
            ->from($username, $name)
            ->subject($subject)
            ->with([
                'file' => $this->file,
                'branch' => $this->branch,
                'customEmails' => $this->customEmails
            ]);

        // Add recipients
        $hasBranchEmail = $this->branch && !empty($this->branch->email);

        // If branch email exists, set as primary recipient
        if ($hasBranchEmail) {
            $mailBuilder->to($this->branch->email);
        }

        // Add operation contact email if available and branch provided
        if ($this->branch && $this->branch->operationContact && $this->branch->operationContact->email) {
            $mailBuilder->cc($this->branch->operationContact->email);
        }

        // Add custom emails - if no branch email, make them TO recipients, otherwise CC
        foreach ($this->customEmails as $email) {
            if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                if ($hasBranchEmail) {
                    $mailBuilder->cc($email);
                } else {
                    $mailBuilder->to($email);
                }
            }
        }

        $gopForAttachment = $this->resolveGopForAttachment();
        if ($gopForAttachment) {
            try {
                $pdfContent = $this->gopPdfContent ?? Pdf::loadView('pdf.gop', ['gop' => $gopForAttachment])->output();
                $mailBuilder->attachData(
                    $pdfContent,
                    'GOP_' . $this->file->mga_reference . '.pdf',
                    ['mime' => 'application/pdf']
                );
            } catch (\Throwable $exception) {
                Log::warning('Failed to attach GOP to appointment request email', [
                    'file_id' => $this->file->id,
                    'gop_id' => $gopForAttachment->id,
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        return $mailBuilder;
    }

    protected function resolveGopForAttachment(): ?Gop
    {
        if ($this->gop && $this->gop->type === 'Out') {
            return $this->gop;
        }

        return $this->file->gops()
            ->where('type', 'Out')
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->first();
    }
}
