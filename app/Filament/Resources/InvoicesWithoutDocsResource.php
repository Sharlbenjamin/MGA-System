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

    protected static ?string $navigationIcon = 'heroicon-o-document-magnifying-glass';
    protected static ?string $navigationGroup = 'Stages';
    protected static ?int $navigationSort = 7;
    protected static ?string $navigationLabel = 'Invoices without docs';
    protected static ?string $modelLabel = 'Invoice without docs';
    protected static ?string $pluralModelLabel = 'Invoices without docs';

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::whereNull('draft_path')->orWhere('draft_path', '')->count();
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                Forms\Components\Select::make('file_id')
                    ->relationship('file', 'mga_reference')
                    ->required(),
                Forms\Components\Select::make('patient_id')
                    ->relationship('patient', 'name')
                    ->required(),
                Forms\Components\DatePicker::make('due_date')
                    ->required(),
                Forms\Components\TextInput::make('total_amount')
                    ->numeric()
                    ->required(),
                Forms\Components\Select::make('status')
                    ->options([
                        'Draft' => 'Draft',
                        'Posted' => 'Posted',
                        'Sent' => 'Sent',
                        'Unpaid' => 'Unpaid',
                        'Partial' => 'Partial',
                        'Paid' => 'Paid',
                    ])
                    ->required(),
                Forms\Components\TextInput::make('draft_path')
                    ->label('Draft Path')
                    ->maxLength(255),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->whereNull('draft_path')->orWhere('draft_path', ''))
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('file.mga_reference')
                    ->label('File Reference')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('patient.name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('patient.client.company_name')
                    ->label('Client')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('total_amount')
                    ->money('EUR')
                    ->sortable(),
                Tables\Columns\TextColumn::make('due_date')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'Draft' => 'gray',
                        'Posted' => 'info',
                        'Sent' => 'info',
                        'Unpaid' => 'warning',
                        'Partial' => 'warning',
                        'Paid' => 'success',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'Draft' => 'Draft',
                        'Posted' => 'Posted',
                        'Sent' => 'Sent',
                        'Unpaid' => 'Unpaid',
                        'Partial' => 'Partial',
                        'Paid' => 'Paid',
                    ]),
            ])
            ->actions([
                Tables\Actions\Action::make('view_file')
                    ->url(fn (Invoice $record): string => route('filament.admin.resources.files.edit', $record->file))
                    ->icon('heroicon-o-eye')
                    ->label('View File'),
                Tables\Actions\Action::make('edit_invoice')
                    ->url(fn (Invoice $record): string => route('filament.admin.resources.invoices.edit', $record))
                    ->icon('heroicon-o-pencil')
                    ->label('Edit Invoice')
                    ->color('primary'),
                Tables\Actions\Action::make('generate_doc')
                    ->url(fn (Invoice $record): string => route('filament.admin.resources.invoices.edit', $record))
                    ->icon('heroicon-o-document-arrow-down')
                    ->label('Generate Doc')
                    ->color('success'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListInvoicesWithoutDocs::route('/'),
        ];
    }
} 