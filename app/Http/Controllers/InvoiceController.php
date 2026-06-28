<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Support\InvoicePdfView;
use Barryvdh\DomPDF\Facade\Pdf;

class InvoiceController extends Controller
{
    public function view(Invoice $invoice)
    {
        // Refresh the invoice record to get the latest data from database
        $invoice->refresh();
        $invoice->load(['file', 'file.patient', 'file.patient.client', 'file.bills']);
        
        // Generate payment link if invoice is Posted and doesn't have one
        if ($invoice->status === 'Posted' && !$invoice->payment_link) {
            $invoice->generatePaymentLink();
            $invoice->refresh(); // Refresh again to get the payment link
        }
        
        $pdf = Pdf::loadView('pdf.invoice', InvoicePdfView::data($invoice));
        return $pdf->stream('invoice.pdf');
    }
}

