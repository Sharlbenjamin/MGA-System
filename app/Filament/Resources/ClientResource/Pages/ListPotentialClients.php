<?php

namespace App\Filament\Resources\ClientResource\Pages;

use App\Filament\Resources\ClientResource;
use App\Filament\Resources\LeadResource;
use App\Models\Client;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ListPotentialClients extends ListRecords
{
    protected static string $resource = ClientResource::class;

    public function getTitle(): string
    {
        return 'Potential Clients';
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
                    ->with(['country', 'leads'])
                    ->withCount('leads')
                    ->inStatusGroup(Client::STATUS_GROUP_POTENTIAL)
            )
            ->columns([
                TextColumn::make('company_name')
                    ->label('Client (Project)')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('country.name')
                    ->label('Country')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('status')
                    ->badge()
                    ->sortable()
                    ->color(fn (string $state): string => match (Client::normalizeStatus($state)) {
                        'searching' => 'danger',
                        'interested' => 'warning',
                        'sent' => 'success',
                        'broker' => 'success',
                        'no reply' => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('leads_count')
                    ->label('Leads (Tasks)')
                    ->sortable(),
                TextColumn::make('pipeline_progress')
                    ->label('Progress')
                    ->state(fn (Client $record): string => $record->leadPipelineProgressPercent().'%')
                    ->description(fn (Client $record): ?string => $record->mainLeadStatus())
                    ->sortable(query: function ($query, string $direction) {
                        $direction = strtolower($direction) === 'desc' ? 'desc' : 'asc';
                        $cases = [];
                        $bindings = [];

                        foreach (\App\Models\Lead::PIPELINE_STEPS as $index => $step) {
                            $cases[] = 'WHEN LOWER(leads.status) = ? THEN ?';
                            $bindings[] = strtolower($step);
                            $bindings[] = $index;
                        }

                        $caseSql = 'CASE '.implode(' ', $cases).' ELSE -1 END';

                        return $query->orderByRaw(
                            '(SELECT MAX('.$caseSql.') FROM leads WHERE leads.client_id = clients.id) '.$direction,
                            $bindings
                        );
                    }),
                TextColumn::make('main_lead')
                    ->label('Main Lead')
                    ->state(fn (Client $record): string => $record->mainLead()?->displayName() ?? '—')
                    ->description(fn (Client $record): ?string => $record->mainLead()?->status)
                    ->url(function (Client $record): ?string {
                        $lead = $record->mainLead();

                        return $lead ? LeadResource::getUrl('edit', ['record' => $lead]) : null;
                    }),
                TextColumn::make('main_lead_last_contact')
                    ->label('Last Contact')
                    ->state(fn (Client $record) => $record->mainLead()?->last_contact_date)
                    ->date('d-m-Y'),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options(Client::statusOptionsForGroup(Client::STATUS_GROUP_POTENTIAL)),
            ])
            ->actions([
                Tables\Actions\Action::make('linkedin')
                    ->label('LinkedIn')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->color('info')
                    ->url(fn (Client $record): ?string => $record->mainLead()?->linkedInUrl())
                    ->openUrlInNewTab()
                    ->visible(fn (Client $record): bool => filled($record->mainLead()?->linkedInUrl())),
                Tables\Actions\Action::make('Overview')
                    ->url(fn (Client $record) => ClientResource::getUrl('overview', ['record' => $record]))
                    ->color('success'),
            ])
            ->defaultSort('company_name', 'asc');
    }
}
