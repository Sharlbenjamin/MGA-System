<?php
namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;

class CustomLeadEmail extends Mailable
{
    public $recipient;  // Can be either a Lead or a ProviderLead
    public $draftMail;
    public $user;

    public function __construct($recipient, $draftMail, $user)
    {
        $this->recipient = $recipient;
        $this->draftMail = $draftMail;
        $this->user = $user;
    }

    public function build()
    {
        // Use user SMTP credentials if available, otherwise use system default
        Config::set('mail.mailers.smtp.username', $this->user->smtp_username ?? Config::get('mail.mailers.smtp.username'));
        Config::set('mail.mailers.smtp.password', $this->user->smtp_password ?? Config::get('mail.mailers.smtp.password'));

        // Determine if the recipient is a Client Lead or Provider Lead
        $isProviderLead = $this->recipient instanceof \App\Models\ProviderLead;


        // Use 'name' for ProviderLeads, 'first_name' for Client Leads
        $leadName = $isProviderLead ? $this->recipient->name : $this->recipient->first_name;

        // Get company name correctly
        if ($isProviderLead) {
            $company = optional($this->recipient->provider)->name ?? 'You';
        } else {
            $company = optional($this->recipient->client)->company_name ?? 'You';
        }

        // Ensure `service_types` is a properly formatted string
        if (is_string($this->recipient->service_types)) {
            $services = $this->recipient->service_types; // Already a comma-separated string
        } elseif (is_array($this->recipient->service_types)) {
            $services = implode(', ', $this->recipient->service_types); // Convert array to string
        } else {
            $services = 'your services'; // Default fallback
        }


        // **Extract city name properly (only exists in Provider Leads)**
        if ($isProviderLead) {
            $city = optional($this->recipient->city)->name ?? 'Unknown City';
        } else {
            $city = 'your city'; // Clients do not have a city field
}

        /// Replace placeholders in the email body
        $body = str_replace(
            ['{name}', '{email}', '{company}', '{city}', '{service}', '{username}'],
            [$leadName, $this->recipient->email, $company, $city, $services, Auth::user()->signature->name],
            $this->draftMail->body_mail
        );

        // Replace placeholders in the subject
        $subject = str_replace(
            ['{name}', '{email}', '{company}', '{city}', '{service}', '{username}'],
            [$leadName, $this->recipient->email, $company, $city, $services, Auth::user()->signature->name],
            trim($this->draftMail->mail_name)
        );

        // Get the full URL for the signature image (if available)
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
