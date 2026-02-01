<?php

namespace App\Filament\Pages;

use App\Models\Employee;
use App\Models\Shift;
use App\Models\ShiftSchedule;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;

class ShiftCalendar extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationGroup = 'HR';
    protected static ?int $navigationSort = 0;
    protected static ?string $navigationIcon = 'heroicon-o-calendar-days';
    protected static ?string $title = 'Shift Calendar';
    protected static string $view = 'filament.pages.shift-calendar';

    public string $viewMode = 'month';
    public string $currentDate;

    public ?array $assignFormData = [];
    public bool $showAssignModal = false;

    public function mount(): void
    {
        $this->currentDate = Carbon::now()->format('Y-m-d');
    }

    public function openAssignModal(?string $date = null): void
    {
        $this->assignFormData = [
            'scheduled_date' => $date ?? $this->currentDate,
            'employee_id' => null,
            'shift_id' => null,
            'location_type' => 'on_site',
            'notes' => null,
        ];
        $this->showAssignModal = true;
    }

    public function closeAssignModal(): void
    {
        $this->showAssignModal = false;
        $this->assignFormData = [];
    }

    public function getEmployeeOptions(): array
    {
        return Employee::query()
            ->where('status', 'active')
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    public function getShiftOptions(): array
    {
        return Shift::query()
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    public function getLocationTypeOptions(): array
    {
        return ShiftSchedule::locationTypes();
    }

    public function saveAssignment(): void
    {
        $data = $this->assignFormData;
        $employeeId = (int) ($data['employee_id'] ?? 0);
        $shiftId = (int) ($data['shift_id'] ?? 0);
        $scheduledDate = $data['scheduled_date'] ?? null;

        if (! $employeeId || ! $shiftId || ! $scheduledDate) {
            Notification::make()->title('Please fill all required fields.')->danger()->send();

            return;
        }

        $exists = ShiftSchedule::query()
            ->where('employee_id', $employeeId)
            ->where('scheduled_date', $scheduledDate)
            ->exists();

        if ($exists) {
            Notification::make()->title('This employee already has a shift on this date.')->danger()->send();

            return;
        }

        ShiftSchedule::create([
            'employee_id' => $employeeId,
            'shift_id' => $shiftId,
            'scheduled_date' => $scheduledDate,
            'location_type' => $data['location_type'] ?? 'on_site',
            'notes' => $data['notes'] ?? null,
        ]);

        Notification::make()->title('Shift assigned successfully.')->success()->send();
        $this->closeAssignModal();
    }

    protected function getFormStatePath(): ?string
    {
        return null;
    }

    public static function getNavigationLabel(): string
    {
        return 'Shift Calendar';
    }

    public function getTitle(): string|Htmlable
    {
        return 'Shift Calendar';
    }

    public function getHeading(): string|Htmlable
    {
        return 'Shift Calendar';
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('assign')
                ->label('Assign shift')
                ->icon('heroicon-o-plus')
                ->action(fn () => $this->openAssignModal()),
        ];
    }

    public function setViewMode(string $mode): void
    {
        $this->viewMode = $mode;
    }

    public function previousPeriod(): void
    {
        $date = Carbon::parse($this->currentDate);
        if ($this->viewMode === 'month') {
            $this->currentDate = $date->subMonth()->format('Y-m-d');
        } else {
            $this->currentDate = $date->subWeek()->format('Y-m-d');
        }
    }

    public function nextPeriod(): void
    {
        $date = Carbon::parse($this->currentDate);
        if ($this->viewMode === 'month') {
            $this->currentDate = $date->addMonth()->format('Y-m-d');
        } else {
            $this->currentDate = $date->addWeek()->format('Y-m-d');
        }
    }

    public function goToToday(): void
    {
        $this->currentDate = Carbon::now()->format('Y-m-d');
    }

    public function getPeriodLabel(): string
    {
        $date = Carbon::parse($this->currentDate);
        if ($this->viewMode === 'month') {
            return $date->format('F Y');
        }
        $start = $date->copy()->startOfWeek();
        $end = $date->copy()->endOfWeek();

        return $start->format('M j') . ' – ' . $end->format('M j, Y');
    }

    public function getCalendarDays(): array
    {
        $date = Carbon::parse($this->currentDate);
        if ($this->viewMode === 'month') {
            $start = $date->copy()->startOfMonth()->startOfWeek();
            $end = $date->copy()->endOfMonth()->endOfWeek();
        } else {
            $start = $date->copy()->startOfWeek();
            $end = $date->copy()->endOfWeek();
        }

        $schedules = ShiftSchedule::query()
            ->with(['employee', 'shift'])
            ->whereBetween('scheduled_date', [$start->format('Y-m-d'), $end->format('Y-m-d')])
            ->orderBy('scheduled_date')
            ->orderBy('shift_id')
            ->get()
            ->groupBy(fn (ShiftSchedule $s) => $s->scheduled_date->format('Y-m-d'));

        $days = [];
        $cursor = $start->copy();
        while ($cursor->lte($end)) {
            $key = $cursor->format('Y-m-d');
            $days[] = [
                'date' => $cursor->copy(),
                'isCurrentMonth' => $this->viewMode === 'week' || $cursor->month === $date->month,
                'isToday' => $cursor->isToday(),
                'schedules' => $schedules->get($key, collect())->values()->all(),
            ];
            $cursor->addDay();
        }

        return $days;
    }

    public function removeSchedule(int $id): void
    {
        ShiftSchedule::query()->where('id', $id)->delete();
        Notification::make()->title('Shift assignment removed.')->success()->send();
    }
}
