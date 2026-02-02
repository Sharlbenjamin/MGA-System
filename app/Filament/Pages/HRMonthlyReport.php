<?php

namespace App\Filament\Pages;

use App\Models\Employee;
use App\Models\FileAssignment;
use App\Models\Salary;
use App\Models\ShiftSchedule;
use App\Models\Task;
use Carbon\Carbon;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Auth;

class HRMonthlyReport extends Page
{
    protected static ?string $navigationGroup = 'HR';

    protected static ?int $navigationSort = 10;

    protected static ?string $navigationIcon = 'heroicon-o-document-chart-bar';

    protected static ?string $title = 'Monthly Report';

    protected static string $view = 'filament.pages.hr-monthly-report';

    public int $year;

    public int $month;

    public function mount(): void
    {
        $now = Carbon::now();
        $this->year = $now->year;
        $this->month = $now->month;
    }

    public static function getNavigationLabel(): string
    {
        return 'Monthly Report';
    }

    public function getTitle(): string|Htmlable
    {
        return 'HR Monthly Report';
    }

    public function getHeading(): string|Htmlable
    {
        return 'HR Monthly Report';
    }

    public static function canAccess(): bool
    {
        return Auth::check() && Auth::user()?->roles?->contains('name', 'admin');
    }

    public function getMonthLabel(): string
    {
        return Carbon::createFromDate($this->year, $this->month, 1)->format('F Y');
    }

    public function getReportRows(): array
    {
        $start = Carbon::createFromDate($this->year, $this->month, 1)->startOfDay();
        $end = $start->copy()->endOfMonth();

        $employees = Employee::query()
            ->where('status', 'active')
            ->orderBy('name')
            ->get();

        $salaryByEmployee = Salary::query()
            ->where('year', $this->year)
            ->where('month', $this->month)
            ->get()
            ->keyBy('employee_id');

        $rows = [];
        foreach ($employees as $employee) {
            $salaryRecord = $salaryByEmployee->get($employee->id);
            $netSalary = $salaryRecord ? (float) $salaryRecord->net_salary : 0;
            $bonus = 0; // No bonuses table yet

            $daysScheduled = ShiftSchedule::query()
                ->where('employee_id', $employee->id)
                ->whereBetween('scheduled_date', [$start->format('Y-m-d'), $end->format('Y-m-d')])
                ->count();

            $casesCount = 0;
            if ($employee->user_id) {
                $casesCount = FileAssignment::query()
                    ->where('user_id', $employee->user_id)
                    ->whereBetween('assigned_at', [$start, $end])
                    ->count();
            }

            $tasksCount = 0;
            if ($employee->user_id) {
                $tasksCount = Task::query()
                    ->where('user_id', $employee->user_id)
                    ->where('is_done', true)
                    ->whereBetween('updated_at', [$start, $end])
                    ->count();
            }

            $rows[] = [
                'employee' => $employee,
                'employee_name' => $employee->name,
                'net_salary' => $netSalary,
                'bonus' => $bonus,
                'days_scheduled' => $daysScheduled,
                'hours' => null, // No attendances table yet
                'cases' => $casesCount,
                'tasks' => $tasksCount,
            ];
        }

        return $rows;
    }
}
