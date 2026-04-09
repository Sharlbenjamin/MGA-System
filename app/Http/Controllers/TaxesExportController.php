<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;
use App\Exports\TaxesModeExport;
use App\Models\Invoice;
use App\Models\Bill;
use App\Models\FileFee;
use Maatwebsite\Excel\Facades\Excel;
use Google\Client;
use Google\Service\Drive;

class TaxesExportController extends Controller
{
    public function export(Request $request)
    {
        $validated = $request->validate([
            'year' => ['nullable', 'integer'],
            'quarter' => ['nullable', 'string'],
            'export_mode' => ['nullable', 'in:invoices_only,invoices_and_payments'],
            'iva_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'nif_source' => ['nullable', 'in:country,niv_number'],
        ]);

        $year = (int) ($validated['year'] ?? Carbon::now()->year);
        $quarter = (string) ($validated['quarter'] ?? '1');
        $exportMode = (string) ($validated['export_mode'] ?? 'invoices_only');
        $ivaPercent = (float) ($validated['iva_percent'] ?? 21);
        $nifSource = (string) ($validated['nif_source'] ?? 'country');

        $payload = $this->buildExportPayload($year, $quarter, $exportMode, $ivaPercent, $nifSource);

        return Excel::download(
            new TaxesModeExport($payload['rows'], $payload['headings']),
            $payload['filename']
        );
    }

    public function buildExportPayload(
        int $year,
        string $quarter,
        string $exportMode,
        float $ivaPercent,
        string $nifSource
    ): array {
        [$startDate, $endDate] = $this->resolvePeriodDates($year, $quarter);
        $ivaRate = $ivaPercent / 100;

        $invoices = Invoice::query()
            ->with([
                'file.serviceType',
                'patient.country',
                'patient.client.financialContact.country',
                'patient.client.operationContact.country',
                'patient.client.gopContact.country',
            ])
            ->where('status', 'Paid')
            ->whereBetween('invoice_date', [$startDate, $endDate])
            ->orderBy('invoice_date')
            ->get();

        $headings = [
            'Record Type',
            'Invoice Date',
            'Invoice Number',
            'Service',
            'Client Name',
            'Client Country',
            'NIF',
            'Patient Name',
            'Total Amount',
            'IVA %',
            'Total After IVA',
            'Status',
            'Source',
            'Notes',
        ];

        $rows = [];

        foreach ($invoices as $invoice) {
            $fileFeeAmount = $this->resolveFileFeeAmountForFile($invoice->file);
            $clientCountry = $this->resolveClientCountryFromInvoice($invoice);
            $amount = $fileFeeAmount !== null ? round($fileFeeAmount, 2) : 'N/A';
            $totalAfterIva = $fileFeeAmount !== null
                ? round($fileFeeAmount * (1 + $ivaRate), 2)
                : 'N/A';

            $rows[] = [
                'Invoice',
                optional($invoice->invoice_date)->format('Y-m-d'),
                $invoice->name,
                $invoice->file?->serviceType?->name ?? '-',
                $invoice->patient?->client?->company_name ?? '-',
                $clientCountry,
                $this->resolveNifValue($invoice, $nifSource),
                $invoice->patient?->name ?? '-',
                $amount,
                $ivaPercent,
                $totalAfterIva,
                $invoice->status,
                'Invoice',
                '',
            ];
        }

        if ($exportMode === 'invoices_and_payments') {
            $bills = Bill::query()
                ->with([
                    'file.serviceType',
                    'file.patient.country',
                    'file.patient.client.financialContact.country',
                    'file.patient.client.operationContact.country',
                    'file.patient.client.gopContact.country',
                ])
                ->whereBetween('bill_date', [$startDate, $endDate])
                ->orderBy('bill_date')
                ->get();

            foreach ($bills as $bill) {
                $amount = (float) $bill->total_amount;
                $file = $bill->file;
                $clientCountry = $this->resolveClientCountryFromFile($file);

                $rows[] = [
                    'Payment',
                    optional($bill->bill_date)->format('Y-m-d'),
                    $bill->name,
                    $file?->serviceType?->name ?? '-',
                    $file?->patient?->client?->company_name ?? '-',
                    $clientCountry,
                    $this->resolveNifFromFile($file, $nifSource),
                    $file?->patient?->name ?? '-',
                    round($amount, 2),
                    'N/A',
                    'N/A',
                    'N/A',
                    'Bill',
                    '',
                ];
            }

            $outTransactions = DB::table('transactions')
                ->whereIn('type', ['Outflow', 'Expense'])
                ->whereBetween('date', [$startDate, $endDate])
                ->orderBy('date')
                ->get();

            foreach ($outTransactions as $transaction) {
                $amount = (float) $transaction->amount;

                $rows[] = [
                    'Payment',
                    optional(Carbon::parse($transaction->date))->format('Y-m-d'),
                    $transaction->name,
                    '-',
                    '-',
                    '-',
                    '-',
                    '-',
                    round($amount, 2),
                    'N/A',
                    'N/A',
                    'N/A',
                    'Transaction',
                    $transaction->notes ?? '',
                ];
            }
        }

        $filenameQuarter = $quarter === 'full' ? 'full' : 'Q' . $quarter;
        $filename = "taxes_report_{$year}_{$filenameQuarter}_" . now()->format('Y-m-d_H-i-s') . '.xlsx';

        return [
            'headings' => $headings,
            'rows' => $rows,
            'filename' => $filename,
        ];
    }

