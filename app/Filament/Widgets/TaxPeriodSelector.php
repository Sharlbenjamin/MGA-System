<?php

namespace App\Filament\Widgets;

use Filament\Widgets\Widget;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Section;
use Carbon\Carbon;

class TaxPeriodSelector extends Widget
{
    protected static string $view = 'filament.widgets.tax-period-selector';

    public ?string $selectedYear = null;
    public ?string $selectedQuarter = null;

    protected static ?string $heading = 'Tax Period Selection';

    public function mount(): void
    {
        $this->selectedYear = Carbon::now()->year;
        $this->selectedQuarter = (string) Carbon::now()->quarter;
    }

    public function getYearOptions(): array
    {
        $currentYear = Carbon::now()->year;
        $years = [];
        for ($i = $currentYear - 5; $i <= $currentYear + 5; $i++) {
            $years[$i] = $i;
        }
        return $years;
    }

    public function getQuarterOptions(): array
    {
        return [
            '1' => 'Q1 (January - March)',
            '2' => 'Q2 (April - June)',
            '3' => 'Q3 (July - September)',
            '4' => 'Q4 (October - December)',
            'full' => 'Full Year',
        ];
    }

    public function updatedSelectedYear()
    {
        $this->dispatch('tax-period-changed', [
            'year' => $this->selectedYear,
            'quarter' => $this->selectedQuarter,
        ]);
    }

    public function updatedSelectedQuarter()
    {
        $this->dispatch('tax-period-changed', [
            'year' => $this->selectedYear,
            'quarter' => $this->selectedQuarter,
        ]);
    }
} 