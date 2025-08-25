<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ShouldBePaidResource\Pages;
use App\Filament\Resources\BillResource\Pages\EditBill;
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
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Grouping\Group;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ShouldBePaidResource extends Resource
{
    protected static ?string $model = Bill::class;
    protected static ?string $navigationIcon = 'heroicon-o-exclamation-triangle';
    protected static ?string $navigationGroup = 'Finance';
    protected static ?int $navigationSort = 4; // After Invoices (3), before Transactions (5)
    protected static ?string $navigationLabel = 'Should Be Paid';
    protected static ?string $modelLabel = 'Bill';
    protected static ?string $pluralModelLabel = 'Bills';

    public static function getNavigationBadge(): ?string
    {
        // Count unpaid bills that have files with paid invoices (BK received bills)
        $count = static::getModel()::where('status', 'Unpaid')
            ->whereHas('file', function (Builder $fileQuery) {
                $fileQuery->whereHas('invoices', function (Builder $invoiceQuery) {
                    $invoiceQuery->where('status', 'Paid');
                });
            })->count();
        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->whereIn('status', ['Unpaid', 'Partial'])
            ->with(['provider.bankAccounts', 'branch', 'file.providerBranch.provider', 'file.invoices']);
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
            ->modifyQueryUsing(fn (Builder $query) => $query->with([
                'provider', 'branch', 'file.providerBranch.provider', 'file.invoices'
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
                Tables\Columns\TextColumn::make('provider_bank_iban')
                    ->label('Provider Bank IBAN')
                    ->searchable()
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
                Tables\Columns\TextColumn::make('due_date')->date()->sortable(),
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
                // 1. Overdue Bills with status unpaid
                Tables\Filters\Filter::make('overdue_unpaid')
                    ->label('Overdue Bills (Unpaid/Partial)')
                    ->query(function (Builder $query): Builder {
                        return $query->where('due_date', '<', now());
                    })
                    ->toggle()
                    ->indicateUsing(function (): array {
                        return ['overdue_unpaid' => 'Overdue Bills (Unpaid/Partial)'];
                    }),
                // 2. BK Received Bills - Unpaid bills with files that have paid invoices
                Tables\Filters\Filter::make('bk_received')
                    ->label('BK Received Bills')
                    ->query(function (Builder $query): Builder {
                        return $query->whereHas('file', function (Builder $fileQuery) {
                                       $fileQuery->whereHas('invoices', function (Builder $invoiceQuery) {
                                           $invoiceQuery->where('status', 'Paid');
                                       });
                                   });
                    })
                    ->toggle()
                    ->indicateUsing(function (): array {
                        return ['bk_received' => 'BK Received Bills'];
                    }),
                // 3. Missing Documents - Bills without bill_google_link
                Tables\Filters\Filter::make('missing_documents')
                    ->label('Missing Documents')
                    ->query(function (Builder $query): Builder {
                        return $query->where(function (Builder $subQuery) {
                            $subQuery->whereNull('bill_google_link')
                                   ->orWhere('bill_google_link', '');
                        });
                    })
                    ->toggle()
                    ->indicateUsing(function (): array {
                        return ['missing_documents' => 'Missing Documents'];
                    }),

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
                Tables\Actions\BulkAction::make('create_payment')
                    ->label('Create Payment Transaction')
                    ->icon('heroicon-o-currency-euro')
                    ->color('success')
                    ->action(function ($records) {
                        Log::info('Bulk action function called', [
                            'records_count' => $records ? $records->count() : 0,
                            'records_null' => $records === null
                        ]);
                        
                        // Check if records is null or empty
                        if (!$records || $records->isEmpty()) {
                            return redirect()->route('filament.admin.resources.transactions.create');
                        }
                        
                        // Get the first bill to determine the provider
                        $firstBill = $records->first();
                        if (!$firstBill) {
                            return redirect()->route('filament.admin.resources.transactions.create');
                        }
                        
                        // Get bill names for the transaction name
                        $billNames = $records->pluck('name')->take(3); // Take first 3 bill names
                        $name = 'Payment for ' . $billNames->implode(', ');
                        
                        // If there are more than 3 bills, add a count
                        if ($records->count() > 3) {
                            $name .= ' and ' . ($records->count() - 3) . ' more';
                        }
                        
                        // Create URL with pre-filled parameters
                        $params = [
                            'type' => 'Outflow',
                            'related_type' => 'Provider',
                            'related_id' => $firstBill->provider_id,
                            'amount' => $records->sum(function ($bill) {
                                return $bill->total_amount - $bill->paid_amount;
                            }),
                            'date' => now()->format('Y-m-d'),
                            'name' => $name,
                            'bill_ids' => $records->pluck('id')->implode(',')
                        ];
                        
                        $baseUrl = route('filament.admin.resources.transactions.create');
                        $url = $baseUrl . '?' . http_build_query($params);
                        
                        Log::info('Transaction create URL:', [
                            'url' => $url,
                            'params' => $params
                        ]);
                        
                        return redirect()->away($url);
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