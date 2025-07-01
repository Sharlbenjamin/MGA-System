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
    protected static ?string $modelLabel = 'Bill with Paid Invoice';
    protected static ?string $pluralModelLabel = 'Bills with Paid Invoices';

    public static function getNavigationBadge(): ?string
    {
        $count = static::getModel()::whereIn('status', ['Unpaid', 'Partial'])
            ->whereHas('file', function ($query) {
                $query->whereHas('invoices', function ($invoiceQuery) {
                    $invoiceQuery->where('status', 'Paid');
                });
            })
            ->count();
        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->whereHas('file', function (Builder $query) {
                $query->whereHas('invoices', function (Builder $invoiceQuery) {
                    $invoiceQuery->where('status', 'Paid');
                });
            })
            ->where(function (Builder $query) {
                $query->where('status', 'Unpaid')
                    ->orWhere('status', 'Partial');
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
                Tables\Columns\BadgeColumn::make('status')->colors(['danger' => 'Unpaid','warning' => 'Partial'])->summarize(Count::make('status')->label('Number of Bills')),
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
                        'Partial' => 'Partial',
                    ]),
                Tables\Filters\Filter::make('overdue')
                    ->label('Overdue Bills')
                    ->query(function (Builder $query): Builder {
                        return $query->where('due_date', '<', now());
                    })
                    ->indicateUsing(function (): array {
                        return ['overdue' => 'Overdue Bills'];
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