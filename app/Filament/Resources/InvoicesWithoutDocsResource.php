<?php

namespace App\Filament\Resources;

use App\Filament\Resources\InvoicesWithoutDocsResource\Pages;
use App\Models\Invoice;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class InvoicesWithoutDocsResource extends Resource
{
    protected static ?string $model = Invoice::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationGroup = 'Stages';

    protected static ?int $navigationSort = 7;

    protected static ?string $navigationLabel = 'Invoices without docs';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('invoice_number')
                    ->required()
                    ->maxLength(255),
                Forms\Components\Select::make('file_id')
                    ->relationship('file', 'file_number')
                    ->required(),
                Forms\Components\TextInput::make('total_amount')
                    ->required()
                    ->numeric()
                    ->prefix('€'),
                Forms\Components\Select::make('status')
                    ->options([
                        'Draft' => 'Draft',
                        'Sent' => 'Sent',
                        'Paid' => 'Paid',
                        'Partial' => 'Partial',
                        'Cancelled' => 'Cancelled',
                    ])
                    ->required(),
                Forms\Components\DatePicker::make('invoice_date')
                    ->required(),
                Forms\Components\DatePicker::make('due_date')
                    ->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('invoice_number')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('file.file_number')
                    ->label('File Number')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('file.client.name')
                    ->label('Client')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('total_amount')
                    ->money('EUR')
                    ->sortable(),
                Tables\Columns\BadgeColumn::make('status')
                    ->colors([
                        'warning' => 'Draft',
                        'info' => 'Sent',
                        'success' => 'Paid',
                        'primary' => 'Partial',
                        'danger' => 'Cancelled',
                    ]),
                Tables\Columns\TextColumn::make('invoice_date')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('due_date')
                    ->date()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'Draft' => 'Draft',
                        'Sent' => 'Sent',
                        'Paid' => 'Paid',
                        'Partial' => 'Partial',
                        'Cancelled' => 'Cancelled',
                    ]),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->modifyQueryUsing(fn (Builder $query) => $query->whereNull('invoice_google_link'));
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListInvoicesWithoutDocs::route('/'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::whereNull('invoice_google_link')->count();
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }
} 