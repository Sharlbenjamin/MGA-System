<?php

namespace App\Filament\Resources;

use App\Filament\Resources\GopResource\Pages;
use App\Models\Gop;
use Filament\Forms;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class GopResource extends Resource
{
    protected static ?string $model = Gop::class;

    protected static ?string $navigationGroup = null; // Removes it from any group
    protected static ?int $navigationSort = null; // Ensures it's not sorted
    protected static ?string $navigationIcon = null; // Hides from sidebar
    protected static bool $shouldRegisterNavigation = false; // Hides it completely

    public static function form(Forms\Form $form): Forms\Form
    {
        return $form
            ->schema([
                Select::make('type')->options(['In' => 'In', 'Out' => 'Out'])->required(),
                TextInput::make('amount')->numeric()->required(),
                DatePicker::make('date')->required(),
                Select::make('status')->options(['Not Sent' => 'Not Sent', 'Sent' => 'Sent', 'Updated' => 'Updated', 'Cancelled' => 'Cancelled'])->default('Not Sent')->required(),
                TextInput::make('gop_google_drive_link')->label('Google Drive Link')->nullable(),
            ]);
    }

    public static function table(Tables\Table $table): Tables\Table
    {
        return $table
            ->columns([
                TextColumn::make('type')->label('Type')->sortable(),
                TextColumn::make('amount')->label('Amount')->sortable(),
                TextColumn::make('date')->label('Date')->sortable(),
                TextColumn::make('status')->label('Status')->badge()->sortable(),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
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
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListGops::route('/'),
            'create' => Pages\CreateGop::route('/create'),
            'edit' => Pages\EditGop::route('/{record}/edit'),
        ];
    }
}
