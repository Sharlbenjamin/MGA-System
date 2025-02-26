<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TeamResource\Pages;
use App\Filament\Resources\TeamResource\RelationManagers;
use App\Models\Team;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Navigation\NavigationItem;
use Filament\Tables\Columns\TextColumn;

class TeamResource extends Resource
{
    protected static ?string $model = Team::class;

    protected static ?string $navigationGroup = null; // Removes it from any group
    protected static ?int $navigationSort = null; // Ensures it's not sorted
    protected static ?string $navigationIcon = null; // Hides from sidebar
    protected static bool $shouldRegisterNavigation = false; // Hides it completely

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('name')
                    ->required()
                    ->label('Team Name'),

                Select::make('user_id')
                    ->relationship('owner', 'name')
                    ->label('Team Owner')
                    ->required(),

                Toggle::make('personal_team')
                    ->label('Personal Team')
                    ->default(false),
            ]);
    }

    public static function table(Table $table): Table
{
    return $table
        ->columns([
            TextColumn::make('name')->label('Team Name')->sortable(),
            TextColumn::make('user.name')->label('Owner')->sortable(),
        ])
        ->filters([])
        ->actions([]);
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
            'index' => Pages\ListTeams::route('/'),
            'create' => Pages\CreateTeam::route('/create'),
            'edit' => Pages\EditTeam::route('/{record}/edit'),
        ];
    }

    public static function navigationItems(): array
    {
        return [
            \Filament\Navigation\NavigationItem::make()
                ->label('Teams')
                ->url(self::getUrl('index'))
                ->icon('heroicon-o-users')
                ->sort(6),
        ];
    }
}
