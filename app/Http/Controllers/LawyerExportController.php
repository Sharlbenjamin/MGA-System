<?php

namespace App\Http\Controllers;

use App\Exports\LawyerDocumentationExport;
use App\Services\LawyerDocumentationExportService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class LawyerExportController extends Controller
{
    public function export(Request $request, LawyerDocumentationExportService $exportService)
    {
        $validated = $request->validate([
            'year' => ['nullable', 'integer'],
            'quarter' => ['nullable', 'string'],
            'iva_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'nif_source' => ['nullable', 'in:country,niv_number'],
            'bank_account_id' => ['nullable', 'integer', 'exists:bank_accounts,id'],
        ]);

        $year = (int) ($validated['year'] ?? Carbon::now()->year);
        $quarter = (string) ($validated['quarter'] ?? '1');
        $ivaPercent = (float) ($validated['iva_percent'] ?? 21);
        $nifSource = (string) ($validated['nif_source'] ?? 'country');
        $bankAccountId = isset($validated['bank_account_id']) ? (int) $validated['bank_account_id'] : null;

        $payload = $exportService->buildExportPayload($year, $quarter, $ivaPercent, $nifSource, $bankAccountId);

        return Excel::download(
            new LawyerDocumentationExport($payload),
            $payload['filename']
        );
    }
}
