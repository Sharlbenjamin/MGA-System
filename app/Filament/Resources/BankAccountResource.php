<?php

namespace App\Filament\Resources;

use App\Filament\Resources\BankAccountResource\RelationManagers\TransactionRelationManager;
use App\Filament\Resources\BankAccountResource\Pages;
use App\Models\BankAccount;
use App\Models\Client;
use App\Models\Country;
use App\Models\File;
use App\Models\Patient;
use App\Models\Provider;
use App\Models\ProviderBranch;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class BankAccountResource extends Resource
{
    protected static ?string $model = BankAccount::class;
    protected static ?string $navigationIcon = 'heroicon-o-building-library';
    protected static ?string $navigationGroup = 'Finance';
    protected static ?int $navigationSort = 6;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('type')->options(['Client' => 'Client', 'Provider' => 'Provider', 'Branch' => 'Branch', 'File' => 'File', 'Internal' => 'Internal'])->reactive()->required()->searchable()->afterStateUpdated(function ($state, callable $set) { $set('client_id', null); $set('provider_id', null); $set('branch_id', null); $set('file_id', null); }),
                Forms\Components\Select::make('client_id')->label('Select Client')->options(fn () => Client::all()->pluck('company_name', 'id'))->searchable()->required()->visible(fn ($get) => $get('type') === 'Client'),
                Forms\Components\Select::make('provider_id')->label('Select Provider')->options(fn () => Provider::all()->pluck('name', 'id'))->searchable()->required()->visible(fn ($get) => $get('type') === 'Provider'),
                Forms\Components\Select::make('branch_id')->label('Select Branch')->options(fn () => ProviderBranch::all()->pluck('branch_name', 'id'))->searchable()->required()->visible(fn ($get) => $get('type') === 'Branch'),
                Forms\Components\Select::make('file_id')->label('Select File')->options(fn () => File::with('patient')->get()->mapWithKeys(fn ($file) => [$file->id => $file->mga_reference . ' - ' . ($file->patient?->name ?? 'Patient')]))->searchable()->required()->visible(fn ($get) => $get('type') === 'File'),
                Forms\Components\TextInput::make('beneficiary_name')->maxLength(255)->required(),
                Forms\Components\Select::make('country_id')->relationship('country', 'name')->searchable()->live()->afterStateUpdated(fn ($state, callable $set) =>$set('iban', '')),
                Forms\Components\TextInput::make('iban')->placeholder(fn (Forms\Get $get): string =>Country::find($get('country_id'))?->iso ?? '')->maxLength(255)->required(),
                Forms\Components\TextInput::make('swift')->label('SWIFT')->maxLength(255),
                Forms\Components\TextInput::make('bank_name')->maxLength(255),
                Forms\Components\Textarea::make('beneficiary_address')->maxLength(65535)->columnSpanFull(),
                Forms\Components\TextInput::make('balance')->prefix('€')->default(0),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('type')->searchable(),
                Tables\Columns\TextColumn::make('owner_name')->label('Owner')->searchable(),
                Tables\Columns\TextColumn::make('beneficiary_name')->label('Name')->searchable(),
                Tables\Columns\TextColumn::make('iban')->label('IBAN')->searchable(),
                Tables\Columns\TextColumn::make('swift')->label('SWIFT')->searchable(),
                Tables\Columns\TextColumn::make('balance')->money('EUR')->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('country')->relationship('country', 'name'),
                Tables\Filters\SelectFilter::make('type'),
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

    public static function getRelations(): array
    {
        return [
            TransactionRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBankAccounts::route('/'),
            'create' => Pages\CreateBankAccount::route('/create'),
            'edit' => Pages\EditBankAccount::route('/{record}/edit'),
        ];
    }
}