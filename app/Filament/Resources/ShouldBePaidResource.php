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
                Group::make('provider_id')
                    ->label('Provider')
                    ->collapsible()
                    ->getTitleFromRecordUsing(fn (Bill $record): string => $record->provider?->name ?? 'No Provider'),
                Group::make('branch_id')
                    ->label('Branch')
                    ->collapsible()
                    ->getTitleFromRecordUsing(fn (Bill $record): string => $record->branch?->branch_name ?? 'No Branch'),
            ])
            // Removed modifyQueryUsing as it conflicts with getEloquentQuery
            // Removed defaultSort to prevent conflicts with grouping
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
                    ->sortable()
                    ->copyable()
                    ->copyMessage('IBAN copied to clipboard')
                    ->copyMessageDuration(1500),
                Tables\Columns\TextColumn::make('name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('file.mga_reference')
                    ->searchable()
                    ->sortable()
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
                    ->sortable()
                    ->state(fn (Bill $record) => $record->total_amount - $record->paid_amount)
                    ->summarize(
                        Summarizer::make()
                            ->label('Total Outstanding')
                            ->using(function ($query) {
                                return $query->sum(DB::raw('total_amount - paid_amount'));
                            })
                            ->formatStateUsing(fn ($state) => '€' . number_format($state, 2))
                    ),
                Tables\Columns\TextColumn::make('file.status')->label('File Status')->searchable()->sortable(),
                Tables\Columns\BadgeColumn::make('bk_status')
                    ->label('BK Status')
                    ->state(function (Bill $record): string {
                        $firstInvoice = $record->file?->invoices?->first();
                        if (!$firstInvoice) {
                            return 'BK Not Received';
                        }
                        return $firstInvoice->status === 'Paid' ? 'BK Received' : 'BK Not Received';
                    })
                    ->colors([
                        'BK Received' => 'success',
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
                    ->requiresConfirmation()
                    ->modalHeading('Create Payment Transaction')
                    ->modalDescription('This will create a new outflow transaction for the selected bills. You can review and modify the details before saving.')
                    ->modalSubmitActionLabel('Create Transaction')
                    ->form([
                        Forms\Components\Select::make('related_type')
                            ->label('Related Type')
                            ->options([
                                'Provider' => 'Provider',
                                'Branch' => 'Branch',
                            ])
                            ->default('Provider')
                            ->required()
                            ->reactive()
                            ->afterStateUpdated(function ($state, $set, $get) {
                                // Clear related_id when type changes
                                $set('related_id', null);
                            }),
                        Forms\Components\Select::make('related_id')
                            ->label('Related Provider/Branch')
                            ->options(function ($get, $records) {
                                $relatedType = $get('related_type');
                                if (!$relatedType || !$records) return [];
                                
                                // Get unique providers/branches from selected bills
                                $billIds = collect($records)->pluck('id');
                                $bills = \App\Models\Bill::whereIn('id', $billIds)
                                    ->with(['provider.bankAccounts', 'branch'])
                                    ->get();
                                
                                if ($relatedType === 'Provider') {
                                    return $bills->pluck('provider')
                                        ->filter()
                                        ->unique('id')
                                        ->mapWithKeys(function ($provider) {
                                            $iban = $provider->bankAccounts->first()?->iban ?? 'No IBAN';
                                            return [$provider->id => "{$provider->name} ({$iban})"];
                                        });
                                } else {
                                    return $bills->pluck('branch')
                                        ->filter()
                                        ->unique('id')
                                        ->pluck('branch_name', 'id');
                                }
                            })
                            ->searchable()
                            ->required()
                            ->disabled(fn ($get) => !$get('related_type')),
                        Forms\Components\TextInput::make('name')
                            ->label('Transaction Name')
                            ->required()
                            ->default(function ($records) {
                                if (!$records) return '';
                                
                                $billIds = collect($records)->pluck('id');
                                $bills = \App\Models\Bill::whereIn('id', $billIds)
                                    ->with('file')
                                    ->get();
                                
                                $fileReferences = $bills->pluck('file.mga_reference')
                                    ->filter()
                                    ->unique()
                                    ->take(3); // Limit to first 3 references
                                
                                $references = $fileReferences->implode(', ');
                                if ($fileReferences->count() > 3) {
                                    $references .= ' and ' . ($fileReferences->count() - 3) . ' more';
                                }
                                
                                return 'Transaction on ' . now()->format('Y-m-d') . ' for ' . $references;
                            }),
                        Forms\Components\TextInput::make('amount')
                            ->label('Total Amount')
                            ->numeric()
                            ->required()
                            ->default(function ($records) {
                                if (!$records) return 0;
                                
                                $billIds = collect($records)->pluck('id');
                                $bills = \App\Models\Bill::whereIn('id', $billIds)->get();
                                
                                return $bills->sum(function ($bill) {
                                    return $bill->total_amount - $bill->paid_amount;
                                });
                            })
                            ->prefix('€'),
                        Forms\Components\DatePicker::make('date')
                            ->label('Transaction Date')
                            ->default(now())
                            ->required(),
                        Forms\Components\Textarea::make('notes')
                            ->label('Notes')
                            ->rows(3)
                            ->placeholder('Optional notes for this transaction'),
                        Forms\Components\Placeholder::make('provider_iban_info')
                            ->label('Provider Bank Information')
                            ->content(function ($records) {
                                if (!$records) return 'No bills selected';
                                
                                $billIds = collect($records)->pluck('id');
                                $bills = \App\Models\Bill::whereIn('id', $billIds)
                                    ->with(['provider.bankAccounts'])
                                    ->get();
                                
                                $providers = $bills->pluck('provider')
                                    ->filter()
                                    ->unique('id');
                                
                                $ibanInfo = [];
                                foreach ($providers as $provider) {
                                    $bankAccount = $provider->bankAccounts->first();
                                    if ($bankAccount) {
                                        $ibanInfo[] = "{$provider->name}: {$bankAccount->iban}";
                                    } else {
                                        $ibanInfo[] = "{$provider->name}: No IBAN available";
                                    }
                                }
                                
                                return implode("\n", $ibanInfo);
                            })
                            ->visible(fn ($records) => !empty($records)),
                    ])
                    ->action(function ($data, $records) {
                        // Create the transaction
                        $transaction = new \App\Models\Transaction();
                        $transaction->type = 'Outflow';
                        $transaction->related_type = $data['related_type'];
                        $transaction->related_id = $data['related_id'];
                        $transaction->name = $data['name'];
                        $transaction->amount = $data['amount'];
                        $transaction->date = $data['date'];
                        $transaction->notes = $data['notes'] ?? null;
                        $transaction->status = 'Draft'; // Start as draft so user can review
                        $transaction->save();
                        
                        // Attach the selected bills to the transaction without marking them as paid
                        $billIds = collect($records)->pluck('id');
                        $transaction->attachBillsForDraft($billIds);
                        
                        // Show success notification
                        \Filament\Notifications\Notification::make()
                            ->title('Transaction Created')
                            ->body('Payment transaction has been created successfully. You can review and finalize it.')
                            ->success()
                            ->send();
                        
                        // Redirect to the transaction edit page
                        return redirect()->route('filament.admin.resources.transactions.edit', ['record' => $transaction->id]);
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