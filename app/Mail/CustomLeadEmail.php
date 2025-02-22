<?php
namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;

class CustomLeadEmail extends Mailable
{
    public $lead;
    public $draftMail;
    public $user;

    public function __construct($lead, $draftMail, $user)
    {
        $this->lead = $lead;
        $this->draftMail = $draftMail;
        $this->user = $user;
    }

    public function build()
    {
        // Change SMTP credentials dynamically
        Config::set('mail.mailers.smtp.username', $this->user->smtp_username);
        Config::set('mail.mailers.smtp.password', $this->user->smtp_password);

        $body = str_replace(
            ['{first_name}', '{email}'],
            [$this->lead->first_name, $this->lead->email],
            $this->draftMail->body_mail
        );

        $subject = trim($this->draftMail->mail_name);

        // Get the full URL for the signature image
        $signaturePath = $this->user->signature_image
            ? storage_path('app/public/' . $this->user->signature_image)
            : null;

        // Log the path to debug
        Log::info("Signature Image Path: " . ($signaturePath ?? 'No signature image'));

        $email = $this->view('emails.lead_mail')
                    ->from($this->user->email, $this->user->name)
                    ->subject($subject)
                    ->with([
                        'body' => $body,
                    ]);

        // Attach the image properly if it exists
        if ($signaturePath && file_exists($signaturePath)) {
            $email->attach($signaturePath, [
                'as' => 'signature.png',
                'mime' => 'image/png',
            ]);
        }

        return $email;
    }
}