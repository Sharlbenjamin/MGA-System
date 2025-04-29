<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

class InvoiceController extends Controller
{
    public function view(Invoice $invoice)
    {
        $pdf = Pdf::loadView('pdf.invoice', compact('invoice'));
        return $pdf->stream('invoice.pdf');
    }
}

