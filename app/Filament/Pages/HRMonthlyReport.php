<?php

namespace App\Filament\Pages;

use App\Models\Employee;
use App\Models\FileAssignment;
use App\Models\Salary;
use App\Models\ShiftSchedule;
use App\Models\Task;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
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

    /**
     * Months that have at least one salary record (created report months).
     *
     * @return array<int, array{year: int, month: int}>
     */
    public function getCreatedMonths(): array
    {
        $pairs = Salary::query()
            ->select('year', 'month')
            ->groupBy('year', 'month')
            ->orderBy('year', 'desc')
            ->orderBy('month', 'desc')
            ->get();

        return $pairs->map(fn ($row) => ['year' => (int) $row->year, 'month' => (int) $row->month])->values()->all();
    }

    /**
     * Next calendar month that has no salary records yet.
     *
     * @return array{year: int, month: int}|null
     */
    public function getNextMonthToCreate(): ?array
    {
        $created = $this->getCreatedMonths();
        $now = Carbon::now();
        $cursor = $now->copy()->startOfMonth();

        if (empty($created)) {
            return ['year' => $cursor->year, 'month' => $cursor->month];
        }

        $latest = Carbon::createFromDate($created[0]['year'], $created[0]['month'], 1);
        $next = $latest->copy()->addMonth();

        return ['year' => $next->year, 'month' => (int) $next->month];
    }

    public function addNewMonth(): void
    {
        $next = $this->getNextMonthToCreate();
        if (! $next) {
            Notification::make()->title('No new month to create')->warning()->send();
            return;
        }

        $employees = Employee::query()
            ->where('status', 'active')
            ->orderBy('name')
            ->get();

        $created = 0;
        foreach ($employees as $employee) {
            $exists = Salary::query()
                ->where('employee_id', $employee->id)
                ->where('year', $next['year'])
                ->where('month', $next['month'])
                ->exists();
            if ($exists) {
                continue;
            }
            $base = (float) ($employee->basic_salary ?? 0);
            Salary::query()->create([
                'employee_id' => $employee->id,
                'year' => $next['year'],
                'month' => $next['month'],
                'base_salary' => $base,
                'adjustments' => 0,
                'deductions' => 0,
                'net_salary' => $base,
            ]);
            $created++;
        }

        $this->year = $next['year'];
        $this->month = $next['month'];

        $label = Carbon::createFromDate($next['year'], $next['month'], 1)->format('F Y');
        Notification::make()
            ->title("Month {$label} created")
            ->body("Salary rows created for {$created} employee(s).")
            ->success()
            ->send();
    }

    public function selectMonth(int $year, int $month): void
    {
        $this->year = $year;
        $this->month = $month;
    }

    protected function getHeaderActions(): array
    {
        $next = $this->getNextMonthToCreate();
        return [
            Action::make('addNewMonth')
                ->label('Add new month')
                ->icon('heroicon-o-plus-circle')
                ->visible($next !== null)
                ->action(fn () => $this->addNewMonth()),
        ];
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
            $baseSalary = $salaryRecord ? (float) $salaryRecord->base_salary : (float) ($employee->basic_salary ?? 0);
            $bonus = $salaryRecord ? (float) $salaryRecord->adjustments : 0;
            $deductions = $salaryRecord ? (float) $salaryRecord->deductions : 0;

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
                'employee_id' => $employee->id,
                'employee_name' => $employee->name,
                'base_salary' => $baseSalary,
                'net_salary' => $netSalary,
                'bonus' => $bonus,
                'deductions' => $deductions,
                'days_scheduled' => $daysScheduled,
                'hours' => null, // No attendances table yet
                'cases' => $casesCount,
                'tasks' => $tasksCount,
            ];
        }

        return $rows;
    }

    /**
     * Update bonus and/or deductions for one employee's salary row and recalculate net salary.
     * Net salary = base_salary + adjustments (bonus) - deductions.
     */
    public function updateSalaryRow(int $employeeId, float $bonus, float $deductions): void
    {
        $employee = Employee::query()->where('id', $employeeId)->where('status', 'active')->firstOrFail();
        $baseSalary = (float) ($employee->basic_salary ?? 0);

        $salary = Salary::query()
            ->where('employee_id', $employeeId)
            ->where('year', $this->year)
            ->where('month', $this->month)
            ->first();

        if ($salary) {
            $salary->base_salary = $salary->base_salary ?: $baseSalary;
            $salary->adjustments = $bonus;
            $salary->deductions = $deductions;
            $salary->net_salary = $salary->base_salary + $bonus - $deductions;
            $salary->save();
        } else {
            Salary::query()->create([
                'employee_id' => $employeeId,
                'year' => $this->year,
                'month' => $this->month,
                'base_salary' => $baseSalary,
                'adjustments' => $bonus,
                'deductions' => $deductions,
                'net_salary' => $baseSalary + $bonus - $deductions,
            ]);
        }

        Notification::make()
            ->title('Saved')
            ->success()
            ->duration(2000)
            ->send();
    }
}
