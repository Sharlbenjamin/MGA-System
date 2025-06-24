<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Models\Invoice;
use App\Models\Bill;

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

        // Calculate totals
        $invoiceTotal = Invoice::whereBetween('invoice_date', [$startDate, $endDate])
            ->where('status', 'Paid')
            ->sum('total_amount');
        $billTotal = Bill::whereBetween('bill_date', [$startDate, $endDate])->sum('total_amount');
        $expenseTotal = DB::table('transactions')
            ->join('bank_accounts', 'transactions.bank_account_id', '=', 'bank_accounts.id')
            ->where('transactions.type', 'Expense')
            ->where('bank_accounts.type', 'Internal')
            ->whereBetween('transactions.date', [$startDate, $endDate])
            ->sum('transactions.amount');
        
        // Outflow is the sum of bills and expenses
        $outflowTotal = $billTotal + $expenseTotal;

        // Get filtered data
        $invoices = Invoice::query()
            ->whereBetween('invoice_date', [$startDate, $endDate])
            ->where('status', 'Paid')
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
                DB::raw("NULL as google_drive_link")
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
} 