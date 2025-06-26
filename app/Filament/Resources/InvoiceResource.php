<?php

namespace App\Filament\Resources;

use App\Filament\Resources\InvoiceResource\Pages;
use App\Filament\Resources\InvoiceResource\RelationManagers\ItemsRelationManager;
use App\Models\BankAccount;
use App\Models\Invoice;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\Resource;
use Filament\Tables\Table;
use Filament\Tables;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\Summarizers\Summarizer;
use Filament\Tables\Grouping\Group;
use Illuminate\Database\Eloquent\Builder;

class InvoiceResource extends Resource
{
    protected static ?string $model = Invoice::class;
    protected static ?string $navigationIcon = 'heroicon-o-document-currency-euro';
    protected static ?int $navigationSort = 3;
    protected static ?string $navigationGroup = 'Finance';

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::whereIn('status', ['Draft', 'Posted'])->count();
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }
    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Card::make()
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->maxLength(255)
                            ->disabled()
                            ->dehydrated(),
                        Forms\Components\Select::make('file_id')
                            ->relationship('file', 'mga_reference', fn (Builder $query, Get $get) => $query->where('patient_id', $get('patient_id')))
                            ->required()
                            ->searchable()->preload(),

                        Forms\Components\Select::make('patient_id')
                            ->relationship('patient', 'name')
                            ->required()
                            ->searchable()->preload()
                            ->live()
                            ->default(fn () => request()->get('patient_id')),

                        Forms\Components\Select::make('bank_account_id')
                            ->relationship('bankAccount', 'beneficiary_name')
                            ->options(function () {
                                return BankAccount::where('type', 'internal')->pluck('beneficiary_name', 'id');
                            })
                            ->nullable(),

                        Forms\Components\DatePicker::make('invoice_date')
                            ->default(now()->format('Y-m-d')),

                        Forms\Components\Select::make('status')
                            ->options([
                                'Draft' => 'Draft',
                                'Posted' => 'Posted',
                                'Sent' => 'Sent',
                                'Unpaid' => 'Unpaid',
                                'Partial' => 'Partial',
                                'Paid' => 'Paid',
                            ])->default('Draft')
                            ->required(),

                        ])->columnSpan(['lg' => 2]),


                Forms\Components\Card::make()
                    ->schema([
                        Forms\Components\Placeholder::make('gop_in_total')
                            ->label('GOP In Total')
                            ->content(fn (?Invoice $record): string => $record ? '€'.number_format($record->file->gopInTotal(), 2) : '0.00'),

                        Forms\Components\Placeholder::make('bills_total')
                            ->label('Bills Total')
                            ->content(fn (?Invoice $record): string => $record ? '€'.number_format($record->file->billsTotal(), 2) : '0.00'),

                        Forms\Components\Placeholder::make('due_date')
                            ->label('Due date')
                            ->content(fn (?Invoice $record): string => $record ? '(' . $record->due_date->format('d/m/Y') . ')' . ' - ' . abs((int)$record->due_date->diffInDays(now())) . ' days' : '-'),

                        Forms\Components\Placeholder::make('subtotal')
                            ->label('Subtotal')
                            // lets add € sign before the subtotal
                            ->content(fn (?Invoice $record): string => $record ? '€'.number_format($record->subtotal, 2) : '0.00'),

                        Forms\Components\Placeholder::make('discount')
                            ->label('Discount')
                            ->content(fn (?Invoice $record): string => $record ? '€'.number_format($record->discount, 2) : '0.00'),

                        Forms\Components\Placeholder::make('total_amount')
                            ->label('Total Amount')
                            ->content(fn (?Invoice $record): string => $record ? '€'.number_format($record->total_amount, 2) : '0.00'),

                    ])->columnSpan(['lg' => 1]),
            ])
            ->columns(3);
    }

    public static function table(Table $table): Table
    {
        return $table->groups([
            Group::make('patient.client.company_name')->collapsible(),
            Group::make('patient.name')->collapsible(),
             ])
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('file.mga_reference')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('patient.client.company_name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('patient.name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('invoice_date')->date()->sortable(),
                Tables\Columns\TextColumn::make('due_date')->date()->sortable(),

                Tables\Columns\BadgeColumn::make('status')
                    ->colors([
                        'danger' => 'Unpaid',
                        'gray' => 'Draft',
                        'info' => 'Posted',
                        'success' => 'Paid',
                        'primary' => 'Sent',
                        'secondary' => 'Partial',
                    ]),
                Tables\Columns\TextColumn::make('total_amount')->money('EUR')->sortable()->summarize(Sum::make('total_amount')->label('Total Amount')->prefix('€')),
                Tables\Columns\TextColumn::make('paid_amount')->money('EUR')->sortable()->summarize(Sum::make('paid_amount')->label('Paid Amount')->prefix('€')),
                Tables\Columns\TextColumn::make('remaining_amount')->state(fn (Invoice $record) => $record->total_amount - $record->paid_amount)->money('EUR')->sortable(),
                Tables\Columns\TextColumn::make('file.status')->label('File Status')->badge()->color(fn (string $state): string => match ($state) {
                    'New' => 'gray',
                    'Handling' => 'info',
                    'Available' => 'info',
                    'Confirmed' => 'success',
                    'Assisted' => 'success',
                    'Hold' => 'warning',
                    'Cancelled' => 'danger',
                    'Void' => 'gray',
                }),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('client')->relationship('patient.client', 'company_name')->label('Client')->searchable()->multiple(),
                Tables\Filters\SelectFilter::make('patient_id')->relationship('patient', 'name')->label('Patient')->searchable(),
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'Draft' => 'Draft',
                        'Posted' => 'Posted',
                        'Sent' => 'Sent',
                        'Unpaid' => 'Unpaid',
                        'Paid' => 'Paid',
                        'Partial' => 'Partial',
                    ]),

                Tables\Filters\Filter::make('draft_or_posted')
                    ->form([
                        Forms\Components\Checkbox::make('show_draft_posted')
                            ->label('Show Draft & Posted Invoices Only')
                            ->default(true),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query->when(
                            $data['show_draft_posted'] ?? true,
                            fn (Builder $query): Builder => $query->whereIn('status', ['Draft', 'Posted']),
                        );
                    }),

                Tables\Filters\Filter::make('due_date')
                    ->form([
                        Forms\Components\DatePicker::make('due_from'),
                        Forms\Components\DatePicker::make('due_until'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['due_from'],
                                fn (Builder $query, $date): Builder => $query->whereDate('due_date', '>=', $date),
                            )
                            ->when(
                                $data['due_until'],
                                fn (Builder $query, $date): Builder => $query->whereDate('due_date', '<=', $date),
                            );
                    })
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])->headerActions([Tables\Actions\CreateAction::make()])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            ItemsRelationManager::class,
            //TransactionsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListInvoices::route('/'),
            'create' => Pages\CreateInvoice::route('/create'),
            'edit' => Pages\EditInvoice::route('/{record}/edit'),
        ];
    }
}