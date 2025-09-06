<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;
use App\Models\Invoice;
use App\Models\Bill;
use Google\Client;
use Google\Service\Drive;

class TaxesExportController extends Controller
{
    public function export(Request $request)
    {
        $year = $request->get('year', Carbon::now()->year);
        $quarter = $request->get('quarter', '1');
        $includeCreatedAt = $request->get('include_created_at', false);
        $includeDueDate = $request->get('include_due_date', false);

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

        // Calculate totals - using the same logic as the view
        $invoiceTotal = Invoice::whereBetween('invoice_date', [$startDate, $endDate])->sum('total_amount');
        $billTotal = Bill::whereBetween('bill_date', [$startDate, $endDate])->sum('total_amount');
        $expenseTotal = DB::table('transactions')
            ->join('bank_accounts', 'transactions.bank_account_id', '=', 'bank_accounts.id')
            ->where('transactions.type', 'Expense')
            ->where('bank_accounts.type', 'Internal')
            ->whereBetween('transactions.date', [$startDate, $endDate])
            ->sum('transactions.amount');
        
        // Outflow is the sum of bills and expenses
        $outflowTotal = $billTotal + $expenseTotal;

        // Get filtered data - using the EXACT same query logic as the view
        $invoices = Invoice::query()
            ->whereBetween('invoice_date', [$startDate, $endDate])
            ->select([
                'id',
                'name as document_number',
                'total_amount',
                'created_at',
                'invoice_date as document_date',
                'status',
                'due_date',
                DB::raw("'invoice' as type"),
                DB::raw("name as invoice_number"),
                DB::raw("NULL as bill_number"),
                DB::raw("NULL as transaction_notes"),
                'invoice_google_link as google_drive_link'
            ]);

        $bills = Bill::query()
            ->whereBetween('bill_date', [$startDate, $endDate])
            ->select([
                'id',
                'name as document_number',
                'total_amount',
                'created_at',
                'bill_date as document_date',
                'status',
                'due_date',
                DB::raw("'bill' as type"),
                DB::raw("NULL as invoice_number"),
                DB::raw("name as bill_number"),
                DB::raw("NULL as transaction_notes"),
                'bill_google_link as google_drive_link'
            ]);

        $expenses = DB::table('transactions')
            ->join('bank_accounts', 'transactions.bank_account_id', '=', 'bank_accounts.id')
            ->where('transactions.type', 'Expense')
            ->where('bank_accounts.type', 'Internal')
            ->whereBetween('transactions.date', [$startDate, $endDate])
            ->select([
                'transactions.id',
                'transactions.name as document_number',
                'transactions.amount as total_amount',
                'transactions.created_at',
                'transactions.date as document_date',
                DB::raw("'Expense' as status"),
                DB::raw("NULL as due_date"),
                DB::raw("'expense' as type"),
                DB::raw("NULL as invoice_number"),
                DB::raw("NULL as bill_number"),
                'transactions.notes as transaction_notes',
                DB::raw("NULL as google_drive_link")
            ]);

        $data = $invoices->union($bills)->union($expenses)->get();

        // Prepare export data
        $exportData = [];
        foreach ($data as $record) {
            $row = [
                'Document Number' => $record->document_number,
                'Type' => ucfirst($record->type),
                'Amount' => $record->total_amount, // Remove currency formatting for Excel
                'Status' => $record->status,
                'Document Date' => $record->document_date,
                'Notes' => $record->transaction_notes ?? '',
                'Google Drive Link' => $record->google_drive_link ?? '',
            ];
            
            // Add optional columns based on user choice
            if ($includeCreatedAt) {
                $row['Created Date'] = $record->created_at;
            }
            
            if ($includeDueDate) {
                $row['Due Date'] = $record->due_date;
            }
            
            $exportData[] = $row;
        }

        // Add summary rows
        $exportData[] = []; // Empty row
        $exportData[] = ['SUMMARY', '', '', '', '', '', ''];
        $exportData[] = ['Invoice Total', '', $invoiceTotal, '', '', '', ''];
        $exportData[] = ['Bill Total', '', $billTotal, '', '', '', ''];
        $exportData[] = ['Expense Total', '', $expenseTotal, '', '', '', ''];
        $exportData[] = ['Outflow Total', '', $outflowTotal, '', '', '', ''];
        $exportData[] = ['Net Total', '', $invoiceTotal - $outflowTotal, '', '', '', ''];
        $exportData[] = ['Expected Tax (25%)', '', ($invoiceTotal - $outflowTotal) * 0.25, '', '', '', ''];

        // Generate filename
        $filename = "taxes_report_{$year}_Q{$quarter}_" . now()->format('Y-m-d_H-i-s') . '.csv';
        
        // Create CSV content
        $csv = '';
        if (!empty($exportData)) {
            $csv .= implode(',', array_keys($exportData[0])) . "\n";
        }
        foreach ($exportData as $row) {
            $csvRow = [];
            foreach ($row as $value) {
                // Handle null values and special characters
                $value = $value ?? '';
                $value = str_replace('"', '""', $value);
                $csvRow[] = '"' . $value . '"';
            }
            $csv .= implode(',', $csvRow) . "\n";
        }
        
        return response($csv)
            ->header('Content-Type', 'text/csv')
            ->header('Content-Disposition', 'attachment; filename="' . $filename . '"')
            ->header('Cache-Control', 'must-revalidate, post-check=0, pre-check=0')
            ->header('Expires', '0');
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
