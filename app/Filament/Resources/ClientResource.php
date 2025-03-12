<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ClientResource\Pages;
use App\Models\Client;
use Filament\Resources\Resource;
use Filament\Forms;
use Filament\Tables;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Columns\TextColumn;
use Filament\Navigation\NavigationItem;
use App\Filament\Resources\ClientResource\RelationManagers\LeadsRelationManager;
use Filament\Tables\Filters\SelectFilter;

class ClientResource extends Resource
{
    protected static ?string $model = Client::class;

    protected static ?string $navigationGroup = 'CRM';
protected static ?int $navigationSort = 1;
protected static ?string $navigationIcon = 'heroicon-o-users'; // 👥 Clients Icon

    public static function form(Forms\Form $form): Forms\Form
    {
        return $form
            ->schema([
                TextInput::make('company_name')->required()->maxLength(255),
                Select::make('type')
                    ->options([
                        'Assistance' => 'Assistance',
                        'Insurance' => 'Insurance',
                        'Agency' => 'Agency',
                    ])->required(),
                Select::make('status')
                    ->options([
                        'Searching' => 'Searching',
                        'Interested' => 'Interested',
                        'Sent' => 'Sent',
                        'Rejected' => 'Rejected',
                        'Active' => 'Active',
                        'On Hold' => 'On Hold',
                    ])
                    ->required(),

                TextInput::make('initials')->maxLength(10)->required(),
                TextInput::make('number_requests')->numeric()->required(),
            ]);
    }

    public static function table(Tables\Table $table): Tables\Table
    {
        return $table
            ->columns([
                TextColumn::make('company_name')->sortable()->searchable(),
                TextColumn::make('type'),
                //color the badges if searching red
                TextColumn::make('status')
                ->badge()->color(fn (string $state): string => match ($state) {
                        'Searching' => 'danger',
                        'Interested' => 'warning',
                        'Sent' => 'success',
                        'Rejected' => 'gray',
                        'Active' => 'success',
                        'On Hold' => 'gray',
                }),
                TextColumn::make('initials'),
                TextColumn::make('number_requests'),
                TextColumn::make('leads_count')->label('Leads')->counts('leads'),
            ])->filters([
                SelectFilter::make('status')->multiple()
                ->options([
                        'Searching' => 'Searching',
                        'Interested' => 'Interested',
                        'Sent' => 'Sent',
                        'Rejected' => 'Rejected',
                        'Active' => 'Active',
                        'On Hold' => 'On Hold',
                ])
                ->label('Filter by Status')->attribute('status')
            ]);
    }

    public static function getRelations(): array
{
    return [
        LeadsRelationManager::class, // Register the Leads relation
    ];
}

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListClients::route('/'),
            'create' => Pages\CreateClient::route('/create'),
            'edit' => Pages\EditClient::route('/{record}/edit'),
        ];
    }

}