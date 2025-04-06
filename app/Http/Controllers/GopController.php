<?php

namespace App\Http\Controllers;

use App\Models\Gop;
use Illuminate\Http\Request;
use Barryvdh\DomPDF\Facade\Pdf;

class GopController extends Controller
{
    public function view(Gop $gop)
    {
        $pdf = Pdf::loadView('pdf.gop', compact('gop'));
        return $pdf->stream('gop.pdf');
    }
}