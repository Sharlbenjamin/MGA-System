<?php

namespace App\Filament\Resources\ProviderResource\RelationManagers;

use App\Filament\Support\BillTable;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class BillRelationManager extends RelationManager
{
    protected static string $relationship = 'bills';

    protected static ?string $title = 'Bills';

    public function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public function table(Table $table): Table
    {
        return BillTable::configureRecordTitle($table)
            ->modifyQueryUsing(fn (Builder $query) => $query->with(BillTable::eagerLoadRelations()))
            ->columns(BillTable::relationManagerColumns())
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->multiple()
                    ->options([
                        'Paid' => 'Paid',
                        'Unpaid' => 'Unpaid',
                        'Partial' => 'Partial',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        $statuses = $data['values'] ?? [];

                        return $query->when(
                            filled($statuses),
                            fn (Builder $query): Builder => $query->whereIn('bills.status', $statuses),
                        );
                    }),
            ])
            ->headerActions([
                BillTable::exportAction(),
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
