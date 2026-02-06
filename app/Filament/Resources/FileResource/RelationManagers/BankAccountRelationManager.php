<?php

namespace App\Filament\Resources\FileResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use App\Models\Country;
use Illuminate\Database\Eloquent\Builder;

class BankAccountRelationManager extends RelationManager
{
    protected static string $relationship = 'bankAccounts';

    protected static ?string $title = 'Bk';

    protected static ?string $recordTitleAttribute = 'iban';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Hidden::make('type')->default('File'),
                Forms\Components\TextInput::make('beneficiary_name')->maxLength(255)->required(),
                Forms\Components\Select::make('country_id')->relationship('country', 'name')->searchable()->preload(false)->live()->afterStateUpdated(fn ($state, callable $set) => $set('iban', '')),
                Forms\Components\TextInput::make('iban')->placeholder(fn (Forms\Get $get): string => Country::find($get('country_id'))?->iso ?? '')->maxLength(255)->required(),
                Forms\Components\TextInput::make('swift')->label('SWIFT')->maxLength(255),
                Forms\Components\TextInput::make('bank_name')->maxLength(255),
                Forms\Components\Textarea::make('beneficiary_address')->maxLength(65535)->columnSpanFull(),
                Forms\Components\TextInput::make('balance')->numeric()->prefix('€')->default(0),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['country']))
            ->defaultPaginationPageOption(10)
            ->columns([
                Tables\Columns\TextColumn::make('beneficiary_name')->searchable(),
                Tables\Columns\TextColumn::make('iban')->label('IBAN')->searchable(),
                Tables\Columns\TextColumn::make('swift')->label('SWIFT')->searchable(),
                Tables\Columns\TextColumn::make('balance')->money('EUR')->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('country')->relationship('country', 'name'),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
}
