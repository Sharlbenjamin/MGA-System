<?php

namespace App\Mail;

use App\Models\Client;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;

class SendOutstandingBalance extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Client $client,
        public Collection $invoices,
        public string $monthName,
        public int $yearNumber
    ) {
    }

    public function build()
    {
        $totalOutstanding = (float) $this->invoices->sum('total_amount');
        $invoiceCount = $this->invoices->count();
        $subject = "MGA x {$this->client->company_name} Outstanding for {$this->monthName} {$this->yearNumber}";

        $pdf = Pdf::loadView('pdf.client_balance', [
            'client' => $this->client,
            'invoices' => $this->invoices,
        ])->output();

        $fileName = "{$this->client->company_name} Outstanding Balance {$this->monthName} {$this->yearNumber}.pdf";

        return $this->view('emails.financial.send-outstanding-balance')
            ->subject($subject)
            ->with([
                'client' => $this->client,
                'invoices' => $this->invoices,
                'totalOutstanding' => $totalOutstanding,
                'invoiceCount' => $invoiceCount,
                'monthName' => $this->monthName,
                'yearNumber' => $this->yearNumber,
            ])
            ->attachData($pdf, $fileName, ['mime' => 'application/pdf']);
    }
}
