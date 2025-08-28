<?php

namespace App\Filament\Resources\ProviderBranchResource\RelationManagers;

use App\Models\ServiceType;
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
    protected static string $relationship = 'branchServices';

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
                            ->unique(ignoreRecord: true, modifyRuleUsing: function ($rule) {
                                return $rule->where('provider_branch_id', $this->getOwnerRecord()->id);
                            }),

                        Section::make('Cost Information')
                            ->schema([
                                Grid::make(2)
                                    ->schema([
                                        TextInput::make('day_cost')
                                            ->label('Day Cost')
                                            ->numeric()
                                            ->minValue(0)
                                            ->step(0.01)
                                            ->nullable(),

                                        TextInput::make('night_cost')
                                            ->label('Night Cost')
                                            ->numeric()
                                            ->minValue(0)
                                            ->step(0.01)
                                            ->nullable(),

                                        TextInput::make('weekend_cost')
                                            ->label('Weekend Cost')
                                            ->numeric()
                                            ->minValue(0)
                                            ->step(0.01)
                                            ->nullable(),

                                        TextInput::make('weekend_night_cost')
                                            ->label('Weekend Night Cost')
                                            ->numeric()
                                            ->minValue(0)
                                            ->step(0.01)
                                            ->nullable(),
                                    ]),
                            ]),

                        Toggle::make('is_active')
                            ->label('Active')
                            ->default(true),
                    ]),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('serviceType.name')
            ->columns([
                TextColumn::make('serviceType.name')
                    ->label('Service Type')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('day_cost')
                    ->label('Day Cost')
                    ->money('USD')
                    ->sortable(),

                TextColumn::make('night_cost')
                    ->label('Night Cost')
                    ->money('USD')
                    ->sortable(),

                TextColumn::make('weekend_cost')
                    ->label('Weekend Cost')
                    ->money('USD')
                    ->sortable(),

                TextColumn::make('weekend_night_cost')
                    ->label('Weekend Night Cost')
                    ->money('USD')
                    ->sortable(),

                BadgeColumn::make('is_active')
                    ->label('Status')
                    ->colors([
                        'success' => true,
                        'danger' => false,
                    ])
                    ->formatStateUsing(fn (bool $state): string => $state ? 'Active' : 'Inactive')
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
                    ->url(fn ($record): string => 
                        \App\Filament\Resources\BranchServiceResource::getUrl('edit', ['record' => $record])
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