    private function resolvePeriodDates(int $year, string $quarter): array
    {
        if ($quarter !== 'full') {
            $startMonth = ((int) $quarter - 1) * 3 + 1;
            $endMonth = $startMonth + 2;

            return [
                Carbon::create($year, $startMonth, 1)->startOfMonth(),
                Carbon::create($year, $endMonth, 1)->endOfMonth(),
            ];
        }

        return [
            Carbon::create($year, 1, 1)->startOfYear(),
            Carbon::create($year, 12, 31)->endOfYear(),
        ];
    }

    private function resolveNifValue(Invoice $invoice, string $nifSource): string
    {
        $clientCountry = $this->resolveClientCountryFromInvoice($invoice);
        $clientNiv = $invoice->patient?->client?->niv_number ?: '-';

        // Business rule: If client country is in Europe use NIF/NIV, otherwise use country name.
        if ($this->isEuropeanCountry($clientCountry)) {
            return $clientNiv;
        }

        return $clientCountry ?: '-';
    }

    private function resolveNifFromFile($file, string $nifSource): string
    {
        $clientCountry = $this->resolveClientCountryFromFile($file);
        $clientNiv = $file?->patient?->client?->niv_number ?: '-';

        // Business rule: If client country is in Europe use NIF/NIV, otherwise use country name.
        if ($this->isEuropeanCountry($clientCountry)) {
            return $clientNiv;
        }

        return $clientCountry ?: '-';
    }

    private function resolveClientCountryFromInvoice(Invoice $invoice): string
    {
        return $this->resolveClientCountryFromClient(
            $invoice->patient?->client,
            $invoice->patient?->country?->name
        );
    }

    private function resolveClientCountryFromFile($file): string
    {
        return $this->resolveClientCountryFromClient(
            $file?->patient?->client,
            $file?->patient?->country?->name
        );
    }

    private function resolveClientCountryFromClient($client, ?string $fallback = null): string
    {
        if (!$client) {
            return $fallback ?: '-';
        }

        return $client->financialContact?->country?->name
            ?? $client->operationContact?->country?->name
            ?? $client->gopContact?->country?->name
            ?? $fallback
            ?? '-';
    }

