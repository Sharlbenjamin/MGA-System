<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ShouldBePaidResource\Pages;
use App\Filament\Resources\BillResource\Pages\EditBill;
use App\Filament\Resources\TransactionResource;
use App\Models\Bill;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Components;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Filament\Tables\Columns\Summarizers\Count;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\Summarizers\Summarizer;
use Filament\Tables\Grouping\Group;
use Illuminate\Support\Facades\DB;

class ShouldBePaidResource extends Resource
{
    protected static ?string $model = Bill::class;
    protected static ?string $navigationIcon = 'heroicon-o-exclamation-triangle';
    protected static ?string $navigationGroup = 'Finance';
    protected static ?int $navigationSort = 4; // After Invoices (3), before Transactions (5)
    protected static ?string $navigationLabel = 'Payables';
    protected static ?string $modelLabel = 'Payable';
    protected static ?string $pluralModelLabel = 'Payables';

    public const STATE_ALL_UNPAID = 'all_unpaid';

    public const STATE_PAYABLES = 'payables';

    public const STATE_BK_RECEIVED = 'bk_received';

    public const STATE_OVERDUE = 'overdue';

    public const STATE_MISSING_DOCUMENT = 'missing_document';

    public const STATE_HAS_BANK_ACCOUNT = 'has_bank_account';

    /**
     * @return array<string, string>
     */
    public static function payableStates(): array
    {
        return [
            self::STATE_ALL_UNPAID => 'All unpaid bills',
            self::STATE_PAYABLES => 'Payables',
            self::STATE_BK_RECEIVED => 'BK received',
            self::STATE_OVERDUE => 'Overdue',
            self::STATE_MISSING_DOCUMENT => 'Missing document',
            self::STATE_HAS_BANK_ACCOUNT => 'Has a bank account',
        ];
    }

    public static function allUnpaidBillsQuery(): Builder
    {
        return Bill::query()->whereIn('bills.status', ['Unpaid', 'Partial']);
    }

    public static function applyPayableState(Builder $query, ?string $state): Builder
    {
        return match ($state) {
            self::STATE_ALL_UNPAID => $query,
            self::STATE_BK_RECEIVED => $query->whereHas('file', function (Builder $fileQuery) {
                $fileQuery->whereHas('invoices', function (Builder $invoiceQuery) {
                    $invoiceQuery->where('status', 'Paid');
                });
            }),
            self::STATE_OVERDUE => $query->whereDate('bills.due_date', '<', today()),
            self::STATE_MISSING_DOCUMENT => $query->where(function (Builder $subQuery) {
                $subQuery->whereNull('bills.bill_google_link')
                    ->orWhere('bills.bill_google_link', '');
            }),
            self::STATE_HAS_BANK_ACCOUNT => $query->whereNotNull('bills.bank_account_id'),
            default => $query->whereHas('provider', function (Builder $providerQuery) {
                $providerQuery->where('needs_payment', true);
            }),
        };
    }

    public static function payableStateQuery(string $state): Builder
    {
        return static::applyPayableState(static::allUnpaidBillsQuery(), $state);
    }

    public static function outstandingAmountForState(string $state): float
    {
        return (float) static::payableStateQuery($state)
            ->sum(DB::raw('bills.total_amount - bills.paid_amount'));
    }

