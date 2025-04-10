<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ClientResource\Pages;
use App\Filament\Resources\ClientResource\RelationManagers\BankAccountRelationManager;
use App\Filament\Resources\ClientResource\RelationManagers\ContactRelationManager;
use App\Filament\Resources\ClientResource\RelationManagers\InvoiceRelationManager;
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
use App\Models\Contact;
class ClientResource extends Resource
{
    protected static ?string $model = Client::class;

    protected static ?string $navigationGroup = 'CRM';
    protected static ?int $navigationSort = 1;
    protected static ?string $navigationIcon = 'heroicon-o-users';

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
                        'Broker' => 'Broker',
                        'No Reply' => 'No Reply',
                    ])
                    ->required(),

                TextInput::make('initials')->maxLength(10)->required(),
                TextInput::make('number_requests')->numeric()->required(),
                Select::make('gop_contact_id')->label('GOP Contact')->options(Contact::pluck('title', 'id'))->searchable()->nullable(),
                Select::make('operation_contact_id')->label('Operation Contact')->options(Contact::pluck('title', 'id'))->searchable()->nullable(),
                Select::make('financial_contact_id')->label('Financial Contact')->options(Contact::pluck('title', 'id'))->searchable()->nullable(),
            ]);
    }

    public static function table(Tables\Table $table): Tables\Table
    {
        return $table
            ->columns([
                TextColumn::make('company_name')->sortable()->searchable(),
                TextColumn::make('type')->sortable(),
                //color the badges if searching red
                TextColumn::make('status')
                ->badge()->color(fn (string $state): string => match ($state) {
                        'Searching' => 'danger',
                        'Interested' => 'warning',
                        'Sent' => 'success',
                        'Rejected' => 'gray',
                        'Active' => 'success',
                        'On Hold' => 'gray',
                        'Broker' => 'success',
                        'No Reply' => 'danger',
                }),
                TextColumn::make('initials'),
                TextColumn::make('number_requests'),
                TextColumn::make('leads_count')->label('Leads')->sortable()->counts('leads'),
                TextColumn::make('latestLead.last_contact_date')->sortable()->label('Last Contact')->date('d-m-Y'),
            ])->filters([
                SelectFilter::make('status')->multiple()
                ->options([
                        'Searching' => 'Searching',
                        'Interested' => 'Interested',
                        'Sent' => 'Sent',
                        'Rejected' => 'Rejected',
                        'Active' => 'Active',
                        'On Hold' => 'On Hold',
                        'Broker' => 'Broker',
                        'No Reply' => 'No Reply',

                ])
                ->label('Filter by Status')->attribute('status')
            ]);
    }

    public static function getRelations(): array
{
    return [
        LeadsRelationManager::class,
        ContactRelationManager::class,
        BankAccountRelationManager::class,
        InvoiceRelationManager::class,
    ];
}

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListClients::route('/'),
            'create' => Pages\CreateClient::route('/create'),
            'edit' => Pages\EditClient::route('/{record}/edit'),
            'overview' => Pages\ClientOverview::route('/{record}'),
        ];
    }

}