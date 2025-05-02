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
                    ])->required()->default('Assistance'),
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
                    ->required()->default('Searching'),

                TextInput::make('initials')->maxLength(10)->required(),
                TextInput::make('number_requests')->numeric()->required()->default(0),
                Select::make('gop_contact_id')->label('GOP Contact')->options(Contact::pluck('title', 'id'))->searchable()->nullable(),
                Select::make('operation_contact_id')->label('Operation Contact')->options(Contact::pluck('title', 'id'))->searchable()->nullable(),
                Select::make('financial_contact_id')->label('Financial Contact')->options(Contact::pluck('title', 'id'))->searchable()->nullable(),
            ]);
    }

    public static function table(Tables\Table $table): Tables\Table
    {
        return $table->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('company_name')->sortable()->searchable(),
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
                TextColumn::make('filesCount')->label('Files')->sortable()->counts('files'),
                TextColumn::make('filesCancelledCount')->label('Canceled')->sortable(),
                TextColumn::make('filesAssistedCount')->label('Assisted')->sortable(),
                TextColumn::make('invoicesTotalNumber')->label('Invoices')->sortable(),
                TextColumn::make('invoicesTotal')->label('Invoices Amount')->sortable()->money('eur'),
                TextColumn::make('invoicesTotalNumberPaid')->label('Paid Invoices')->sortable(),
                TextColumn::make('invoicesTotalPaid')->label('Paid Invoices Amount')->sortable()->money('eur'),
                TextColumn::make('invoicesTotalNumberOutstanding')->label('Unpaid Invoices')->sortable(),
                TextColumn::make('invoicesTotalOutstanding')->label('Unpaid Invoices Amount')->sortable()->money('eur'),
                TextColumn::make('transactionsLastDate')->label('Last Transaction Date')->date('d-m-Y')->sortable(),
                TextColumn::make('transactionLastAmount')->label('Last Transaction Amount')->sortable()->money('eur'),

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
            ])->actions([
                Tables\Actions\Action::make('Overview')
                ->url(fn (Client $record) => ClientResource::getUrl('overview', ['record' => $record]))->color('success'),
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
