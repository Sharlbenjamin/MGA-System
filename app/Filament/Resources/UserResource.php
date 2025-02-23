<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Filament\Resources\UserResource\RelationManagers;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Forms\Components\MultiSelect;
use Filament\Tables\Columns\TextColumn;

use Filament\Forms\Components\FileUpload;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
{
    return $form
        ->schema([
            TextInput::make('name')->required(),
            Forms\Components\TextInput::make('email')
                ->email()
                ->required()
                ->unique('users', 'email', ignoreRecord: true), // Ignore the current user's email

            TextInput::make('password')
                ->password()
                ->minLength(8)
                ->dehydrated(fn ($state) => !empty($state)) // ✅ Only save if a new password is entered
                ->nullable(),

            MultiSelect::make('teams')
                ->relationship('teams', 'name')
                ->label('Assign Teams'),
            
                Forms\Components\TextInput::make('smtp_username')
                ->label('SMTP Username')
                ->helperText('Enter your email SMTP username.'),
            
            Forms\Components\TextInput::make('smtp_password')
                ->label('SMTP Password')
                ->password()
                ->helperText('Enter your email SMTP password.'),
            
                TextInput::make('signature_image')
                ->type('file') // ✅ Standard file input (browser-based)
                ->label('Upload Signature')
                ->columnSpanFull()
                ->nullable() // ✅ Makes it mandatory
        ]);
}

public static function table(Table $table): Table
{
    return $table
        ->columns([
            TextColumn::make('id')
                ->label('ID')
                ->sortable(),

            TextColumn::make('name')
                ->label('Name')
                ->searchable()
                ->sortable(),

            TextColumn::make('created_at')
                ->label('Created At')
                ->dateTime()
                ->sortable(),
        ])
        ->filters([
            // You can add filters here if needed
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
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }

    public static function getNavigationGroup(): ?string
    {
        return 'System Management';
    }
}
