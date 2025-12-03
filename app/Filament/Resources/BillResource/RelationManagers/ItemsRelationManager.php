<?php

namespace App\Filament\Resources\BillResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Components\TextInput;
use Filament\Support\RawJs;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Table;
use Filament\Tables;
use App\Models\BranchService;
use App\Models\ServiceType;
use App\Models\BillItem;

class ItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'items';
    protected static ?string $recordTitleAttribute = 'description';
    protected static ?string $model = BillItem::class;

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('service_selector')
                    ->label('Select Service')
                    ->options(function () {
                        $bill = $this->getOwnerRecord();
                        if (!$bill || !$bill->branch_id) {
                            return ['custom' => 'Custom Item'];
                        }

                        $services = \App\Models\ProviderBranch::find($bill->branch_id)
                            ->services()
                            ->get()
                            ->mapWithKeys(function ($serviceType) {
                                $serviceName = $serviceType->name;
                                $cost = $serviceType->pivot->min_cost ?? 0;
                                return ["service_{$serviceType->id}" => "{$serviceName} - €{$cost}"];
                            });

                        // Add custom option
                        $services->put('custom', 'Custom Item');
                        
                        return $services;
                    })
                    ->searchable()
                    ->preload()
                    ->reactive()
                    ->visibleOn('create')
                    ->afterStateUpdated(function ($state, $set, $get) {
                        if ($state && $state !== 'custom' && str_starts_with($state, 'service_')) {
                            $bill = $this->getOwnerRecord();
                            $branch = \App\Models\ProviderBranch::find($bill->branch_id);
                            if ($branch) {
                                // Extract service type ID from the state
                                $serviceTypeId = str_replace('service_', '', $state);
                                $serviceType = $branch->services()->where('service_types.id', $serviceTypeId)->first();
                                if ($serviceType) {
                                    $serviceName = $serviceType->name;
                                    $serviceDate = $bill->file->service_date ?? now();
                                    $description = "{$serviceName} on {$serviceDate->format('d/m/Y')}";
                                    
                                    $set('description', $description);
                                    $set('amount', $serviceType->pivot->min_cost ?? 0);
                                }
                            }
                        } else {
                            // Clear fields when custom is selected
                            $set('description', '');
                            $set('amount', '');
                        }
                    }),

                Forms\Components\TextInput::make('description')
                    ->required()
                    ->maxLength(255)
                    ->disabled(fn ($get) => 
                        $get('service_selector') 
                        && $get('service_selector') !== 'custom'
                    ),

                Forms\Components\TextInput::make('amount')
                    ->required()
                    ->numeric()
                    ->inputMode('decimal')
                    ->step('0.01')
                    ->prefix('€')
                    ->disabled(fn ($get) => 
                        $get('service_selector') 
                        && $get('service_selector') !== 'custom'
                    ),

                Forms\Components\TextInput::make('discount')
                    ->numeric()
                    ->inputMode('decimal')
                    ->step('0.01')
                    ->prefix('€')
                    ->default('0'),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('description'),
                Tables\Columns\TextColumn::make('amount')
                    ->money('EUR'),
                Tables\Columns\TextColumn::make('discount')
                    ->money('EUR'),
                Tables\Columns\TextColumn::make('tax')
                    ->money('EUR'),
                Tables\Columns\TextColumn::make('total')
                    ->money('EUR'),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->using(function (array $data) {
                        // If a service was selected, ensure description and amount are set
                        if (isset($data['service_selector']) && $data['service_selector'] !== 'custom' && str_starts_with($data['service_selector'], 'service_')) {
                            $bill = $this->getOwnerRecord();
                            $branch = \App\Models\ProviderBranch::find($bill->branch_id);
                            if ($branch) {
                                $serviceTypeId = str_replace('service_', '', $data['service_selector']);
                                $serviceType = $branch->services()->where('service_types.id', $serviceTypeId)->first();
                                if ($serviceType) {
                                    $serviceName = $serviceType->name;
                                    $serviceDate = $bill->file->service_date ?? now();
                                    $data['description'] = "{$serviceName} on {$serviceDate->format('d/m/Y')}";
                                    $data['amount'] = $serviceType->pivot->min_cost ?? 0;
                                }
                            }
                        }
                        
                        // Remove the service_selector field as it's not part of the model
                        unset($data['service_selector']);
                        
                        return $this->getRelationship()->create($data);
                    })
                    ->after(function ($record) {
                        $record->bill->calculateTotal();
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->mutateFormDataUsing(function (array $data): array {
                        // Remove service_selector if it exists (shouldn't be in edit mode, but just in case)
                        unset($data['service_selector']);
                        return $data;
                    })
                    ->after(function ($record) {
                        $record->bill->calculateTotal();
                    }),
                Tables\Actions\DeleteAction::make()
                    ->after(function ($record) {
                        $record->bill->calculateTotal();
                    }),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make()
                    ->after(function () {
                        $this->getOwnerRecord()->calculateTotal();
                    }),
            ]);
    }
}
