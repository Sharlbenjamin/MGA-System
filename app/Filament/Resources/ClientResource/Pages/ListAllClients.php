<?php

namespace App\Filament\Resources\ClientResource\Pages;

use App\Filament\Resources\ClientResource;
use App\Models\Client;
use App\Models\Country;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ListAllClients extends ListRecords
{
    protected static string $resource = ClientResource::class;

    public function getTitle(): string
    {
        return 'All Clients';
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                ClientResource::getEloquentQuery()
                    ->with('country')
            )
            ->columns([
                TextColumn::make('company_name')
                    ->label('Client Name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('country.name')
                    ->label('Country')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('type')
                    ->badge()
                    ->sortable()
                    ->color(fn (string $state): string => match ($state) {
                        'Assistance' => 'success',
                        'Insurance' => 'warning',
                        'Agency' => 'info',
                        default => 'gray',
                    }),
                TextColumn::make('status')
                    ->badge()
                    ->sortable()
                    ->color(fn (string $state): string => match (Client::normalizeStatus($state)) {
                        'searching' => 'danger',
                        'interested' => 'warning',
                        'sent' => 'success',
                        'rejected' => 'gray',
                        'active' => 'success',
                        'on hold' => 'gray',
                        'closed' => 'gray',
                        'black list' => 'danger',
                        'broker' => 'success',
                        'no reply' => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('leadsCount')
                    ->label('Leads')
                    ->sortable(),
                TextColumn::make('files_count')
                    ->label('Files')
                    ->counts('files')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->multiple()
                    ->options(Client::statusOptions()),
                SelectFilter::make('country_id')
                    ->label('Country')
                    ->options(Country::pluck('name', 'id')),
                SelectFilter::make('type')
                    ->label('Type')
                    ->options([
                        'Assistance' => 'Assistance',
                        'Insurance' => 'Insurance',
                        'Agency' => 'Agency',
                    ]),
            ])
            ->actions([
                Tables\Actions\Action::make('Overview')
                    ->url(fn (Client $record) => ClientResource::getUrl('overview', ['record' => $record]))
                    ->color('success'),
            ])
            ->defaultSort('company_name', 'asc');
    }
}
