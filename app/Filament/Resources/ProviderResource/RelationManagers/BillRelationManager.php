<?php

namespace App\Filament\Resources\ProviderResource\RelationManagers;

use App\Filament\Support\BillTable;
use App\Filament\Support\ContactProviderCommunications;
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
                BillTable::statusFilter(),
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
                    ContactProviderCommunications::makeOutstandingBillsBulkAction(
                        fn () => $this->getOwnerRecord(),
                        fn () => null,
                    ),
                    ContactProviderCommunications::makeMissingBillsBulkAction(
                        fn () => $this->getOwnerRecord(),
                        fn () => null,
                    ),
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
}