    private function isEuropeanCountry(?string $countryName): bool
    {
        if (!$countryName) {
            return false;
        }

        $euCountries = [
            'austria', 'belgium', 'bulgaria', 'croatia', 'cyprus', 'czech republic',
            'denmark', 'estonia', 'finland', 'france', 'germany', 'greece', 'hungary',
            'ireland', 'italy', 'latvia', 'lithuania', 'luxembourg', 'malta',
            'netherlands', 'poland', 'portugal', 'romania', 'slovakia', 'slovenia',
            'spain', 'sweden',
        ];

        return in_array(mb_strtolower(trim($countryName)), $euCountries, true);
    }

    private function resolveFileFeeAmountForFile($file): ?float
    {
        if (!$file || !$file->service_type_id) {
            return null;
        }

        $serviceTypeId = (int) $file->service_type_id;
        $countryId = $file->country_id ? (int) $file->country_id : null;
        $cityId = $file->city_id ? (int) $file->city_id : null;

        static $cache = [];
        $cacheKey = implode(':', [$serviceTypeId, $countryId ?? 'null', $cityId ?? 'null']);

        if (array_key_exists($cacheKey, $cache)) {
            return $cache[$cacheKey];
        }

        if ($countryId && $cityId) {
            $exact = FileFee::query()
                ->where('service_type_id', $serviceTypeId)
                ->where('country_id', $countryId)
                ->where('city_id', $cityId)
                ->first();
            if ($exact) {
                return $cache[$cacheKey] = (float) $exact->amount;
            }
        }

        if ($countryId) {
            $countryDefault = FileFee::query()
                ->where('service_type_id', $serviceTypeId)
                ->where('country_id', $countryId)
                ->whereNull('city_id')
                ->first();
            if ($countryDefault) {
                return $cache[$cacheKey] = (float) $countryDefault->amount;
            }
        }

        $globalDefault = FileFee::query()
            ->where('service_type_id', $serviceTypeId)
            ->whereNull('country_id')
            ->whereNull('city_id')
            ->first();

        $cache[$cacheKey] = $globalDefault ? (float) $globalDefault->amount : null;

        return $cache[$cacheKey];
    }

