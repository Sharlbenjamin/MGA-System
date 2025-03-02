<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Filament\Resources\UserResource\RelationManagers;
use Filament\Resources\RelationManagers\HasManyRelationManager;
use App\Filament\Resources\UserResource\RelationManagers\UserSignatureRelationManager;
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
use Filament\Navigation\NavigationItem;
use Filament\Forms\Components\FileUpload;
use Spatie\Permission\Models\Role;


class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationGroup = 'Admin';
protected static ?int $navigationSort = 1;
protected static ?string $navigationIcon = 'heroicon-o-user'; // 👤 Users Icon

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

            
                Forms\Components\Select::make('roles')
                ->label('User Role')
                ->multiple() // Allows selecting multiple roles
                ->options(Role::pluck('name', 'name')->toArray()) // Fetches roles from the database
                ->relationship('roles', 'name') // Connects it to Spatie roles
                ->preload()
                ->required(),
            
                Forms\Components\TextInput::make('smtp_username')
                ->label('SMTP Username')
                ->helperText('Enter your email SMTP username.'),
            
            Forms\Components\TextInput::make('smtp_password')
                ->label('SMTP Password')
                ->password()
                ->helperText('Enter your email SMTP password.'),
            
            FileUpload::make('signature_image')
                ->image()
                ->acceptedFileTypes(['image/png', 'image/jpg', 'image/jpeg']) // ✅ Restrict file types
                ->directory('signatures') // ✅ Saves in storage/app/public/signatures
                ->disk('public') // ✅ Ensures correct storage disk
                ->visibility('public') // ✅ Makes file accessible
                ->preserveFilenames()
                ->columnSpanFull()
                ->nullable(), // ✅ Makes it mandatory

                // User Signature
                Forms\Components\Section::make('Signature Details') // Add a section for the signature
                ->relationship('signature')
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->label('Name (in signature)')
                        ->nullable(),

                    Forms\Components\TextInput::make('job_title')
                        ->label('Job Title (in signature)')
                        ->nullable(),

                    Forms\Components\Select::make('department')
                        ->label('Department (in signature)')
                        ->options([
                            'Operation' => 'Operation',
                            'Financial' => 'Financial',
                            'Provider Network' => 'Provider Network',
                            'Client Network' => 'Client Network',
                        ])
                        ->nullable(),

                    Forms\Components\TextInput::make('work_phone')
                        ->label('Work Phone (in signature)')
                        ->nullable(),
                ]),
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
            UserSignatureRelationManager::class,
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

    public static function navigationItems(): array
{
    return [
        \Filament\Navigation\NavigationItem::make()
            ->label('Users')
            ->url(self::getUrl('index'))
            ->icon('heroicon-o-users')
            ->sort(6),
    ];
}


}
