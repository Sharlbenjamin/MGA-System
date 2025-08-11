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

class ItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'items';
    protected static ?string $recordTitleAttribute = 'description';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('branch_service_id')
                    ->label('Select Service')
                    ->options(function () {
                        $bill = $this->getOwnerRecord();
                        if (!$bill || !$bill->branch_id) {
                            return ['custom' => 'Custom Item'];
                        }

                        $services = BranchService::where('provider_branch_id', $bill->branch_id)
                            ->where('is_active', true)
                            ->with('serviceType')
                            ->get()
                            ->mapWithKeys(function ($branchService) {
                                $serviceName = $branchService->serviceType->name;
                                $cost = $branchService->day_cost ?? 0;
                                return [$branchService->id => "{$serviceName} - €{$cost}"];
                            });

                        // Add custom option
                        $services->put('custom', 'Custom Item');
                        
                        return $services;
                    })
                    ->searchable()
                    ->preload()
                    ->reactive()
                    ->afterStateUpdated(function ($state, $set, $get) {
                        if ($state && $state !== 'custom') {
                            $branchService = BranchService::with('serviceType')->find($state);
                            if ($branchService) {
                                $bill = $this->getOwnerRecord();
                                $serviceName = $branchService->serviceType->name;
                                $serviceDate = $bill->file->service_date ?? now();
                                $description = "{$serviceName} on {$serviceDate->format('d/m/Y')}";
                                
                                $set('description', $description);
                                $set('amount', $branchService->day_cost ?? 0);
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
                    ->visible(fn ($get) => $get('branch_service_id') === 'custom'),

                Forms\Components\TextInput::make('amount')
                    ->required()
                    ->numeric()
                    ->inputMode('decimal')
                    ->step('0.01')
                    ->prefix('€')
                    ->visible(fn ($get) => $get('branch_service_id') === 'custom'),

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
                    ->after(function ($record) {
                        $record->bill->calculateTotal();
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
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
