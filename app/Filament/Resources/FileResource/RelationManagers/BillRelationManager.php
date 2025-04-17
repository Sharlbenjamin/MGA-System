<?php

namespace App\Filament\Resources\FileResource\RelationManagers;

use App\Filament\Resources\BillResource;
use App\Filament\Resources\FileResource;
use App\Filament\Resources\FileResource\Pages;
use App\Filament\Resources\InvoiceResource;
use App\Models\Country;
use App\Models\File;
use App\Models\Invoice;
use App\Models\Patient;
use Filament\Tables\Actions\Action;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Actions\CreateAction;
use Filament\Tables\Table;
use Filament\Tables\Filters\SelectFilter;

class BillRelationManager extends RelationManager
{
    protected static string $relationship = 'bills';

    protected static ?string $model = Invoice::class;


    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('status')->sortable()->searchable()->badge() ->badge()
                ->color(fn (string $state): string => match ($state) {
                    'Paid' => 'success',
                    'Unpaid' => 'warning',
                    'Partial' => 'gray',
                }),
                Tables\Columns\TextColumn::make('due_date')->sortable()->searchable()->date(),
                Tables\Columns\TextColumn::make('final_total')->sortable()->searchable()->money('EUR'),
                Tables\Columns\TextColumn::make('remaining_amount')->sortable()->searchable()->money('EUR'),

            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'Unpaid' => 'Unpaid',
                        'Partial' => 'Partial',
                        'Paid' => 'Paid',
                    ]),
                    // due date filter when true fetch invoices with due date before today
            ])->actions([
                Action::make('editBill')
                    ->url(fn ($record) => BillResource::getUrl('edit', [
                        'record' => $record->id
                    ])),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ])->headerActions([
                Action::make('createBill')->label('Create Bill')
                    ->openUrlInNewTab(false)
                    ->url(fn () => BillResource::getUrl('create', [
                        'file_id' => $this->ownerRecord->id
                    ])),
            ]);
    }

}
