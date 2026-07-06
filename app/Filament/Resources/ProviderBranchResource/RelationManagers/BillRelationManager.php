<?php

namespace App\Filament\Resources\ProviderBranchResource\RelationManagers;

use App\Filament\Support\BillTable;
use Filament\Forms;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\EditAction;
use Illuminate\Database\Eloquent\Builder;

class BillRelationManager extends RelationManager
{
    protected static string $relationship = 'bills';

    public function form(Forms\Form $form): Forms\Form
    {
        return $form->schema([
            TextInput::make('name')
                ->label('Custom bill name')
                ->placeholder(fn ($record) => $record?->display_name),
        ]);
    }

    public function table(Tables\Table $table): Tables\Table
    {
        return BillTable::configureRecordTitle($table)
            ->modifyQueryUsing(fn (Builder $query) => $query->with(BillTable::eagerLoadRelations()))
            ->columns(BillTable::relationManagerColumns())
            ->filters([
                Tables\Filters\SelectFilter::make('status')->options([
                    'Paid' => 'Paid',
                    'Unpaid' => 'Unpaid',
                    'Partial' => 'Partial',
                ]),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make(),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ]);
    }
}
