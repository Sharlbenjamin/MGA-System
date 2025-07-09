<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
            if ($invoice->invoice_google_link) {
                $fileName = 'Invoices/' . $invoice->name . '.pdf';
                $zip->addFromString($fileName, $this->downloadGoogleDriveFile($invoice->invoice_google_link));
            }
        }

        // Add bills to zip
        foreach ($bills as $bill) {
            if ($bill->bill_google_link) {
                $fileName = 'Bills/' . $bill->name . '.pdf';
                $zip->addFromString($fileName, $this->downloadGoogleDriveFile($bill->bill_google_link));
            }
        }

        // Add expenses to zip
        foreach ($expenses as $expense) {
            if ($expense->attachment_path) {
                $fileName = 'Expenses/' . $expense->name . '.pdf';
                $zip->addFromString($fileName, $this->downloadGoogleDriveFile($expense->attachment_path));
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
            $service = new Drive($client);

            // Download file content
            $content = $service->files->get($fileId, [
                'alt' => 'media',
                'supportsAllDrives' => true
            ]);

            return $content->getBody()->getContents();
        } catch (\Exception $e) {
            \Log::error('Failed to download Google Drive file', [
                'url' => $googleDriveLink,
                'error' => $e->getMessage()
            ]);
            return 'Document not available';
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
} 