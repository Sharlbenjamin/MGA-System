<?php

namespace App\Filament\Resources\EmployeeResource\Pages;

use App\Filament\Resources\EmployeeResource;
use App\Models\Employee;
use App\Models\Salary;
use Carbon\Carbon;
use Filament\Actions;
use Filament\Forms;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Notifications\Notification;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Enums\MaxWidth;
use Illuminate\Database\Eloquent\Builder;

class ListEmployees extends ListRecords
{
    protected static string $resource = EmployeeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('enterSalaries')
                ->label('Enter salaries')
                ->icon('heroicon-o-banknotes')
                ->modalHeading('Enter monthly salaries')
                ->modalDescription('Update base salary and bonus for all active employees for the selected month.')
                ->modalWidth(MaxWidth::FiveExtraLarge)
                ->form($this->salaryEntryFormSchema())
                ->fillForm(fn (): array => $this->salaryModalInitialData())
                ->action(fn (array $data) => $this->saveSalaryEntries($data)),
            Actions\Action::make('salarySummary')
                ->label('Salary summary')
                ->icon('heroicon-o-calculator')
                ->modalHeading('Salary summary')
                ->modalDescription('Salary plus bonus total per active employee for the selected month.')
                ->modalWidth(MaxWidth::FourExtraLarge)
                ->form($this->salarySummaryFormSchema())
                ->fillForm(fn (): array => $this->salarySummaryInitialData())
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Close'),
            Actions\CreateAction::make(),
        ];
    }

    /**
     * @return array<string, Tab>
     */
    public function getTabs(): array
    {
        return [
            'all' => Tab::make('All'),
            'interviewing' => Tab::make('Interviewing')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', Employee::STATUS_INTERVIEWING)),
            'active' => Tab::make('Active')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', Employee::STATUS_ACTIVE)),
            'former' => Tab::make('Former')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', Employee::STATUS_FORMER)),
        ];
    }

    /**
     * @return array<int, Forms\Components\Component>
     */
    protected function salaryPeriodFields(): array
    {
        return [
            Forms\Components\Select::make('year')
                ->label('Year')
                ->options(collect(range(now()->year - 5, now()->year + 1))->mapWithKeys(fn (int $y) => [$y => (string) $y]))
                ->default(now()->year)
                ->required()
                ->live()
                ->afterStateUpdated(function ($state, Get $get, Set $set): void {
                    $this->refreshSalaryModalRows($set, (int) $state, (int) $get('month'), $get('summary_mode') === true);
                }),
            Forms\Components\Select::make('month')
                ->label('Month')
                ->options(collect(range(1, 12))->mapWithKeys(fn (int $m) => [$m => Carbon::createFromDate(2000, $m, 1)->format('F')]))
                ->default(now()->month)
                ->required()
                ->live()
                ->afterStateUpdated(function ($state, Get $get, Set $set): void {
                    $this->refreshSalaryModalRows($set, (int) $get('year'), (int) $state, $get('summary_mode') === true);
                }),
        ];
    }

    protected function refreshSalaryModalRows(Set $set, int $year, int $month, bool $summary): void
    {
        if ($summary) {
            $set('rows', $this->buildSalarySummaryRows($year, $month));

            return;
        }

        $set('rows', $this->buildSalaryEntryRows($year, $month));
    }

    /**
     * @return array<int, Forms\Components\Component>
     */
    protected function salaryEntryFormSchema(): array
    {
        return [
            Forms\Components\Hidden::make('summary_mode')->default(false),
            Forms\Components\Grid::make(2)->schema($this->salaryPeriodFields()),
            Forms\Components\Repeater::make('rows')
                ->label('Active employees')
                ->schema([
                    Forms\Components\Hidden::make('employee_id'),
                    Forms\Components\TextInput::make('employee_name')
                        ->label('Employee')
                        ->disabled()
                        ->dehydrated(false),
                    Forms\Components\TextInput::make('base_salary')
                        ->label('Salary')
                        ->numeric()
                        ->minValue(0)
                        ->required(),
                    Forms\Components\TextInput::make('bonus')
                        ->label('Bonus')
                        ->numeric()
                        ->default(0),
                ])
                ->columns(3)
                ->addable(false)
                ->deletable(false)
                ->reorderable(false),
        ];
    }

    /**
     * @return array<int, Forms\Components\Component>
     */
    protected function salarySummaryFormSchema(): array
    {
        return [
            Forms\Components\Hidden::make('summary_mode')->default(true),
            Forms\Components\Grid::make(2)->schema($this->salaryPeriodFields()),
            Forms\Components\Repeater::make('rows')
                ->label('Totals')
                ->schema([
                    Forms\Components\TextInput::make('employee_name')
                        ->label('Employee')
                        ->disabled()
                        ->dehydrated(false),
                    Forms\Components\TextInput::make('base_salary')
                        ->label('Salary')
                        ->disabled()
                        ->dehydrated(false),
                    Forms\Components\TextInput::make('bonus')
                        ->label('Bonus')
                        ->disabled()
                        ->dehydrated(false),
                    Forms\Components\TextInput::make('total')
                        ->label('Total')
                        ->disabled()
                        ->dehydrated(false),
                ])
                ->columns(4)
                ->addable(false)
                ->deletable(false)
                ->reorderable(false),
            Forms\Components\Placeholder::make('grand_total')
                ->label('Grand total (salary + bonus)')
                ->content(function (Get $get): string {
                    $sum = collect($get('rows') ?? [])->sum(function (array $row): float {
                        return (float) ($row['total'] ?? 0);
                    });

                    return number_format($sum, 2);
                }),
        ];
    }

    /**
     * @return array{year: int, month: int, summary_mode: bool, rows: array<int, array<string, mixed>>}
     */
    public function salaryModalInitialData(): array
    {
        $year = now()->year;
        $month = now()->month;

        return [
            'year' => $year,
            'month' => $month,
            'summary_mode' => false,
            'rows' => $this->buildSalaryEntryRows($year, $month),
        ];
    }

    /**
     * @return array{year: int, month: int, summary_mode: bool, rows: array<int, array<string, mixed>>}
     */
    public function salarySummaryInitialData(): array
    {
        $year = now()->year;
        $month = now()->month;

        return [
            'year' => $year,
            'month' => $month,
            'summary_mode' => true,
            'rows' => $this->buildSalarySummaryRows($year, $month),
        ];
    }

    /**
     * @return array<int, array{employee_id: int, employee_name: string, base_salary: float, bonus: float}>
     */
    public function buildSalaryEntryRows(int $year, int $month): array
    {
        $employees = Employee::query()
            ->where('status', Employee::STATUS_ACTIVE)
            ->orderBy('name')
            ->get();

        $salaryByEmployee = Salary::query()
            ->where('year', $year)
            ->where('month', $month)
            ->get()
            ->keyBy('employee_id');

        return $employees->map(function (Employee $employee) use ($salaryByEmployee): array {
            $salaryRecord = $salaryByEmployee->get($employee->id);
            $baseSalary = $salaryRecord
                ? (float) $salaryRecord->base_salary
                : (float) ($employee->basic_salary ?? 0);

            return [
                'employee_id' => $employee->id,
                'employee_name' => $employee->name,
                'base_salary' => $baseSalary,
                'bonus' => $salaryRecord ? (float) $salaryRecord->adjustments : 0.0,
            ];
        })->values()->all();
    }

    /**
     * @return array<int, array{employee_name: string, base_salary: float, bonus: float, total: float}>
     */
    public function buildSalarySummaryRows(int $year, int $month): array
    {
        return collect($this->buildSalaryEntryRows($year, $month))
            ->map(function (array $row): array {
                $salary = (float) $row['base_salary'];
                $bonus = (float) $row['bonus'];

                return [
                    'employee_name' => $row['employee_name'],
                    'base_salary' => $salary,
                    'bonus' => $bonus,
                    'total' => $salary + $bonus,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function saveSalaryEntries(array $data): void
    {
        $year = (int) $data['year'];
        $month = (int) $data['month'];

        foreach ($data['rows'] ?? [] as $row) {
            $employeeId = (int) ($row['employee_id'] ?? 0);
            if ($employeeId <= 0) {
                continue;
            }

            Employee::query()
                ->where('id', $employeeId)
                ->where('status', Employee::STATUS_ACTIVE)
                ->firstOrFail();

            $baseSalary = (float) ($row['base_salary'] ?? 0);
            $bonus = (float) ($row['bonus'] ?? 0);

            $salary = Salary::query()->firstOrNew([
                'employee_id' => $employeeId,
                'year' => $year,
                'month' => $month,
            ]);

            $deductions = $salary->exists ? (float) $salary->deductions : 0.0;
            $salary->base_salary = $baseSalary;
            $salary->adjustments = $bonus;
            $salary->deductions = $deductions;
            $salary->net_salary = $baseSalary + $bonus - $deductions;
            $salary->save();
        }

        Notification::make()
            ->title('Salaries saved')
            ->body(Carbon::createFromDate($year, $month, 1)->format('F Y'))
            ->success()
            ->send();
    }
}
