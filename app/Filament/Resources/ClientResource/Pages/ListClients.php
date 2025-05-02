<?php

namespace App\Filament\Resources\ClientResource\Pages;

use App\Filament\Resources\ClientResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Actions\Action;
use Livewire\Attributes\Reactive;

class ListClients extends ListRecords
{
    protected static string $resource = ClientResource::class;

    public string $viewMode = 'active';

    public function getTitle(): string
    {
        return $this->viewMode === 'active'
            ? 'Active Clients'
            : 'Potential Clients';
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('toggleView')
                ->label(fn () => $this->viewMode === 'active' ? 'Potential Clients' : 'Active Clients')
                ->action(function () {
                    $this->viewMode = $this->viewMode === 'active' ? 'crm' : 'active';
                    $this->resetTable();
                })
                ->color('success'),
            Actions\CreateAction::make(),
        ];
    }

    public function table(Table $table): Table
    {
        // Potential Clients View (shows non-active clients)
        if ($this->viewMode === 'crm') {
            return $table
                ->query(
                    ClientResource::getEloquentQuery()
                        ->whereNot('status', 'Active')
                )
                ->columns([
                    TextColumn::make('company_name')
                        ->searchable()
                        ->sortable()
                        ->label('Company Name'),
                    TextColumn::make('type')
                        ->badge()
                        ->color(fn (string $state): string => match ($state) {
                            'Assistance' => 'success',
                            'Insurance' => 'warning',
                            'Agency' => 'info',
                        }),
                    TextColumn::make('status')
                        ->badge()
                        ->color(fn (string $state): string => match ($state) {
                            'Searching' => 'danger',
                            'Interested' => 'warning',
                            'Sent' => 'success',
                            'Rejected' => 'gray',
                            'On Hold' => 'gray',
                            'Broker' => 'success',
                            'No Reply' => 'danger',
                        }),
                    TextColumn::make('leadsCount')
                        ->label('Leads'),
                    TextColumn::make('leadsLastContactDate')
                        ->label('Last Contact')
                        ->date('d-m-Y'),
                ])
                ->defaultSort('company_name', 'asc');
        }

        // Active Clients View (default - shows only active clients)
        return $table
            ->query(
                ClientResource::getEloquentQuery()
                    ->where('status', 'Active')
            )
            ->columns([
                TextColumn::make('company_name')
                    ->searchable()
                    ->sortable()
                    ->label('Company Name'),
                TextColumn::make('type')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'Assistance' => 'success',
                        'Insurance' => 'warning',
                        'Agency' => 'info',
                    }),
                TextColumn::make('filesCount')
                    ->label('Total Files')
                    ->counts('files'),
                TextColumn::make('filesAssistedCount')
                    ->label('Assisted Files')
                    ->sortable(),
                TextColumn::make('invoicesTotalNumber')
                    ->label('Total Invoices')
                    ->sortable(),
                TextColumn::make('invoicesTotal')
                    ->label('Total Amount')
                    ->money('eur')
                    ->sortable(),
                TextColumn::make('invoicesTotalPaid')
                    ->label('Paid Amount')
                    ->money('eur')
                    ->sortable(),
                TextColumn::make('invoicesTotalOutstanding')
                    ->label('Outstanding Amount')
                    ->money('eur')
                    ->sortable(),
                TextColumn::make('transactionsLastDate')
                    ->label('Last Transaction')
                    ->date('d-m-Y')
                    ->sortable(),
            ])
            ->defaultSort('company_name', 'asc');
    }
}
