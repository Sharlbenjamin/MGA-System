<?php

namespace App\Filament\Resources\ProviderResource\RelationManagers;

use App\Models\City;
use App\Models\Provider;
use App\Models\Province;
use App\Models\ServiceType;
use Filament\Forms;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use App\Models\ProviderBranch;

class ProviderBranchRelationManager extends RelationManager
{
    protected static string $relationship = 'Branches';

    public static function query(Builder $query): Builder
    {
        return $query->where('provider_id', static::getOwnerRecord()->id)
                    ->with(['cities', 'branchServices.serviceType']);
    }
    public function form(Forms\Form $form): Forms\Form
{
    return $form
        ->schema([
            TextInput::make('branch_name')->label('Branch Name')->required()->maxLength(255),
            Select::make('cities')
                    ->label('Branch Cities')
                    ->multiple()
                    ->options(function (callable $get) {
                        $providerId = $this->getOwnerRecord()->id;
                        if (!$providerId) return [];

                        return City::query()
                            ->whereIn('country_id', function($query) use ($providerId) {
                                $query->select('country_id')
                                    ->from('providers')
                                    ->where('id', $providerId);
                            })
                            ->orderBy('name')
                            ->pluck('name', 'id');
                    })
                    ->searchable()
                    ->preload()
                    ->relationship('cities', 'name', function (Builder $query, callable $get) {
                        $providerId = $this->getOwnerRecord()->id;
                        if ($providerId) {
                            $query->whereIn('country_id', function($subquery) use ($providerId) {
                                $subquery->select('country_id')
                                    ->from('providers')
                                    ->where('id', $providerId);
                            });
                        }
                    }),
            Select::make('province')->label('Province')->options(fn ($get) => Province::where('country_id', Provider::where('id', $this->getOwnerRecord()->id)->value('country_id'))->pluck('name', 'id'))->searchable()->reactive(),
            Select::make('status')->label('Status')->options(['Active' => 'Active','Hold' => 'Hold',])->required(),
            Select::make('priority')->label('Priority')->options([
                    '1' => '1', '2' => '2', '3' => '3', '4' => '4', '5' => '5',
                    '6' => '6', '7' => '7', '8' => '8', '9' => '9', '10' => '10',
                ])->required(),



            Section::make('Medical Services')
                ->schema([
                    Toggle::make('emergency')->label('Emergency')->inline(),
                    Toggle::make('pediatrician_emergency')->label('Pediatrician Emergency')->inline(),
                    Toggle::make('dental')->label('Dental')->inline(),
                    Toggle::make('pediatrician')->label('Pediatrician')->inline(),
                    Toggle::make('gynecology')->label('Gynecology')->inline(),
                    Toggle::make('urology')->label('Urology')->inline(),
                    Toggle::make('cardiology')->label('Cardiology')->inline(),
                    Toggle::make('ophthalmology')->label('Ophthalmology')->inline(),
                    Toggle::make('trauma_orthopedics')->label('Trauma / Orthopedics')->inline(),
                    Toggle::make('surgery')->label('Surgery')->inline(),
                    Toggle::make('intensive_care')->label('Intensive Care')->inline(),
                    Toggle::make('obstetrics_delivery')->label('Obstetrics / Delivery')->inline(),
                    Toggle::make('hyperbaric_chamber')->label('Hyperbaric Chamber')->inline(),
                ])


        ]);
}

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('Branches')
            ->columns([
                TextColumn::make('branch_name')
                    ->label('Branch Name')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('branchServices.serviceType.name')
                    ->label('Services')
                    ->listWithLineBreaks()
                    ->bulleted()
                    ->limitList(3)
                    ->expandableLimitedList(),

                TextColumn::make('communication_method')
                    ->label('Contact Method')
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make(),
            ])
            ->actions([
                Tables\Actions\Action::make('Edit')
                    ->url(fn (ProviderBranch $record): string => 
                        \App\Filament\Resources\ProviderBranchResource::getUrl('edit', ['record' => $record])
                    )
                    ->icon('heroicon-o-pencil')
                    ->color('primary'),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
}
