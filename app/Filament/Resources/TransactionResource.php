<?php

namespace App\Filament\Resources;


use App\Filament\Resources\TransactionResource\Pages;
use App\Filament\Resources\TransactionResource\RelationManager\InvoiceRelationManager;
use App\Filament\Resources\TransactionResource\RelationManager\BillRelationManager;
use App\Models\Bill;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Patient;
use App\Models\Provider;
use App\Models\ProviderBranch;
use App\Models\Transaction;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\DB;
use Filament\Forms\Components\Actions\Action;
use Illuminate\Database\Eloquent\Model;
use App\Services\UploadTransactionToGoogleDrive;
use Filament\Notifications\Notification;
use Filament\Tables\Actions\Action as TableAction;

class TransactionResource extends Resource
{
    protected static ?string $model = Transaction::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';
    protected static ?string $navigationGroup = 'Finance';
    protected static ?int $navigationSort = 4;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('type')->options([
                    'Income' => 'Income',
                    'Outflow' => 'Outflow',
                    'Expense' => 'Expense',
                ])->required()->default(fn () => request()->get('type')),
                Forms\Components\Select::make('related_type')->options(fn ($get) => Self::relatedTypes($get('type')))->required()->searchable()->reactive()->default(fn () => request()->get('related_type')),
                    // I want to select an invoice if realted_type is Client
                Forms\Components\Select::make('related_id')->label('Client')->required()->options(Client::all()->pluck('company_name', 'id'))->visible(fn ($get) => $get('related_type') === 'Client')->searchable()->default(fn () => request()->get('related_id')),
                Forms\Components\Select::make('related_id')->label('Provider')->required()->options(Provider::all()->pluck('name', 'id'))->visible(fn ($get) => $get('related_type') === 'Provider')->searchable()->default(fn () => request()->get('related_id')),
                Forms\Components\Select::make('related_id')->label('Provider')->required()->options(ProviderBranch::all()->pluck('name', 'id'))->visible(fn ($get) => $get('related_type') === 'Branch')->searchable()->default(fn () => request()->get('related_id')),
                Forms\Components\Select::make('related_id')->label('Patient')->required()->options(Patient::all()->pluck('name', 'id'))->visible(fn ($get) => $get('related_type') === 'Patient')->searchable()->default(fn () => request()->get('related_id')),
                Forms\Components\Select::make('bank_account_id')->relationship('bankAccount', 'beneficiary_name')->required()->default(fn () => request()->get('bank_account_id')),
                Forms\Components\TextInput::make('name')->required()->maxLength(255)->default(fn () => request()->get('name')),
                Forms\Components\TextInput::make('amount')->required()->numeric()->prefix('€')->default(fn () => request()->get('amount')),
                Forms\Components\DatePicker::make('date')->required()->default(fn () => request()->get('date') ?? now()),
                Forms\Components\Textarea::make('notes')->columnSpanFull(),
                Forms\Components\FileUpload::make('attachment_path')
                    ->label('Upload Transaction Document')
                    ->acceptedFileTypes(['application/pdf', 'image/*'])
                    ->maxSize(10240) // 10MB
                    ->helperText('Upload the transaction document (PDF or image)'),
                Forms\Components\TextInput::make('bank_charges')
                ->numeric()
                ->prefix('€')
                ->maxValue(999999.99)
                ->default(0),

                Forms\Components\Toggle::make('charges_covered_by_client')
                ->default(false),

