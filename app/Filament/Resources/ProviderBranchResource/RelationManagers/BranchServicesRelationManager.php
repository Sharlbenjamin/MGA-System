<?php

namespace App\Filament\Resources\ProviderBranchResource\RelationManagers;

use App\Models\ServiceType;
use App\Filament\Resources\BranchServiceResource;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Grid;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\BadgeColumn;

class BranchServicesRelationManager extends RelationManager
{
    protected static string $relationship = 'services';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Service Information')
                    ->schema([
                        Select::make('id')
                            ->label('Service Type')
                            ->options(ServiceType::pluck('name', 'id'))
                            ->searchable()
                            ->required()
                            ->rules([
                                function () {
                                    return function (string $attribute, $value, \Closure $fail) {
                                        $ownerRecord = $this->getOwnerRecord();
                                        $exists = $ownerRecord->services()->where('service_type_id', $value)->exists();
                                        
                                        if ($exists) {
                                            $fail('This service type is already associated with this branch.');
                                        }
                                    };
                                },
                            ]),

                        Section::make('Cost Information')
                            ->schema([
                                Grid::make(2)
                                    ->schema([
                                        TextInput::make('min_cost')
                                            ->label('Minimum Cost')
                                            ->numeric()
                                            ->minValue(0)
                                            ->step(0.01)
                                            ->nullable(),

                                        TextInput::make('max_cost')
                                            ->label('Maximum Cost')
                                            ->numeric()
                                            ->minValue(0)
                                            ->step(0.01)
                                            ->nullable(),
                                    ]),
                            ]),
                    ]),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                TextColumn::make('name')
                    ->label('Service Type')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('pivot.min_cost')
                    ->label('Minimum Cost')
                    ->money('USD')
                    ->sortable(),

                TextColumn::make('pivot.max_cost')
                    ->label('Maximum Cost')
                    ->money('USD')
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
}
