<?php

namespace App\Filament\Resources\ProviderBranchResource\RelationManagers;

use App\Filament\Resources\BillResource;
use App\Filament\Support\BillTable;
use App\Filament\Support\ContactProviderCommunications;
use Filament\Forms;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Actions\Action;
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
            ->modifyQueryUsing(fn (Builder $query) => BillTable::forProviderBranch(
                $query,
                (int) $this->getOwnerRecord()->getKey(),
            ))
            ->columns(BillTable::relationManagerColumns())
            ->filters([
                BillTable::statusFilter(),
            ])
            ->headerActions([
                BillTable::exportAction(),
                Tables\Actions\CreateAction::make(),
            ])
            ->actions([
                EditAction::make()
                    ->label('Edit name')
                    ->modalHeading('Edit bill name')
                    ->icon('heroicon-o-pencil-square'),
                Action::make('editBill')
                    ->label('Edit bill')
                    ->icon('heroicon-o-pencil')
                    ->url(fn ($record) => BillResource::getUrl('edit', ['record' => $record])),
                DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    ContactProviderCommunications::makeOutstandingBillsBulkAction(
                        fn () => $this->getOwnerRecord()->provider,
                        fn () => $this->getOwnerRecord(),
                    ),
                    ContactProviderCommunications::makeMissingBillsBulkAction(
                        fn () => $this->getOwnerRecord()->provider,
                        fn () => $this->getOwnerRecord(),
                    ),
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
}