                // I want to have a table to select the related invoice or bill
                Forms\Components\Select::make('invoices')
                    ->label('Invoices')
                    ->relationship('invoices', 'name')
                    ->multiple()
                    ->searchable()
                    ->preload()
                    ->live()
                    ->visible(fn ($get) => $get('related_type') === 'Client')
                    ->default(fn () => request()->get('invoice_id') ? [request()->get('invoice_id')] : [])
                    ->options(function (callable $get) {
                        $clientId = $get('related_id');
                        if (!$clientId) return [];

                        return Invoice::query()
                            ->whereHas('patient', function ($q) use ($clientId) {
                                $q->where('client_id', $clientId);
                            })
                            ->where(function ($query) {
                                $query->whereDoesntHave('transactions');
                            })
                            ->pluck('name', 'id');
                    })
                    ->afterStateUpdated(function ($state, $record) {
                        if ($record && $state) {
                            $record->attachInvoices($state);
                        }
                    }),
                Forms\Components\Select::make('bills')
                ->relationship('bills', 'name')
                ->label('Bills')
                ->multiple()
                ->searchable()
                ->preload()
                ->live()
                ->visible(fn ($get) => $get('related_type') === 'Provider' || $get('related_type') === 'Branch')
                ->default(fn () => request()->get('bill_id') ? [request()->get('bill_id')] : [])
                ->options(function (callable $get) {
                    $relatedType = $get('related_type');
                    $relatedId = $get('related_id');
                    if (!$relatedId) return [];

                    return static::getBills($relatedType, $relatedId)
                        ->pluck('name', 'id')
                        ->toArray();
                })
                ->afterStateUpdated(function ($state, $record) {
                    if ($record && $state) {
                        $record->attachBills($state);
                    }
                }),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('date', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('name')->searchable(),
                Tables\Columns\TextColumn::make('bankAccount.beneficiary_name')->sortable(),
                Tables\Columns\TextColumn::make('related_type')->searchable(),
                Tables\Columns\TextColumn::make('related_id')->numeric()->sortable(),
                Tables\Columns\TextColumn::make('amount'),
                Tables\Columns\TextColumn::make('type')->searchable()
                ->color(fn ($record) => match ($record->type) {'Income' => 'success','Outflow' => 'warning','Expense' => 'danger',})->badge(),
                Tables\Columns\TextColumn::make('date')->date()->sortable(),
                Tables\Columns\TextColumn::make('attachment_path')->searchable(),
                Tables\Columns\TextColumn::make('bank_charges')->money()->sortable(),
                Tables\Columns\IconColumn::make('charges_covered_by_client')->label('Covered')->boolean()->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')->options(['Income' => 'Income', 'Outflow' => 'Outflow', 'Expense' => 'Expense'])->multiple(),
                Tables\Filters\SelectFilter::make('bank_account_id')->relationship('bankAccount', 'beneficiary_name')->multiple()->preload(),
            ])
            ->groups([
                Tables\Grouping\Group::make('date')
                    ->label('Month')
                    ->date()
                    ->collapsible()
                    ->getTitleFromRecordUsing(fn (Transaction $record): string => $record->date->format('F Y'))
                    ->getDescriptionFromRecordUsing(fn (Transaction $record) => $record->date->format('F Y') . ' Balance: ' . $record->bankAccount->monthlyBalance($record->date)),

            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                TableAction::make('upload_to_google_drive')
                    ->label('Upload to Google Drive')
                    ->icon('heroicon-o-cloud-arrow-up')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Upload Transaction to Google Drive')
                    ->modalDescription('This will upload the transaction document to the Google Drive folder associated with this transaction.')
                    ->modalSubmitActionLabel('Upload')
                    ->action(function (Transaction $record) {
                        // Check if there's an attachment
                        if (!$record->attachment_path) {
                            Notification::make()
                                ->danger()
                                ->title('No attachment found')
                                ->body('Please upload a document first before uploading to Google Drive.')
                                ->send();
                            return;
                        }

                        // Get the file content
                        $filePath = storage_path('app/public/' . $record->attachment_path);
                        if (!file_exists($filePath)) {
                            Notification::make()
                                ->danger()
                                ->title('File not found')
                                ->body('The uploaded file could not be found.')
                                ->send();
                            return;
                        }

                        $fileContent = file_get_contents($filePath);
                        $fileName = basename($record->attachment_path);

                        // Upload to Google Drive
                        $uploader = app(UploadTransactionToGoogleDrive::class);
                        $result = $uploader->uploadTransactionToGoogleDrive(
                            $fileContent,
                            $fileName,
                            $record
                        );

                        if ($result === false) {
                            Notification::make()
                                ->danger()
                                ->title('Upload failed')
                                ->body('Failed to upload to Google Drive. Check logs for more details.')
                                ->send();
                            return;
                        }

                        Notification::make()
                            ->success()
                            ->title('Upload successful')
                            ->body('Transaction has been uploaded to Google Drive successfully.')
                            ->send();
                    })
                    ->visible(fn (Transaction $record) => $record->attachment_path && !str_contains($record->attachment_path, 'drive.google.com')),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            InvoiceRelationManager::class,
            BillRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTransactions::route('/'),
            'create' => Pages\CreateTransaction::route('/create'),
            'edit' => Pages\EditTransaction::route('/{record}/edit'),
        ];
    }

    public function relatedInvoices()
    {
        // invocies will pass an array of invoice ids
        return $this->hasMany(Invoice::class);
    }

    public function relatedBills()
    {
        return $this->hasMany(Bill::class);
    }


    public static function getBills($relatedType, $relatedId)
    {
        $bills = collect();
        if ($relatedType === 'Provider') {
            $bills = Bill::query()
                ->whereHas('file', function ($query) use ($relatedId) {
                    $query->whereHas('provider', function ($providerQuery) use ($relatedId) {
                        $providerQuery->where('providers.id', $relatedId);
                    })
                    ->orWhereHas('providerBranch', function ($branchQuery) use ($relatedId) {
                        $branchQuery->where('provider_branches.provider_id', $relatedId);
                    });
                })
                ->whereDoesntHave('transactions')
                ->get();
        } elseif ($relatedType === 'Branch') {
            $bills = Bill::query()
                ->whereHas('file', function ($query) use ($relatedId) {
                    $query->where('provider_branch_id', $relatedId);
                })
                ->whereDoesntHave('transactions')
                ->get();
        }

        return $bills;
    }

    public static function relatedTypes($type)
    {
        return match ($type) {
            'Income' => [
                'Client' => 'Client',
                'Patient' => 'Patient',
            ],
            'Outflow' => [
                'Provider' => 'Provider',
                'Branch' => 'Branch',
            ],
            'Expense' => [
                'Provider' => 'Provider',
                'Branch' => 'Branch',
            ],
            default => [],
        };
    }
}
