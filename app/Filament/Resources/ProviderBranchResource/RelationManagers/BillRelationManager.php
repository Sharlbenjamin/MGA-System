<?php

namespace App\Filament\Resources\ProviderBranchResource\RelationManagers;

use App\Models\City;
use App\Models\Client;
use App\Models\Country;
use App\Models\Patient;
use App\Models\Provider;
use App\Models\ProviderBranch;
use Filament\Forms;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Tables;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\DeleteAction;

class BillRelationManager extends RelationManager
{
    protected static string $relationship = 'bills';

    public function form(Forms\Form $form): Forms\Form
    {
        return $form->schema([
            TextInput::make('name')->label('Name'),
        ]);
    }


    public function table(Tables\Table $table): Tables\Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->sortable()->searchable(),
                TextColumn::make('total_amount')->sortable()->searchable()->money('eur'),
                TextColumn::make('paid_amount')->sortable()->searchable()->money('eur'),
                TextColumn::make('remaining_amount')->sortable()->searchable()->money('eur')->state(fn ($record) => $record->total_amount - $record->paid_amount),
                TextColumn::make('bill_date')->sortable()->searchable()->date('d-m-Y'),
                TextColumn::make('status')->sortable()->searchable()->badge()->color(fn ($state) => match ($state) {
                    'Paid' => 'success',
                    'Unpaid' => 'danger',
                    'Partial' => 'warning',
                    default => 'secondary',
                }),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')->options([
                    'Paid' => 'Paid',
                    'Unpaid' => 'Unpaid',
                    'Partial' => 'Partial',
                ]),
            ]) ->headerActions([
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