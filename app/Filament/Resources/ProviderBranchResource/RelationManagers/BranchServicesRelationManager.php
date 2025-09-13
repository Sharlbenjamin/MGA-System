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
                        Select::make('service_type_id')
                            ->label('Service Type')
                            ->options(ServiceType::pluck('name', 'id'))
                            ->searchable()
                            ->required()
                            ->disabled(fn ($record) => $record !== null) // Disable when editing
                            ->rules([
                                function () {
                                    return function (string $attribute, $value, \Closure $fail) {
                                        $ownerRecord = $this->getOwnerRecord();
                                        $exists = $ownerRecord->services()->where('service_types.id', $value)->exists();
                                        
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
                Tables\Actions\CreateAction::make()
                    ->mutateFormDataUsing(function (array $data): array {
                        // For many-to-many relationships, we need to handle the pivot data differently
                        return $data;
                    })
                    ->using(function (array $data): \Illuminate\Database\Eloquent\Model {
                        $ownerRecord = $this->getOwnerRecord();
                        
                        // Get the service type
                        $serviceType = ServiceType::findOrFail($data['service_type_id']);
                        
                        // Attach the service type to the branch with pivot data
                        $ownerRecord->services()->attach($serviceType->id, [
                            'min_cost' => $data['min_cost'] ?? null,
                            'max_cost' => $data['max_cost'] ?? null,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                        
                        // Return the service type for the relation manager
                        return $serviceType;
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->mutateFormDataUsing(function (array $data, $record): array {
                        // Load pivot data when editing
                        if ($record) {
                            $ownerRecord = $this->getOwnerRecord();
                            $pivotData = $ownerRecord->services()->where('service_types.id', $record->id)->first()?->pivot;
                            
                            if ($pivotData) {
                                $data['min_cost'] = $pivotData->min_cost;
                                $data['max_cost'] = $pivotData->max_cost;
                            }
                        }
                        
                        return $data;
                    })
                    ->using(function (array $data, \Illuminate\Database\Eloquent\Model $record): \Illuminate\Database\Eloquent\Model {
                        $ownerRecord = $this->getOwnerRecord();
                        
                        // Update the pivot table data
                        $ownerRecord->services()->updateExistingPivot($record->id, [
                            'min_cost' => $data['min_cost'] ?? null,
                            'max_cost' => $data['max_cost'] ?? null,
                            'updated_at' => now(),
                        ]);
                        
                        return $record;
                    }),
                Tables\Actions\DeleteAction::make()
                    ->using(function (\Illuminate\Database\Eloquent\Model $record): void {
                        $ownerRecord = $this->getOwnerRecord();
                        $ownerRecord->services()->detach($record->id);
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
}
