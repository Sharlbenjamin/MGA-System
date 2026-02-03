<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\Salary;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\StreamedResponse;

class HRMonthlyReportExportController extends Controller
{
    /**
     * Export one table per month: Employee name, Bank account, Salary (net), National ID.
     * One extract for the requested month.
     */
    public function export(Request $request): StreamedResponse
    {
        if (! Auth::check() || ! Auth::user()?->roles?->contains('name', 'admin')) {
            abort(403, 'Unauthorized.');
        }

        $year = (int) $request->get('year', Carbon::now()->year);
        $month = (int) $request->get('month', Carbon::now()->month);

        $label = Carbon::createFromDate($year, $month, 1)->format('F_Y');
        $filename = "hr_monthly_report_{$label}.csv";

        return response()->streamDownload(
            function () use ($year, $month) {
                $handle = fopen('php://output', 'w');

                fputcsv($handle, ['Employee name', 'Bank account', 'Salary', 'National ID']);

                $employees = Employee::query()
                    ->where('status', 'active')
                    ->with('bankAccount')
                    ->orderBy('name')
                    ->get();

                $salaryByEmployee = Salary::query()
                    ->where('year', $year)
                    ->where('month', $month)
                    ->get()
                    ->keyBy('employee_id');

                foreach ($employees as $employee) {
                    $salaryRecord = $salaryByEmployee->get($employee->id);
                    $netSalary = $salaryRecord ? (float) $salaryRecord->net_salary : 0;

                    $bankAccount = $employee->bankAccount;
                    $bankDisplay = $bankAccount
                        ? ($bankAccount->iban ?: ($bankAccount->bank_name ?: '—'))
                        : '—';

                    fputcsv($handle, [
                        $employee->name ?? '',
                        $bankDisplay,
                        number_format($netSalary, 2, '.', ''),
                        $employee->national_id ?? '',
                    ]);
                }

                fclose($handle);
            },
            $filename,
            [
                'Content-Type' => 'text/csv',
                'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            ]
        );
    }
}