    public static function getNavigationBadge(): ?string
    {
        $count = static::payableStateQuery(self::STATE_PAYABLES)->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->whereIn('bills.status', ['Unpaid', 'Partial'])
            ->with(['provider.bankAccounts', 'branch', 'file.providerBranch.provider', 'file.invoices', 'file.patient']);
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                // Form fields can be added here if needed for editing
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->groups([
                Group::make('provider.name')->label('Provider')->collapsible(),
                Group::make('branch.branch_name')->label('Branch')->collapsible(),
            ])
            ->defaultGroup('provider.name')
            ->modifyQueryUsing(fn (Builder $query) => $query->with([
                'provider', 'branch', 'file.providerBranch.provider', 'file.invoices', 'file.patient'
            ]))
            ->defaultSort('due_date', 'asc')
            ->columns([
                Tables\Columns\TextColumn::make('provider.name')
                    ->searchable()
                    ->sortable()
                    ->label('Provider'),
                Tables\Columns\TextColumn::make('branch.branch_name')
                    ->searchable()
                    ->sortable()
                    ->label('Branch'),
                Tables\Columns\TextColumn::make('file.patient.name')
                    ->label('Patient')
                    ->searchable(),
                Tables\Columns\TextColumn::make('provider_bank_iban')
                    ->label('Provider Bank IBAN')
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->whereHas('provider.bankAccounts', function (Builder $bankAccountQuery) use ($search) {
                            $bankAccountQuery->where('iban', 'like', "%{$search}%");
                        });
                    })
                    ->sortable(false)
                    ->copyable()
                    ->copyMessage('IBAN copied to clipboard')
                    ->copyMessageDuration(1500),
                Tables\Columns\TextColumn::make('name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('file.mga_reference')
                    ->searchable()
                    ->sortable(query: function (Builder $query, string $direction): Builder {
                        return $query
                            ->join('files', 'bills.file_id', '=', 'files.id')
                            ->orderBy('files.mga_reference', $direction);
                    })
                    ->url(fn (Bill $record) => $record->file?->google_drive_link)
                    ->openUrlInNewTab()
                    ->color(fn (Bill $record) => $record->file?->google_drive_link ? 'primary' : 'gray'),
                Tables\Columns\TextColumn::make('bill_date')
                    ->label('Bill Date')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('due_date')
                    ->label('Due Date')
                    ->date()
                    ->sortable(),
                Tables\Columns\BadgeColumn::make('status')
                    ->colors(['danger' => 'Unpaid', 'warning' => 'Partial'])
                    ->summarize(Count::make()->label('Total Bills')),
                Tables\Columns\TextColumn::make('total_amount')->money('EUR')->sortable()->summarize(Sum::make('total_amount')->label('Total Amount')->prefix('€')),
                Tables\Columns\TextColumn::make('paid_amount')->money('EUR')->sortable()->summarize(Sum::make('paid_amount')->label('Paid Amount')->prefix('€')),
                Tables\Columns\TextColumn::make('remaining_amount')
                    ->money('EUR')
                    ->sortable(false)
                    ->state(fn (Bill $record) => $record->total_amount - $record->paid_amount)
                    ->summarize(
                        Summarizer::make()
                            ->label('Total Outstanding')
                            ->using(function ($query) {
                                return $query->sum(DB::raw('total_amount - paid_amount'));
                            })
                            ->formatStateUsing(fn ($state) => '€' . number_format($state, 2))
                    ),
                Tables\Columns\TextColumn::make('file.status')->label('File Status')->searchable()->sortable(query: function (Builder $query, string $direction): Builder {
                        return $query
                            ->join('files', 'bills.file_id', '=', 'files.id')
                            ->orderBy('files.status', $direction);
                    }),
                Tables\Columns\BadgeColumn::make('bk_status')
                    ->label('BK Status')
                    ->state(function (Bill $record): string {
                        $firstInvoice = $record->file?->invoices?->first();
                        if (!$firstInvoice) {
                            return 'BK Not Received';
                        }
                        return $firstInvoice->status;
                    })
                    ->colors([
                        'Paid' => 'success',
                        'Unpaid' => 'danger',
                        'Partial' => 'warning',
                        'Draft' => 'gray',
                        'Sent' => 'info',
                        'BK Not Received' => 'danger',
                    ])
                    ->size('sm')
                    ->extraAttributes(['class' => 'font-semibold']),
                Tables\Columns\BadgeColumn::make('bill_google_link')
                    ->label('Google Drive')
                    ->state(fn (Bill $record): string => $record->bill_google_link ? 'Linked' : 'Missing')
                    ->color(fn (Bill $record): string => $record->bill_google_link ? 'success' : 'danger')
                    ->summarize(Count::make()->label('Total Bills'))
                    ->toggleable(isToggledHiddenByDefault: false),

            ])
            ->filters([
                Tables\Filters\SelectFilter::make('payable_state')
                    ->label('State')
                    ->options(static::payableStates())
                    ->default(self::STATE_PAYABLES)
                    ->selectablePlaceholder(false)
                    ->query(function (Builder $query, array $data): Builder {
                        $state = $data['value'] ?? null;

                        return static::applyPayableState(
                            $query,
                            is_string($state) && $state !== '' ? $state : self::STATE_PAYABLES,
                        );
                    }),
                Tables\Filters\SelectFilter::make('provider')
                    ->relationship('provider', 'name')
                    ->label('Provider')
                    ->searchable()
                    ->multiple(),
                Tables\Filters\SelectFilter::make('branch')
                    ->relationship('branch', 'branch_name')
                    ->label('Branch')
                    ->searchable()
                    ->multiple(),
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'Unpaid' => 'Unpaid',
                        'Partial' => 'Partial',
                    ]),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('download')
                    ->icon('heroicon-o-pencil')
                    ->url(fn (Bill $record) => $record->draft_path)
                    ->openUrlInNewTab(),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
                Tables\Actions\BulkAction::make('create_draft_payment')
                    ->label('Create draft payment')
                    ->icon('heroicon-o-currency-euro')
                    ->color('success')
                    ->action(function ($records) {
                        if (! $records || $records->isEmpty()) {
                            return redirect()->route('filament.admin.resources.transactions.create');
                        }

                        $firstBill = $records->first();
                        if (! $firstBill) {
                            return redirect()->route('filament.admin.resources.transactions.create');
                        }

                        $billCount = $records->count();
                        $params = [
                            'type' => 'Outflow',
                            'related_type' => $firstBill->branch_id ? 'Branch' : 'Provider',
                            'related_id' => $firstBill->branch_id ?? $firstBill->provider_id,
                            'amount' => $records->sum(fn (Bill $bill) => $bill->total_amount - $bill->paid_amount),
                            'date' => now()->format('Y-m-d'),
                            'bill_ids' => $records->pluck('id')->implode(','),
                            'status' => 'Draft',
                            'documentation_category' => $billCount >= 2 ? 'provider_bulk' : 'provider_single',
                        ];

                        if ($firstBill->bank_account_id) {
                            $params['bank_account_id'] = $firstBill->bank_account_id;
                        }

                        return redirect()->away(TransactionResource::getUrl('create', $params));
                    }),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            // No relations needed for this view
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListShouldBePaid::route('/'),
            'edit' => EditBill::route('/{record}/edit'),
        ];
    }
} 