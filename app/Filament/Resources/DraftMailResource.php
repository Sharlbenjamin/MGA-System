<?php

namespace App\Filament\Resources;

use App\Filament\Resources\DraftMailResource\Pages;
use App\Filament\Resources\DraftMailResource\RelationManagers;
use App\Models\DraftMail;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class DraftMailResource extends Resource
{
    protected static ?string $model = DraftMail::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    

    public static function form(Form $form): Form
    {
        $statusOptions = [
            'Introduction' => 'Introduction',
            'Introduction Sent' => 'Introduction Sent',
            'Reminder' => 'Reminder',
            'Reminder Sent' => 'Reminder Sent',
            'Presentation' => 'Presentation',
            'Presentation Sent' => 'Presentation Sent',
            'Price List' => 'Price List',
            'Price List Sent' => 'Price List Sent',
            'Contract' => 'Contract',
            'Contract Sent' => 'Contract Sent',
            'Interested' => 'Interested',
            'Error' => 'Error',
            'Partner' => 'Partner',
            'Rejected' => 'Rejected',
        ];

        return $form
            ->schema([
                Forms\Components\TextInput::make('mail_name')
                    ->required(),
                    Forms\Components\Textarea::make('body_mail')
                    ->required()
                    ->columnSpanFull()
                    ->helperText('Use {first_name} and {email} and {company} as placeholders for lead data.'),
                Forms\Components\Select::make('status')
                    ->options($statusOptions)
                    ->required()
                    ->label('Lead Status'),
                Forms\Components\Select::make('new_status')
                    ->options($statusOptions)
                    ->required()
                    ->label('New Status After Sending'),

                Forms\Components\Select::make('type')
                    ->label('Type')
                    ->options([
                    'Provider' => 'Provider',
                    'Client' => 'Client',])
                    ->required(),
            ]);
    }

    public static function table(Tables\Table $table): Tables\Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('mail_name'),
                Tables\Columns\TextColumn::make('status'),
                Tables\Columns\TextColumn::make('new_status'),
                Tables\Columns\TextColumn::make('type'),
            ])
            ->filters([])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
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
            'index' => Pages\ListDraftMails::route('/'),
            'create' => Pages\CreateDraftMail::route('/create'),
            'edit' => Pages\EditDraftMail::route('/{record}/edit'),
        ];
    }

    public static function getNavigationGroup(): ?string
    {
        return 'System Management';
    }
}
