<?php

namespace App\Filament\Resources;

use App\Filament\Resources\BillWithoutTransactionResource\Pages;
use App\Models\Bill;
use App\Models\BankAccount;
use Filament\Forms;
use Filament\Tables\Grouping\Group;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Table;
use Filament\Tables;
use Filament\Tables\Columns\Summarizers\Count;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\BadgeColumn;
use Illuminate\Database\Eloquent\Builder;
use App\Services\UploadBillToGoogleDrive;
use Filament\Notifications\Notification;
use Filament\Tables\Actions\Action;

class BillWithoutTransactionResource extends Resource
{
    protected static ?string $model = Bill::class;
    protected static ?string $navigationIcon = 'heroicon-o-exclamation-triangle';
    protected static ?int $navigationSort = 8;
    protected static ?string $navigationGroup = 'Stages';
    protected static ?string $navigationLabel = 'Bills without transaction';
    protected static ?string $modelLabel = 'Bill Without Transaction';
    protected static ?string $pluralModelLabel = 'Bills Without Transactions';

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::where('status', 'Paid')
            ->whereDoesntHave('transactions')
            ->count();
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('status', 'Paid')
            ->whereDoesntHave('transactions');
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
                            ->dehydrated()
                            ->helperText('Bill name will be auto-generated based on file reference and sequence'),
                        Forms\Components\Select::make('file_id')
                            ->relationship('file', 'mga_reference')
                            ->required()
                            ->searchable()
                            ->default(fn () => request()->get('file_id'))
                            ->preload()
                            ->live(),
                        Forms\Components\Select::make('provider_id')
                            ->relationship('provider', 'name')
                            ->searchable()
                            ->preload()
                            ->nullable()
                            ->disabled()
                            ->live()
                            ->dehydrated()
                            ->reactive()
                            ->afterStateHydrated(function ($state, $set, $get) {
                                $fileId = $get('file_id');
                                if ($fileId) {
                                    $file = \App\Models\File::find($fileId);
                                    if ($file && $file->providerBranch && $file->providerBranch->provider_id) {
                                        $set('provider_id', $file->providerBranch->provider_id);
                                    }
                                }
                            }),
                        Forms\Components\Select::make('branch_id')
                            ->relationship('branch', 'branch_name')
                            ->searchable()
                            ->preload()
                            ->nullable()
                            ->disabled()
                            ->live()
                            ->dehydrated()
                            ->reactive()
                            ->afterStateHydrated(function ($state, $set, $get) {
                                $fileId = $get('file_id');
                                if ($fileId) {
                                    $file = \App\Models\File::find($fileId);
                                    if ($file && $file->provider_branch_id) {
                                        $set('branch_id', $file->provider_branch_id);
                                    }
                                }
                            }),
                        Forms\Components\Select::make('bank_account_id')
                            ->relationship('bankAccount', 'beneficiary_name')
                            ->options(function () {
                                return BankAccount::where('type', 'Internal')->pluck('beneficiary_name', 'id');
                            })
                            ->nullable(),
                        Forms\Components\DatePicker::make('bill_date')->default(now()->format('Y-m-d')),
                        Forms\Components\Select::make('status')
                            ->options([
                                'Unpaid' => 'Unpaid',
                                'Partial' => 'Partial',
                                'Paid' => 'Paid',
                            ])->default('Unpaid')
                            ->required(),
                        Forms\Components\TextInput::make('bill_google_link')
                            ->label('Google Drive Link')
                            ->helperText('Google Drive link for this bill'),
                    ])->columnSpan(['lg' => 2]),
                Forms\Components\Card::make()
                    ->schema([
                        Forms\Components\Placeholder::make('created_at')->label('Created at')->content(fn (?Bill $record): string => $record ? $record->created_at->diffForHumans() : '-'),
                        Forms\Components\Placeholder::make('due_date')->label('Due date')->content(fn (?Bill $record): string => $record ? '(' . $record->due_date->format('d/m/Y') . ')' . ' - ' . abs((int)$record->due_date->diffInDays(now())) . ' days' : '-'),
                        Forms\Components\Placeholder::make('subtotal')->label('Subtotal')->content(fn (?Bill $record): string => $record ? '€'.number_format($record->subtotal, 2) : '0.00'),
                        Forms\Components\Placeholder::make('discount')->label('Discount')->content(fn (?Bill $record): string => $record ? '€'.number_format($record->discount, 2) : '0.00'),
                        Forms\Components\Placeholder::make('total_amount')->label('Total Amount')->content(fn (?Bill $record): string => $record ? '€'.number_format($record->total_amount, 2) : '0.00'),
                    ])->columnSpan(['lg' => 1]),
            ])
            ->columns(3);
    }

    public static function table(Table $table): Table
    {
        return $table->groups([
            Group::make('provider.name')->label('Provider')->collapsible(),
            Group::make('branch.branch_name')->label('Branch')->collapsible(),
        ])
            ->defaultSort('bill_date', 'desc')
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
                Tables\Columns\TextColumn::make('file.service_date')
                    ->label('Service Date')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('due_date')->date()->sortable(),
                Tables\Columns\BadgeColumn::make('status')->colors(['danger' => 'Unpaid','success' => 'Paid','primary' => 'Partial',])->summarize(Count::make('status')->label('Number of Bills')),
                Tables\Columns\TextColumn::make('total_amount')->money('EUR')->sortable()->summarize(Sum::make('total_amount')->label('Total Amount')->prefix('€')),
                Tables\Columns\TextColumn::make('paid_amount')->money('EUR')->sortable()->summarize(Sum::make('paid_amount')->label('Paid Amount')->prefix('€')),
                Tables\Columns\TextColumn::make('remaining_amount')->money('EUR')->sortable()->state(fn (Bill $record) => $record->total_amount - $record->paid_amount),
                Tables\Columns\TextColumn::make('file.status')->label('File Status')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('bill_google_link')
                    ->label('Google Drive')
                    ->url(fn (Bill $record) => $record->bill_google_link)
                    ->openUrlInNewTab()
                    ->toggleable(isToggledHiddenByDefault: true),
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
                        'Paid' => 'Paid',
                        'Partial' => 'Partial',
                    ]),
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
                    }),
                Tables\Filters\Filter::make('missing_document')
                    ->label('Missing Document')
                    ->form([
                        Forms\Components\Checkbox::make('missing_document')
                            ->label('Missing Document')
                            ->default(false)
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query->when(
                            $data['missing_document'] ?? false,
                            fn (Builder $query): Builder => $query->whereNull('bill_google_link')->orWhere('bill_google_link', '=', '')
                        );
                    })
                    ->indicateUsing(function (array $data): ?string {
                        if ($data['missing_document'] ?? false) {
                            return 'Missing Document';
                        }
                        return null;
                    })
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->url(fn (Bill $record) => route('filament.admin.resources.bills.edit', $record)),
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
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBillWithoutTransactions::route('/'),
            'create' => Pages\CreateBillWithoutTransaction::route('/create'),
        ];
    }
} 