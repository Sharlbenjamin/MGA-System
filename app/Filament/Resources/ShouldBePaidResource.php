<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ShouldBePaidResource\Pages;
use App\Models\Bill;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Filament\Tables\Columns\Summarizers\Count;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Grouping\Group;

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
            ->where('status', 'Unpaid')
            ->whereHas('file', function (Builder $fileQuery) {
                $fileQuery->whereHas('invoices', function (Builder $invoiceQuery) {
                    $invoiceQuery->where('status', 'Paid');
                });
            })
            ->orderBy('due_date', 'asc');
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
            ->defaultSort('due_date', 'asc')
            ->columns([
                Tables\Columns\TextColumn::make('provider.name')->searchable()->sortable()->label('Provider'),
                Tables\Columns\TextColumn::make('branch.branch_name')->searchable()->sortable()->label('Branch'),
                Tables\Columns\TextColumn::make('name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('file.mga_reference')
                    ->searchable()
                    ->sortable()
                    ->url(fn (Bill $record) => $record->file?->google_drive_link)
                    ->openUrlInNewTab()
                    ->color(fn (Bill $record) => $record->file?->google_drive_link ? 'primary' : 'gray'),
                Tables\Columns\TextColumn::make('due_date')->date()->sortable(),
                Tables\Columns\BadgeColumn::make('status')->colors(['danger' => 'Unpaid'])->summarize(Count::make('status')->label('Number of Bills')),
                Tables\Columns\TextColumn::make('total_amount')->money('EUR')->sortable()->summarize(Sum::make('total_amount')->label('Total Amount')->prefix('€')),
                Tables\Columns\TextColumn::make('paid_amount')->money('EUR')->sortable()->summarize(Sum::make('paid_amount')->label('Paid Amount')->prefix('€')),
                Tables\Columns\TextColumn::make('remaining_amount')->money('EUR')->sortable()->state(fn (Bill $record) => $record->total_amount - $record->paid_amount),
                Tables\Columns\TextColumn::make('file.status')->label('File Status')->searchable()->sortable(),
                Tables\Columns\BadgeColumn::make('bill_google_link')
                    ->label('Google Drive')
                    ->state(fn (Bill $record): string => $record->bill_google_link ? 'Linked' : 'Missing')
                    ->color(fn (Bill $record): string => $record->bill_google_link ? 'success' : 'danger')
                    ->summarize(Count::make('bill_google_link')->label('Total Bills'))
                    ->toggleable(isToggledHiddenByDefault: false),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('provider.name')->relationship('provider', 'name')->label('Provider')->searchable()->multiple(),
                Tables\Filters\SelectFilter::make('branch.branch_name')->relationship('branch', 'branch_name')->label('Branch')->searchable()->multiple(),
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'Unpaid' => 'Unpaid',
                    ]),
                // 1. Overdue Bills with status unpaid
                Tables\Filters\Filter::make('overdue_unpaid')
                    ->label('Overdue Bills (Unpaid)')
                    ->query(function (Builder $query): Builder {
                        return $query->where('due_date', '<', now())
                                   ->where('status', 'Unpaid');
                    })
                    ->indicateUsing(function (): array {
                        return ['overdue_unpaid' => 'Overdue Bills (Unpaid)'];
                    }),
                // 2. BK Received Bills - Unpaid bills with files that have paid invoices
                Tables\Filters\Filter::make('bk_received')
                    ->label('BK Received Bills')
                    ->query(function (Builder $query): Builder {
                        return $query->where('status', 'Unpaid')
                                   ->whereHas('file', function (Builder $fileQuery) {
                                       $fileQuery->whereHas('invoices', function (Builder $invoiceQuery) {
                                           $invoiceQuery->where('status', 'Paid');
                                       });
                                   });
                    })
                    ->indicateUsing(function (): array {
                        return ['bk_received' => 'BK Received Bills'];
                    }),
                // 3. Missing Documents - Bills without bill_google_link
                Tables\Filters\Filter::make('missing_documents')
                    ->label('Missing Documents')
                    ->query(function (Builder $query): Builder {
                        return $query->whereNull('bill_google_link')
                                   ->orWhere('bill_google_link', '');
                    })
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
        ];
    }
} 