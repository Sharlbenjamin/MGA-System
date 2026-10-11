<?php

namespace App\Filament\Resources\ShouldBePaidResource\Pages;

use App\Filament\Resources\ShouldBePaidResource;
use App\Filament\Widgets\UnpaidBillsSummary;
use Filament\Resources\Pages\ListRecords;
use Livewire\Attributes\On;

class ListShouldBePaid extends ListRecords
{
    protected static string $resource = ShouldBePaidResource::class;

    public function mount(): void
    {
        parent::mount();

        if (blank(data_get($this->tableFilters, 'payable_state.value'))) {
            $filters = $this->tableFilters ?? [];
            $filters['payable_state'] = ['value' => ShouldBePaidResource::STATE_PAYABLES];
            $this->tableFilters = $filters;
            $this->getTableFiltersForm()->fill($this->tableFilters);
        }
    }

    protected function getHeaderWidgets(): array
    {
        return [
            'payables-'.$this->getActivePayableState() => UnpaidBillsSummary::make([
                'activePayableState' => $this->getActivePayableState(),
            ]),
        ];
    }

    public function getTitle(): string
    {
        $label = ShouldBePaidResource::payableStates()[$this->getActivePayableState()];
        $count = $this->getTableQuery()->count();

        return "{$label} ({$count})";
    }

    #[On('apply-payable-state')]
    public function applyPayableState(string $state): void
    {
        if (! array_key_exists($state, ShouldBePaidResource::payableStates())) {
            $state = ShouldBePaidResource::STATE_PAYABLES;
        }

        if ($state === $this->getActivePayableState()) {
            $state = ShouldBePaidResource::STATE_PAYABLES;
        }

        $filters = $this->tableFilters ?? [];
        $filters['payable_state'] = ['value' => $state];
        $this->tableFilters = $filters;
        $this->getTableFiltersForm()->fill($this->tableFilters);
        $this->resetTable();
    }

    public function getActivePayableState(): string
    {
        $state = data_get($this->tableFilters, 'payable_state.value');

        if (! is_string($state) || ! array_key_exists($state, ShouldBePaidResource::payableStates())) {
            return ShouldBePaidResource::STATE_PAYABLES;
        }

        return $state;
    }
}
