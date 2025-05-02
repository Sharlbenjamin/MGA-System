<?php

namespace App\Filament\Resources;


use App\Filament\Resources\TransactionResource\Pages;
use App\Filament\Resources\TransactionResource\RelationManager\InvoiceRelationManager;
use App\Filament\Resources\TransactionResource\RelationManager\BillRelationManager;
use App\Models\Bill;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Provider;
use App\Models\ProviderBranch;
use App\Models\Transaction;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\DB;
use Filament\Forms\Components\Actions\Action;
use Illuminate\Database\Eloquent\Model;

class TransactionResource extends Resource
{
    protected static ?string $model = Transaction::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';
    protected static ?string $navigationGroup = 'Finance';
    protected static ?int $navigationSort = 4;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('type')->options([
                    'Income' => 'Income',
                    'Outflow' => 'Outflow',
                    'Expense' => 'Expense',
                ])->required(),
                Forms\Components\Select::make('related_type')->options(fn ($get) => Self::relatedTypes($get('type')))->required()->searchable()->reactive(),
                    // I want to select an invoice if realted_type is Client
                Forms\Components\Select::make('related_id')->label('Client')->required()->options(Client::all()->pluck('company_name', 'id'))->visible(fn ($get) => $get('related_type') === 'Client'),
                Forms\Components\Select::make('related_id')->label('Provider')->required()->options(Provider::all()->pluck('name', 'id'))->visible(fn ($get) => $get('related_type') === 'Provider'),
                Forms\Components\Select::make('related_id')->label('Provider')->required()->options(ProviderBranch::all()->pluck('name', 'id'))->visible(fn ($get) => $get('related_type') === 'Branch'),
                Forms\Components\Select::make('bank_account_id')->relationship('bankAccount', 'beneficiary_name')->required(),
                Forms\Components\TextInput::make('name')->required()->maxLength(255),
                Forms\Components\TextInput::make('amount')->required()->numeric()->prefix('€'),
                Forms\Components\DatePicker::make('date')->required()->default(now()),
                Forms\Components\Textarea::make('notes')->columnSpanFull(),
                Forms\Components\TextInput::make('attachment_path')->maxLength(255),
                Forms\Components\TextInput::make('bank_charges')
                ->numeric()
                ->prefix('€')
                ->maxValue(999999.99)
                ->default(0),

                Forms\Components\Toggle::make('charges_covered_by_client')
                ->default(false),


                // I want to have a table to select the related invoice or bill
                Forms\Components\Select::make('invoices')
                    ->label('Invoices')
                    ->relationship('invoices', 'name')
                    ->multiple()
                    ->searchable()
                    ->preload()
                    ->live()
                    ->visible(fn ($get) => $get('related_type') === 'Client')
                    ->options(function (callable $get) {
                        $clientId = $get('related_id');
                        if (!$clientId) return [];

                        return Invoice::query()
                            ->whereHas('patient', function ($q) use ($clientId) {
                                $q->where('client_id', $clientId);
                            })
                            ->where(function ($query) {
                                $query->whereDoesntHave('transactions');
                            })
                            ->pluck('name', 'id');
                    })
                    ->afterStateUpdated(function ($state, $record) {
                        if ($record && $state) {
                            $record->attachInvoices($state);
                        }
                    }),
                Forms\Components\Select::make('bills')
                ->relationship('bills', 'name')
                ->label('Bills')
                ->multiple()
                ->searchable()
                ->preload()
                ->live()
                ->visible(fn ($get) => $get('related_type') === 'Provider' || $get('related_type') === 'Branch')
                ->options(function (callable $get) {
                    $relatedType = $get('related_type');
                    $relatedId = $get('related_id');
                    if (!$relatedId) return [];

                    return static::getBills($relatedType, $relatedId)
                        ->pluck('name', 'id')
                        ->toArray();
                })
                ->afterStateUpdated(function ($state, $record) {
                    if ($record && $state) {
                        $record->attachBills($state);
                    }
                }),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('date', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('name')->searchable(),
                Tables\Columns\TextColumn::make('bankAccount.beneficiary_name')->sortable(),
                Tables\Columns\TextColumn::make('related_type')->searchable(),
                Tables\Columns\TextColumn::make('related_id')->numeric()->sortable(),
                Tables\Columns\TextColumn::make('amount')
                ->numeric()
                ->sortable()
                ->summarize([
                    Tables\Columns\Summarizers\Sum::make()
                    ->money('EUR')
                    ->label('Monthly Total')
                ]),
                Tables\Columns\TextColumn::make('type')->searchable()
                ->color(fn ($record) => match ($record->type) {'Income' => 'success','Outflow' => 'warning','Expense' => 'danger',})->badge(),
                Tables\Columns\TextColumn::make('date')->date()->sortable(),
                Tables\Columns\TextColumn::make('attachment_path')->searchable(),
                Tables\Columns\TextColumn::make('bank_charges')->money()->sortable(),
                Tables\Columns\IconColumn::make('charges_covered_by_client')->label('Covered')->boolean()->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')->options(['Income' => 'Income', 'Outflow' => 'Outflow', 'Expense' => 'Expense'])->multiple(),
            ])
            ->groups([
                Tables\Grouping\Group::make('date')
                    ->label('Month')
                    ->date()
                    ->collapsible()
                    ->getTitleFromRecordUsing(fn (Transaction $record): string => $record->date->format('F Y'))
                    ->orderQueryUsing(fn (Builder $query, string $direction) => $query->orderBy('date', 'desc')),
            ])
            ->defaultGroup('date')
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
            InvoiceRelationManager::class,
            BillRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTransactions::route('/'),
            'create' => Pages\CreateTransaction::route('/create'),
            'edit' => Pages\EditTransaction::route('/{record}/edit'),
        ];
    }

    public function relatedInvoices()
    {
        // invocies will pass an array of invoice ids
        return $this->hasMany(Invoice::class);
    }

    public function relatedBills()
    {
        return $this->hasMany(Bill::class);
    }


    public static function getBills($relatedType, $relatedId)
    {
        $bills = collect();
        if ($relatedType === 'Provider') {
            $bills = Bill::query()
                ->whereHas('file', function ($query) use ($relatedId) {
                    $query->whereHas('provider', function ($providerQuery) use ($relatedId) {
                        $providerQuery->where('providers.id', $relatedId);
                    })
                    ->orWhereHas('providerBranch', function ($branchQuery) use ($relatedId) {
                        $branchQuery->where('provider_branches.provider_id', $relatedId);
                    });
                })
                ->where('bills.status', '!=', 'Paid')
                ->whereDoesntHave('transactions')
                ->get();
        } elseif ($relatedType === 'Branch') {
            $bills = Bill::query()
                ->whereHas('file', function ($query) use ($relatedId) {
                    $query->where('files.provider_branch_id', $relatedId);
                })
                ->where('bills.status', '!=', 'Paid')
                ->whereDoesntHave('transactions')
                ->get();
        }
        return $bills;
    }

    public static function relatedTypes($get)
    {
        if ($get === null) {
            return [];
        }

        if ($get === 'Expense') {
            return [
                'Taxes' => 'Taxes',
                'Salaries' => 'Salaries',
                'Bonuses' => 'Bonuses',
                'Installments' => 'Installments',
                'Utilities' => 'Utilities',
                'Maintenance' => 'Maintenance',
            ];
        }

        return [
            'Client' => 'Client',
            'Provider' => 'Provider',
            'Branch' => 'Branch',
        ];
    }
}
