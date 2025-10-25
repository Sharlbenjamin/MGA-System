<?php

namespace App\Filament\Resources\InvoiceResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Components\TextInput;
use Filament\Support\RawJs;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Table;
use Filament\Tables;
use App\Models\FileFee;
use App\Models\BillItem;

class ItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'items';
    protected static ?string $recordTitleAttribute = 'description';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('item_selector')
                    ->label('Select Item')
                    ->options(function () {
                        $invoice = $this->getOwnerRecord();
                        if (!$invoice || !$invoice->file_id) {
                            return ['custom' => 'Custom Item'];
                        }

                        $options = collect();

                        // Add bill items from the file's bills
                        $billItems = BillItem::whereHas('bill', function ($query) use ($invoice) {
                            $query->where('file_id', $invoice->file_id);
                        })->get();

                        foreach ($billItems as $billItem) {
                            $options->put(
                                "bill_item_{$billItem->id}", 
                                "Bill Item: {$billItem->description} - €{$billItem->amount}"
                            );
                        }

                        // Add file fees
                        $fileFees = FileFee::with('serviceType', 'country', 'city')->get();
                        
                        foreach ($fileFees as $fileFee) {
                            $serviceName = $fileFee->serviceType ? $fileFee->serviceType->name : 'Unknown Service';
                            $countryName = $fileFee->country ? $fileFee->country->name : '';
                            $cityName = $fileFee->city ? $fileFee->city->name : '';
                            
                            $location = trim("{$countryName} {$cityName}");
                            $label = $location ? "{$serviceName} ({$location}) - €{$fileFee->amount}" : "{$serviceName} - €{$fileFee->amount}";
                            
                            $options->put("file_fee_{$fileFee->id}", $label);
                        }

                        // Add custom option
                        $options->put('custom', 'Custom Item');
                        
                        return $options;
                    })
                    ->searchable()
                    ->preload()
                    ->reactive()
                    ->afterStateUpdated(function ($state, $set, $get) {
                        if ($state && $state !== 'custom') {
                            $invoice = $this->getOwnerRecord();
                            $serviceDate = $invoice->file->service_date ?? now();
                            $dateString = $serviceDate->format('d/m/Y');
                            
                            if (str_starts_with($state, 'bill_item_')) {
                                $billItemId = str_replace('bill_item_', '', $state);
                                $billItem = BillItem::find($billItemId);
                                if ($billItem) {
                                    $set('description', "{$billItem->description} on {$dateString}");
                                    // Don't auto-fill amount
                                }
                            } elseif (str_starts_with($state, 'file_fee_')) {
                                $fileFeeId = str_replace('file_fee_', '', $state);
                                $fileFee = FileFee::with('serviceType')->find($fileFeeId);
                                if ($fileFee) {
                                    $serviceName = $fileFee->serviceType ? $fileFee->serviceType->name : 'Unknown Service';
                                    $isTelemedicine = $fileFee->serviceType && strtolower($fileFee->serviceType->name) === 'telemedicine';
                                    $description = $isTelemedicine ? "{$serviceName} on {$dateString}" : "File Fee";
                                    $set('description', $description);
                                    // Don't auto-fill amount
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
                    ->maxLength(255),

                Forms\Components\TextInput::make('amount')
                    ->required()
                    ->numeric()
                    ->inputMode('decimal')
                    ->step('0.01')
                    ->prefix('€')
                    ->placeholder(function ($get) {
                        if ($get('item_selector') && $get('item_selector') !== 'custom') {
                            if (str_starts_with($get('item_selector'), 'bill_item_')) {
                                $billItemId = str_replace('bill_item_', '', $get('item_selector'));
                                $billItem = \App\Models\BillItem::find($billItemId);
                                return $billItem ? '€' . number_format($billItem->amount, 2) : null;
                            } elseif (str_starts_with($get('item_selector'), 'file_fee_')) {
                                $fileFeeId = str_replace('file_fee_', '', $get('item_selector'));
                                $fileFee = \App\Models\FileFee::find($fileFeeId);
                                return $fileFee ? '€' . number_format($fileFee->amount, 2) : null;
                            }
                        }
                        return null;
                    }),

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
                        // If an item was selected, ensure description is set with service date
                        if (isset($data['item_selector']) && $data['item_selector'] !== 'custom') {
                            $invoice = $this->getOwnerRecord();
                            $serviceDate = $invoice->file->service_date ?? now();
                            $dateString = $serviceDate->format('d/m/Y');
                            
                            if (str_starts_with($data['item_selector'], 'bill_item_')) {
                                $billItemId = str_replace('bill_item_', '', $data['item_selector']);
                                $billItem = BillItem::find($billItemId);
                                if ($billItem) {
                                    $data['description'] = "{$billItem->description}";
                                    // Don't auto-fill amount
                                }
                            } elseif (str_starts_with($data['item_selector'], 'file_fee_')) {
                                $fileFeeId = str_replace('file_fee_', '', $data['item_selector']);
                                $fileFee = FileFee::with('serviceType')->find($fileFeeId);
                                if ($fileFee) {
                                    $serviceName = $fileFee->serviceType ? $fileFee->serviceType->name : 'Unknown Service';
                                    $isTelemedicine = $fileFee->serviceType && strtolower($fileFee->serviceType->name) === 'telemedicine';
                                    $data['description'] = $isTelemedicine ? "{$serviceName} on {$dateString}" : "File Fee";
                                    // Don't auto-fill amount
                                }
                            }
                        }
                        
                        // Remove the item_selector field as it's not part of the model
                        unset($data['item_selector']);
                        
                        return $this->getRelationship()->create($data);
                    })
                    ->after(function ($record) {
                        $record->invoice->calculateTotal();
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->after(function ($record) {
                        $record->invoice->calculateTotal();
                    }),
                Tables\Actions\DeleteAction::make()
                    ->after(function ($record) {
                        $record->invoice->calculateTotal();
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
