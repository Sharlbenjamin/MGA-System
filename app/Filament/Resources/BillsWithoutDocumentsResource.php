<?php

namespace App\Filament\Resources;

use App\Filament\Resources\BillsWithoutDocumentsResource\Pages;
use App\Models\Bill;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class BillsWithoutDocumentsResource extends Resource
{
    protected static ?string $model = Bill::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-magnifying-glass';
    protected static ?string $navigationGroup = 'Stages';
    protected static ?int $navigationSort = 6;
    protected static ?string $navigationLabel = 'Bills without documents';
    protected static ?string $modelLabel = 'Bill without documents';
    protected static ?string $pluralModelLabel = 'Bills without documents';

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::whereNull('bill_google_link')->orWhere('bill_google_link', '')->count();
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
                Forms\Components\Select::make('provider_id')
                    ->relationship('provider', 'name')
                    ->required(),
                Forms\Components\Select::make('branch_id')
                    ->relationship('branch', 'branch_name')
                    ->required(),
                Forms\Components\DatePicker::make('due_date')
                    ->required(),
                Forms\Components\TextInput::make('total_amount')
                    ->numeric()
                    ->required(),
                Forms\Components\Select::make('status')
                    ->options([
                        'Draft' => 'Draft',
                        'Sent' => 'Sent',
                        'Unpaid' => 'Unpaid',
                        'Partial' => 'Partial',
                        'Paid' => 'Paid',
                    ])
                    ->required(),
                Forms\Components\TextInput::make('bill_google_link')
                    ->label('Google Drive Link')
                    ->url()
                    ->maxLength(255),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->whereNull('bill_google_link')->orWhere('bill_google_link', ''))
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('file.mga_reference')
                    ->label('File Reference')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('file.patient.name')
                    ->label('Patient')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('file.patient.client.company_name')
                    ->label('Client')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('provider.name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('branch.branch_name')
                    ->label('Branch')
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
                        'Sent' => 'Sent',
                        'Unpaid' => 'Unpaid',
                        'Partial' => 'Partial',
                        'Paid' => 'Paid',
                    ]),
                Tables\Filters\SelectFilter::make('provider_id')
                    ->relationship('provider', 'name')
                    ->label('Provider'),
                Tables\Filters\SelectFilter::make('branch_id')
                    ->relationship('branch', 'branch_name')
                    ->label('Branch'),
            ])
            ->actions([
                Tables\Actions\Action::make('view_file')
                    ->url(fn (Bill $record): string => route('filament.admin.resources.files.edit', $record->file))
                    ->icon('heroicon-o-eye')
                    ->label('View File'),
                Tables\Actions\Action::make('edit_bill')
                    ->url(fn (Bill $record): string => route('filament.admin.resources.bills.edit', $record))
                    ->icon('heroicon-o-pencil')
                    ->label('Edit Bill')
                    ->color('primary'),
                Tables\Actions\Action::make('upload_doc')
                    ->url(fn (Bill $record): string => route('filament.admin.resources.bills.edit', $record))
                    ->icon('heroicon-o-cloud-arrow-up')
                    ->label('Upload Doc')
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
            'index' => Pages\ListBillsWithoutDocuments::route('/'),
        ];
    }
} 