<?php

namespace App\Filament\Resources\ProviderResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use App\Models\Country;
use App\Models\Bill;
use Illuminate\Database\Eloquent\Builder;

class BillRelationManager extends RelationManager
{
    protected static string $relationship = 'bills';

    protected static ?string $title = 'Bills';

    protected static ?string $recordTitleAttribute = 'number';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
            ]);
    }

    protected function getTableQuery(): Builder
    {
        $tableName = (new Bill())->getTable();
        $query = parent::getTableQuery();
        
        // Ensure all columns are properly qualified to avoid ambiguity
        return $query->select($tableName . '.*');
    }

    public function table(Table $table): Table
    {
        $tableName = (new Bill())->getTable();
        
        return $table
            ->modifyQueryUsing(function (Builder $query) use ($tableName) {
                // Explicitly select bills table columns to avoid ambiguity
                return $query->select($tableName . '.*');
            })
            ->columns([
                Tables\Columns\TextColumn::make('name'),
                Tables\Columns\TextColumn::make('total_amount')->money('eur'),
                Tables\Columns\TextColumn::make('paid_amount')->money('eur'),
                Tables\Columns\TextColumn::make('remaining_amount')->state(function ($record) {return $record->total_amount - $record->paid_amount;})->money('eur'),
                Tables\Columns\TextColumn::make('status'),
                Tables\Columns\TextColumn::make('bill_date')->date('d/m/Y'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('bill_status')
                    ->label('Status')
                    ->options([
                        'Paid' => 'Paid',
                        'Unpaid' => 'Unpaid',
                    ])
                    ->query(function (Builder $query, array $data) use ($tableName): Builder {
                        if (!empty($data['value'])) {
                            // Use whereRaw to ensure the column is explicitly qualified
                            return $query->whereRaw("`{$tableName}`.`status` = ?", [$data['value']]);
                        }
                        return $query;
                    }),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->url(fn ($record) => \App\Filament\Resources\BillResource::getUrl('edit', ['record' => $record])),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
}