    public function exportZip(Request $request)
    {
        $year = $request->get('year', Carbon::now()->year);
        $quarter = $request->get('quarter', '1');

        // Calculate date range based on quarter
        if ($quarter !== 'full') {
            $startMonth = ($quarter - 1) * 3 + 1;
            $endMonth = $startMonth + 2;
            $startDate = Carbon::create($year, $startMonth, 1)->startOfMonth();
            $endDate = Carbon::create($year, $endMonth, 1)->endOfMonth();
        } else {
            $startDate = Carbon::create($year, 1, 1)->startOfYear();
            $endDate = Carbon::create($year, 12, 31)->endOfYear();
        }

        // Get data for the period
        $invoices = Invoice::query()
            ->whereBetween('invoice_date', [$startDate, $endDate])
            ->whereNotNull('invoice_google_link')
            ->get();

        $bills = Bill::query()
            ->whereBetween('bill_date', [$startDate, $endDate])
            ->whereNotNull('bill_google_link')
            ->get();

        $expenses = DB::table('transactions')
            ->join('bank_accounts', 'transactions.bank_account_id', '=', 'bank_accounts.id')
            ->where('transactions.type', 'Expense')
            ->where('bank_accounts.type', 'Internal')
            ->whereBetween('transactions.date', [$startDate, $endDate])
            ->whereNotNull('transactions.attachment_path')
            ->select('transactions.*')
            ->get();

        // Create zip file
        $zipFileName = "taxes_documents_{$year}_Q{$quarter}_" . now()->format('Y-m-d_H-i-s') . '.zip';
        $zipPath = storage_path('app/temp/' . $zipFileName);
        
        // Ensure temp directory exists
        if (!file_exists(dirname($zipPath))) {
            mkdir(dirname($zipPath), 0755, true);
        }

        $zip = new \ZipArchive();
        if ($zip->open($zipPath, \ZipArchive::CREATE) !== TRUE) {
            return response()->json(['error' => 'Could not create zip file'], 500);
        }

        // Add invoices to zip
        foreach ($invoices as $invoice) {
            $fileName = 'Invoices/' . $invoice->name . '.pdf';

            // Prefer generating the invoice PDF locally (does not require Drive)
            try {
                $pdfContent = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.invoice', ['invoice' => $invoice])->output();
                if (!empty($pdfContent)) {
                    $zip->addFromString($fileName, $pdfContent);
                    continue; // Done with this invoice
                }
            } catch (\Throwable $e) {
                Log::warning('Failed to generate local invoice PDF, will try Drive', [
                    'invoice_id' => $invoice->id,
                    'error' => $e->getMessage(),
                ]);
            }

            // If local generation failed, try Google Drive if link exists
            if ($invoice->invoice_google_link) {
                $result = $this->downloadGoogleDriveFile($invoice->invoice_google_link);
                if (is_string($result) && $result !== 'Document not accessible' && $result !== 'Document download failed' && $result !== 'Service unavailable') {
                    $zip->addFromString($fileName, $result);
                } else {
                    // Fallback to text file with link if download fails
                    $fileNameTxt = 'Invoices/' . $invoice->name . '_LINK.txt';
                    $content = $this->createDocumentLinkFile($invoice->name, $invoice->invoice_google_link, 'Invoice', $result);
                    $zip->addFromString($fileNameTxt, $content);
                }
            }
        }

        // Add bills to zip
        foreach ($bills as $bill) {
            if ($bill->bill_google_link) {
                $fileName = 'Bills/' . $bill->name . '.pdf';
                $result = $this->downloadGoogleDriveFile($bill->bill_google_link);
                if (is_string($result) && $result !== 'Document not accessible' && $result !== 'Document download failed' && $result !== 'Service unavailable') {
                    $zip->addFromString($fileName, $result);
                } else {
                    // Fallback to text file with link if download fails
                    $fileName = 'Bills/' . $bill->name . '_LINK.txt';
                    $content = $this->createDocumentLinkFile($bill->name, $bill->bill_google_link, 'Bill', $result);
                    $zip->addFromString($fileName, $content);
                }
            }
        }

        // Add expenses to zip
        foreach ($expenses as $expense) {
            if ($expense->attachment_path) {
                $path = $expense->attachment_path;

                // If the attachment is a locally uploaded file in the public disk
                if (str_starts_with($path, 'transactions/')) {
                    try {
                        $content = Storage::disk('public')->get($path);
                        // Determine extension from path (default to pdf)
                        $ext = pathinfo($path, PATHINFO_EXTENSION) ?: 'pdf';
                        $fileName = 'Expenses/' . $expense->name . '.' . $ext;
                        $zip->addFromString($fileName, $content);
                        continue; // Done with this expense
                    } catch (\Throwable $e) {
                        Log::warning('Failed to read local expense attachment, will try Drive', [
                            'path' => $path,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }

                // If it's a Drive link (or any URL), try Drive downloader
                $result = $this->downloadGoogleDriveFile($path);
                if (is_string($result) && $result !== 'Document not accessible' && $result !== 'Document download failed' && $result !== 'Service unavailable') {
                    $fileName = 'Expenses/' . $expense->name . '.pdf';
                    $zip->addFromString($fileName, $result);
                } else {
                    // Fallback to text file with link if download fails
                    $fileNameTxt = 'Expenses/' . $expense->name . '_LINK.txt';
                    $content = $this->createDocumentLinkFile($expense->name, $path, 'Expense', $result);
                    $zip->addFromString($fileNameTxt, $content);
                }
            }
        }

        $zip->close();

        // Return the zip file
        return response()->download($zipPath, $zipFileName)->deleteFileAfterSend();
    }

    private function downloadGoogleDriveFile($googleDriveLink)
    {
        try {
            // Extract file ID from Google Drive URL
            $fileId = $this->extractFileIdFromUrl($googleDriveLink);
            if (!$fileId) {
                return 'Document not available';
            }

            // Initialize Google Drive service
            $client = new Client();
            $client->setAuthConfig(storage_path('app/google-drive/laraveldriveintegration-af9e6ab2e69d.json'));
            $client->addScope(Drive::DRIVE);
            $client->setSubject('mga.operation@medguarda.com'); // Set the service account to impersonate
            $service = new Drive($client);

            // First, try to get file metadata to check access
            try {
                $file = $service->files->get($fileId, [
                    'fields' => 'id,name,mimeType',
                    'supportsAllDrives' => true
                ]);
                Log::info('File metadata accessed successfully', [
                    'fileId' => $fileId,
                    'fileName' => $file->getName(),
                    'mimeType' => $file->getMimeType()
                ]);
            } catch (\Exception $e) {
                Log::warning('Cannot access file metadata', [
                    'fileId' => $fileId,
                    'error' => $e->getMessage()
                ]);
                return 'Document not accessible: ' . $e->getMessage();
            }

            // Download file content with proper error handling
            try {
                // First, get file metadata to check if it's accessible
                $file = $service->files->get($fileId, [
                    'fields' => 'id,name,mimeType,size',
                    'supportsAllDrives' => true
                ]);

                Log::info('File metadata retrieved', [
                    'fileId' => $fileId,
                    'fileName' => $file->getName(),
                    'mimeType' => $file->getMimeType(),
                    'size' => $file->getSize()
                ]);

                // Now download the actual file content
                $response = $service->files->get($fileId, [
                    'alt' => 'media',
                    'supportsAllDrives' => true
                ]);

                // The response should be the raw file content
                $content = (string) $response;
                
                // Verify we got actual content and it looks like a PDF
                if (empty($content) || strlen($content) < 100) {
                    Log::warning('Downloaded content seems too small', [
                        'fileId' => $fileId,
                        'contentLength' => strlen($content)
                    ]);
                    return 'Document download failed: Content too small';
                }

                // Check if it looks like a PDF (should start with %PDF)
                if (substr($content, 0, 4) !== '%PDF') {
                    Log::warning('Downloaded content does not appear to be a PDF', [
                        'fileId' => $fileId,
                        'contentStart' => substr($content, 0, 50)
                    ]);
                    return 'Document download failed: Not a valid PDF file';
                }

                return $content;
            } catch (\Exception $e) {
                Log::error('Failed to download file content', [
                    'fileId' => $fileId,
                    'error' => $e->getMessage()
                ]);
                return 'Document download failed';
            }
        } catch (\Exception $e) {
            Log::error('Failed to initialize Google Drive service', [
                'url' => $googleDriveLink,
                'error' => $e->getMessage()
            ]);
            return 'Service unavailable';
        }
    }

    private function extractFileIdFromUrl($url)
    {
        // Handle different Google Drive URL formats
        if (preg_match('/\/file\/d\/([a-zA-Z0-9_-]+)/', $url, $matches)) {
            return $matches[1];
        }
        
        if (preg_match('/id=([a-zA-Z0-9_-]+)/', $url, $matches)) {
            return $matches[1];
        }
        
        return null;
    }

    private function createDocumentLinkFile($documentName, $googleDriveLink, $documentType, $errorMessage = null)
    {
        $content = "Document Information\n";
        $content .= "===================\n\n";
        $content .= "Document Name: {$documentName}\n";
        $content .= "Document Type: {$documentType}\n";
        $content .= "Google Drive Link: {$googleDriveLink}\n\n";
        
        if ($errorMessage) {
            $content .= "Download Error: {$errorMessage}\n\n";
        }
        
        $content .= "Instructions:\n";
        $content .= "1. Click on the Google Drive link above to access the document\n";
        $content .= "2. The document will open in your browser\n";
        $content .= "3. You can download or view the document directly from Google Drive\n\n";
        $content .= "Note: This file contains a link to the actual document stored in Google Drive.\n";
        $content .= "The document is not embedded in this zip file due to: {$errorMessage}\n";
        
        return $content;
    }
} 
