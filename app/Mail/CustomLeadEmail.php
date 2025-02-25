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

    // Get company name (if client exists)
    $company = optional($this->lead->client)->company_name ?? 'Your Company';

    // Replace placeholders in the email body
    $body = str_replace(
        ['{first_name}', '{email}', '{company}'],
        [$this->lead->first_name, $this->lead->email, $company],
        $this->draftMail->body_mail
    );

    // Replace placeholders in the subject (header)
    $subject = str_replace(
        ['{first_name}', '{email}', '{company}'],
        [$this->lead->first_name, $this->lead->email, $company],
        trim($this->draftMail->mail_name)
    );

    // Get the full URL for the signature image
    $signatureUrl = $this->user->signature_image
        ? asset('storage/' . $this->user->signature_image)
        : null;

    Log::info("Final Email Subject: " . $subject);
    Log::info("Signature Image URL: " . $signatureUrl);

    return $this->view('emails.lead_mail')
                ->from($this->user->email, $this->user->name)
                ->subject($subject)
                ->with([
                    'body' => $body,
                    'signature' => $signatureUrl,
                ]);
}
}