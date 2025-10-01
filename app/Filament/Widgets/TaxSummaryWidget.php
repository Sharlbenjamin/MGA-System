<?php

namespace App\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use App\Models\Invoice;
use App\Models\Bill;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use Livewire\Attributes\On;

class TaxSummaryWidget extends BaseWidget
{
    public static function shouldLoad(): bool
    {
        return Auth::user()?->roles->contains('name', 'admin') ?? false;
    }
    public ?string $selectedYear = null;
    public ?string $selectedQuarter = null;

    protected function getStats(): array
    {
        $year = $this->selectedYear ?? Carbon::now()->year;
        $quarter = $this->selectedQuarter ?? (string) Carbon::now()->quarter;

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

        $netTotal = $invoiceTotal - $outflowTotal;

        return [
            Stat::make('Invoice Total', number_format($invoiceTotal, 2) . ' €')
                ->description('Total invoices for the period')
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->color('success'),

            Stat::make('Bill Total', number_format($billTotal, 2) . ' €')
                ->description('Total bills for the period')
                ->descriptionIcon('heroicon-m-arrow-trending-down')
                ->color('warning'),

            Stat::make('Expense Total', number_format($expenseTotal, 2) . ' €')
                ->description('Total expenses for the period')
                ->descriptionIcon('heroicon-m-arrow-trending-down')
                ->color('danger'),

            Stat::make('Outflow Total', number_format($outflowTotal, 2) . ' €')
                ->description('Total outflows (Bills + Expenses)')
                ->descriptionIcon('heroicon-m-arrow-trending-down')
                ->color('danger'),

            Stat::make('Net Total', number_format($netTotal, 2) . ' €')
                ->description('Net income (Invoices - Outflows)')
                ->descriptionIcon('heroicon-m-calculator')
                ->color($netTotal >= 0 ? 'success' : 'danger'),

            Stat::make('Expected Tax (25%)', number_format($netTotal * 0.25, 2) . ' €')
                ->description('Tax liability at 25% rate')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color($netTotal >= 0 ? 'danger' : 'gray'),
        ];
    }

    #[On('tax-period-changed')]
    public function onTaxPeriodChanged($data)
    {
        $this->selectedYear = $data['year'];
        $this->selectedQuarter = $data['quarter'];
        $this->dispatch('$refresh');
    }
} 