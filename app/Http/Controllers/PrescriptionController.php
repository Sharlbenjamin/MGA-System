<?php

namespace App\Http\Controllers;

use App\Models\Prescription;
use Illuminate\Http\Request;

class PrescriptionController extends Controller
{
    public function view(Prescription $prescription)
    {
        return view('prescription.view', compact('prescription'));
    }
}
