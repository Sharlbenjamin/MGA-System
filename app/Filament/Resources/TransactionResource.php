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
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Filament\Tables\Actions\Action;
use Illuminate\Database\Eloquent\Model;

use Filament\Notifications\Notification;


class TransactionResource extends Resource
{
    protected static ?string $model = Transaction::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';
    protected static ?string $navigationGroup = 'Finance';
    protected static ?int $navigationSort = 5;
    protected static ?string $recordTitleAttribute = 'name';



    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('type')->options([
                    'Income' => 'Income',
                    'Outflow' => 'Outflow',
                    'Expense' => 'Expense',
                ])->required()->default(function () {
                    $type = request()->get('type');
                    $allParams = request()->all();
                    Log::info('Transaction form defaults:', [
                        'type' => $type,
                        'all_params' => $allParams,
                        'url' => request()->url()
                    ]);
                    return $type;
                }),
                Forms\Components\Select::make('related_type')->options(fn ($get) => Self::relatedTypes($get('type')))->required()->searchable()->reactive()->default(fn () => request()->get('related_type')),
                    // I want to select an invoice if realted_type is Client
                Forms\Components\Select::make('related_id')->label('Client')->required()->options(Client::all()->pluck('company_name', 'id'))->visible(fn ($get) => $get('related_type') === 'Client')->searchable()->default(fn () => request()->get('related_id')),
                Forms\Components\Select::make('related_id')->label('Provider')->required()->options(Provider::all()->pluck('name', 'id'))->visible(fn ($get) => $get('related_type') === 'Provider')->searchable()->default(fn () => request()->get('related_id')),
                Forms\Components\Select::make('related_id')->label('Provider')->required()->options(ProviderBranch::all()->pluck('name', 'id'))->visible(fn ($get) => $get('related_type') === 'Branch')->searchable()->default(fn () => request()->get('related_id')),
                Forms\Components\Select::make('related_id')->label('Patient')->required()->options(Patient::all()->pluck('name', 'id'))->visible(fn ($get) => $get('related_type') === 'Patient')->searchable()->default(fn () => request()->get('related_id')),
                Forms\Components\Select::make('bank_account_id')
                    ->relationship('bankAccount', 'beneficiary_name', function ($query) {
                        return $query->where('type', 'Internal');
                    })
                    ->required()
                    ->default(fn () => request()->get('bank_account_id')),
                
                // Display provider/branch bank account details for Outflow transactions
                Forms\Components\Section::make('Provider Bank Account Details')
                    ->description('Bank account information for the selected provider/branch (for Outflow transactions)')
                    ->schema([
                        Forms\Components\Placeholder::make('provider_bank_details')
                            ->label('Bank Account Information')
                            ->reactive()
                            ->content(function (callable $get) {
                                $type = $get('type');
                                $relatedType = $get('related_type');
                                $relatedId = $get('related_id');
                                
                                if ($type !== 'Outflow' || !$relatedId) {
                                    return 'Select "Outflow" as transaction type and choose a provider/branch to see bank details.';
                                }
                                
                                $bankAccount = null;
                                
                                if ($relatedType === 'Provider') {
                                    $provider = \App\Models\Provider::find($relatedId);
                                    $bankAccount = $provider?->bankAccounts()->first();
                                } elseif ($relatedType === 'Branch') {
                                    $branch = \App\Models\ProviderBranch::find($relatedId);
                                    $bankAccount = $branch?->bankAccounts()->first();
                                }
                                
                                if (!$bankAccount) {
                                    return 'No bank account found for the selected provider/branch.';
                                }
                                
                                // Get bills for the transaction reason
                                $bills = collect();
                                if ($relatedType === 'Provider') {
                                    $bills = \App\Models\Bill::query()
                                        ->whereHas('file', function ($query) use ($relatedId) {
                                            $query->whereHas('provider', function ($providerQuery) use ($relatedId) {
                                                $providerQuery->where('providers.id', $relatedId);
                                            })
                                            ->orWhereHas('providerBranch', function ($branchQuery) use ($relatedId) {
                                                $branchQuery->where('provider_branches.provider_id', $relatedId);
                                            });
                                        })
                                        ->whereIn('status', ['Unpaid', 'Partial'])
                                        ->get();
                                } elseif ($relatedType === 'Branch') {
                                    $bills = \App\Models\Bill::query()
                                        ->whereHas('file', function ($query) use ($relatedId) {
                                            $query->where('provider_branch_id', $relatedId);
                                        })
                                        ->whereIn('status', ['Unpaid', 'Partial'])
                                        ->get();
                                }
                                
                                $billNames = $bills->pluck('name')->implode(', ');
                                $reason = $billNames ? "Payment for {$billNames}" : "Payment for services";
                                
                                $details = [
                                    'IBAN: ' . $bankAccount->iban,
                                    'Beneficiary Name: ' . $bankAccount->beneficiary_name,
                                    'SWIFT: ' . $bankAccount->swift,
                                    'Country: ' . ($bankAccount->country?->name ?? 'N/A'),
                                    'Reason: ' . $reason
                                ];
                                
                                return implode("\n", $details);
                            })
                            ->visible(fn ($get) => $get('type') === 'Outflow')
                            ->columnSpanFull(),
                        
                        // Individual copiable fields for each bank detail
                        Forms\Components\TextInput::make('provider_iban')
                            ->label('IBAN')
                            ->default(function (callable $get) {
                                $relatedType = $get('related_type');
                                $relatedId = $get('related_id');
                                
                                if ($relatedType === 'Provider') {
                                    $provider = \App\Models\Provider::find($relatedId);
                                    return $provider?->bankAccounts()->first()?->iban ?? '';
                                } elseif ($relatedType === 'Branch') {
                                    $branch = \App\Models\ProviderBranch::find($relatedId);
                                    return $branch?->bankAccounts()->first()?->iban ?? '';
                                }
                                return '';
                            })
                            ->disabled()
                            ->copyable()
                            ->reactive()
                            ->visible(fn ($get) => $get('type') === 'Outflow'),
                        
                        Forms\Components\TextInput::make('provider_beneficiary')
                            ->label('Beneficiary Name')
                            ->default(function (callable $get) {
                                $relatedType = $get('related_type');
                                $relatedId = $get('related_id');
                                
                                if ($relatedType === 'Provider') {
                                    $provider = \App\Models\Provider::find($relatedId);
                                    return $provider?->bankAccounts()->first()?->beneficiary_name ?? '';
                                } elseif ($relatedType === 'Branch') {
                                    $branch = \App\Models\ProviderBranch::find($relatedId);
                                    return $branch?->bankAccounts()->first()?->beneficiary_name ?? '';
                                }
                                return '';
                            })
                            ->disabled()
                            ->copyable()
                            ->reactive()
                            ->visible(fn ($get) => $get('type') === 'Outflow'),
                        
                        Forms\Components\TextInput::make('provider_swift')
                            ->label('SWIFT')
                            ->default(function (callable $get) {
                                $relatedType = $get('related_type');
                                $relatedId = $get('related_id');
                                
                                if ($relatedType === 'Provider') {
                                    $provider = \App\Models\Provider::find($relatedId);
                                    return $provider?->bankAccounts()->first()?->swift ?? '';
                                } elseif ($relatedType === 'Branch') {
                                    $branch = \App\Models\ProviderBranch::find($relatedId);
                                    return $branch?->bankAccounts()->first()?->swift ?? '';
                                }
                                return '';
                            })
                            ->disabled()
                            ->copyable()
                            ->reactive()
                            ->visible(fn ($get) => $get('type') === 'Outflow'),
                        
                        Forms\Components\TextInput::make('provider_country')
                            ->label('Country')
                            ->default(function (callable $get) {
                                $relatedType = $get('related_type');
                                $relatedId = $get('related_id');
                                
                                if ($relatedType === 'Provider') {
                                    $provider = \App\Models\Provider::find($relatedId);
                                    return $provider?->bankAccounts()->first()?->country?->name ?? '';
                                } elseif ($relatedType === 'Branch') {
                                    $branch = \App\Models\ProviderBranch::find($relatedId);
                                    return $branch?->bankAccounts()->first()?->country?->name ?? '';
                                }
                                return '';
                            })
                            ->disabled()
                            ->copyable()
                            ->reactive()
                            ->visible(fn ($get) => $get('type') === 'Outflow'),
                        
                        Forms\Components\TextInput::make('provider_reason')
                            ->label('Transaction Reason')
                            ->default(function (callable $get) {
                                $relatedType = $get('related_type');
                                $relatedId = $get('related_id');
                                
                                // Get bills for the transaction reason
                                $bills = collect();
                                if ($relatedType === 'Provider') {
                                    $bills = \App\Models\Bill::query()
                                        ->whereHas('file', function ($query) use ($relatedId) {
                                            $query->whereHas('provider', function ($providerQuery) use ($relatedId) {
                                                $providerQuery->where('providers.id', $relatedId);
                                            })
                                            ->orWhereHas('providerBranch', function ($branchQuery) use ($relatedId) {
                                                $branchQuery->where('provider_branches.provider_id', $relatedId);
                                            });
                                        })
                                        ->whereIn('status', ['Unpaid', 'Partial'])
                                        ->get();
                                } elseif ($relatedType === 'Branch') {
                                    $bills = \App\Models\Bill::query()
                                        ->whereHas('file', function ($query) use ($relatedId) {
                                            $query->where('provider_branch_id', $relatedId);
                                        })
                                        ->whereIn('status', ['Unpaid', 'Partial'])
                                        ->get();
                                }
                                
                                $billNames = $bills->pluck('name')->implode(', ');
                                return $billNames ? "Payment for {$billNames}" : "Payment for services";
                            })
                            ->disabled()
                            ->copyable()
                            ->reactive()
                            ->visible(fn ($get) => $get('type') === 'Outflow'),
                    ])
                    ->visible(fn ($get) => $get('type') === 'Outflow' && in_array($get('related_type'), ['Provider', 'Branch']))
                    ->collapsible()
                    ->collapsed(false),
                Forms\Components\TextInput::make('name')->required()->maxLength(255)->default(fn () => request()->get('name')),
                Forms\Components\TextInput::make('amount')->required()->numeric()->prefix('€')->default(fn () => request()->get('amount')),
                Forms\Components\DatePicker::make('date')->required()->default(fn () => request()->get('date') ?? now()),
                Forms\Components\Textarea::make('notes')->columnSpanFull(),
                Forms\Components\TextInput::make('attachment_path')
                    ->label('Link or Text')
                    ->placeholder('Enter a Google Drive link, any URL, or any text here...')
                    ->helperText('You can enter a Google Drive link, any URL (will be clickable), or any text.')
                    ->maxLength(1000),

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
                    }),
                Forms\Components\Select::make('bills')
                ->label('Bills')
                ->multiple()
                ->searchable()
                ->preload()
                ->live()
                ->visible(fn ($get) => $get('related_type') === 'Provider' || $get('related_type') === 'Branch')
                ->default(function ($record = null) {
                    // Handle bill_ids parameter (comma-separated list from bulk action)
                    if (request()->get('bill_ids')) {
                        $billIds = explode(',', request()->get('bill_ids'));
                        return array_filter($billIds); // Remove empty values
                    }
                    
                    // Handle single bill_id parameter
                    if (request()->get('bill_id')) {
                        return [request()->get('bill_id')];
                    }
                    
                    // If editing, get the currently attached bill IDs
                    if ($record && $record->exists) {
                        return $record->bills()->pluck('bills.id')->toArray();
                    }
                    
                    return [];
                })
                ->options(function (callable $get, $record = null) {
                    $relatedType = $get('related_type');
                    $relatedId = $get('related_id');
                    
                    // If we're editing and have a record, include currently attached bills
                    if ($record && $record->exists) {
                        $attachedBillIds = $record->bills()->pluck('bills.id')->toArray();
                        
                        // Get all bills for the related provider/branch
                        $allBills = collect();
                        if ($relatedType === 'Provider') {
                            $allBills = Bill::query()
                                ->whereHas('file', function ($query) use ($relatedId) {
                                    $query->whereHas('provider', function ($providerQuery) use ($relatedId) {
                                        $providerQuery->where('providers.id', $relatedId);
                                    })
                                    ->orWhereHas('providerBranch', function ($branchQuery) use ($relatedId) {
                                        $branchQuery->where('provider_branches.provider_id', $relatedId);
                                    });
                                })
                                ->where(function ($query) use ($attachedBillIds) {
                                    $query->whereIn('status', ['Unpaid', 'Partial'])
                                        ->orWhereIn('id', $attachedBillIds);
                                })
                                ->get();
                        } elseif ($relatedType === 'Branch') {
                            $allBills = Bill::query()
                                ->whereHas('file', function ($query) use ($relatedId) {
                                    $query->where('provider_branch_id', $relatedId);
                                })
                                ->where(function ($query) use ($attachedBillIds) {
                                    $query->whereIn('status', ['Unpaid', 'Partial'])
                                        ->orWhereIn('id', $attachedBillIds);
                                })
                                ->get();
                        }
                        
                        return $allBills->pluck('name', 'id')->toArray();
                    }
                    
                    // For create mode, use the original logic
                    if (!$relatedId) return [];

                    return static::getBills($relatedType, $relatedId)
                        ->pluck('name', 'id')
                        ->toArray();
                }),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with([
                'bankAccount',
                'invoices.file.patient.client',
                'bills.file.patient.client'
            ]))
            ->defaultSort('date', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('name')->searchable(),
                Tables\Columns\TextColumn::make('bankAccount.beneficiary_name')->sortable(),
                Tables\Columns\TextColumn::make('related_type')->searchable(),
                Tables\Columns\TextColumn::make('related_id')->numeric()->sortable(),
                Tables\Columns\TextColumn::make('amount'),
                Tables\Columns\TextColumn::make('type')->searchable()
                ->color(fn ($record) => match ($record->type) {'Income' => 'success','Outflow' => 'warning','Expense' => 'danger',})->badge(),
                Tables\Columns\BadgeColumn::make('status')
                    ->colors([
                        'Draft' => 'gray',
                        'Completed' => 'success',
                        'Pending' => 'warning',
                    ])
                    ->default('Completed'),
                Tables\Columns\TextColumn::make('date')->date()->sortable(),
                Tables\Columns\TextColumn::make('client_reference')
                    ->label('Client Reference')
                    ->formatStateUsing(function ($record) {
                        // For Income transactions, try to get client from invoices
                        if ($record->type === 'Income' && $record->invoices->isNotEmpty()) {
                            $firstInvoice = $record->invoices->first();
                            if ($firstInvoice->file && $firstInvoice->file->patient && $firstInvoice->file->patient->client) {
                                return $firstInvoice->file->patient->client->company_name;
                            }
                        }
                        
                        // For Outflow/Expense transactions, try to get client from bills
                        if (in_array($record->type, ['Outflow', 'Expense']) && $record->bills->isNotEmpty()) {
                            $firstBill = $record->bills->first();
                            if ($firstBill->file && $firstBill->file->patient && $firstBill->file->patient->client) {
                                return $firstBill->file->patient->client->company_name;
                            }
                        }
                        
                        return 'N/A';
                    })
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('attachment_path')
                    ->label('Link/Text')
                    ->searchable()
                    ->formatStateUsing(function ($record) {
                        if (!$record->attachment_path) {
                            return 'No Link/Text';
                        }
                        
                        // Check if it's a Google Drive link
                        if ($record->isGoogleDriveAttachment()) {
                            return 'View Document';
                        }
                        
                        // Check if it's any other URL
                        if (filter_var($record->attachment_path, FILTER_VALIDATE_URL)) {
                            return 'View Link';
                        }
                        
                        // Return truncated text if it's not a URL
                        return strlen($record->attachment_path) > 30 
                            ? substr($record->attachment_path, 0, 30) . '...' 
                            : $record->attachment_path;
                    })
                    ->action(function ($state, $record) {
                        if ($record->attachment_path && filter_var($record->attachment_path, FILTER_VALIDATE_URL)) {
                            return $record->attachment_path;
                        }
                        return null;
                    })
                    ->openUrlInNewTab()
                    ->icon(fn ($record) => !$record->attachment_path ? 'heroicon-o-document' : (($record->isGoogleDriveAttachment() || filter_var($record->attachment_path, FILTER_VALIDATE_URL)) ? 'heroicon-o-link' : 'heroicon-o-document-text'))
                    ->color(fn ($record) => !$record->attachment_path ? 'gray' : (($record->isGoogleDriveAttachment() || filter_var($record->attachment_path, FILTER_VALIDATE_URL)) ? 'primary' : 'gray')),
                Tables\Columns\TextColumn::make('bank_charges')->money()->sortable(),
                Tables\Columns\IconColumn::make('charges_covered_by_client')->label('Covered')->boolean()->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')->options(['Income' => 'Income', 'Outflow' => 'Outflow', 'Expense' => 'Expense'])->multiple(),
                Tables\Filters\SelectFilter::make('status')->options(['Draft' => 'Draft', 'Completed' => 'Completed', 'Pending' => 'Pending'])->multiple(),
                Tables\Filters\SelectFilter::make('bank_account_id')->relationship('bankAccount', 'beneficiary_name')->multiple()->preload(),
                Tables\Filters\Filter::make('missing_documents')
                    ->label('Missed Documents')
                    ->form([
                        Forms\Components\Checkbox::make('show_missing_documents')
                            ->label('Missing Documents')
                            ->default(true)
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['show_missing_documents'] ?? true,
                                fn (Builder $query): Builder => $query->where('type', 'Outflow')->whereNull('attachment_path')
                            );
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];
                        
                        if ($data['show_missing_documents'] ?? true) {
                            $indicators['missing_documents'] = 'Missed Documents (Outflow)';
                        }
                        
                        return $indicators;
                    }),
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
                Tables\Actions\ViewAction::make(),
                Action::make('uploadDocument')
                    ->label('Upload Document')
                    ->icon('heroicon-o-document-arrow-up')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Upload Transaction Document')
                    ->modalDescription('Upload the transaction document to Google Drive.')
                    ->modalSubmitActionLabel('Upload')
                    ->form([
                        Forms\Components\FileUpload::make('transaction_document_main')
                            ->label('Upload Transaction Document')
                            ->acceptedFileTypes(['application/pdf', 'image/*'])
                            ->maxSize(10240) // 10MB
                            ->required()
                            ->disk('public')
                            ->directory('transactions')
                            ->visibility('public')
                            ->helperText('Upload the transaction document (PDF or image)')
                            ->storeFileNamesIn('original_filename')
                            ->downloadable()
                            ->openable()
                            ->preserveFilenames()
                            ->maxFiles(1),
                    ])
                    ->action(function ($record, array $data = []) {
                        try {
                            if (!isset($data['transaction_document_main']) || empty($data['transaction_document_main'])) {
                                Notification::make()
                                    ->danger()
                                    ->title('No document uploaded')
                                    ->body('Please upload a document first.')
                                    ->send();
                                return;
                            }

                            // Handle the uploaded file properly
                            $uploadedFile = $data['transaction_document_main'];
                            
                            // Log the uploaded file data for debugging
                            Log::info('Transaction upload file data:', ['data' => $data, 'uploadedFile' => $uploadedFile]);
                            
                            // If it's an array (multiple files), take the first one
                            if (is_array($uploadedFile)) {
                                $uploadedFile = $uploadedFile[0] ?? null;
                            }
                            
                            if (!$uploadedFile) {
                                Notification::make()
                                    ->danger()
                                    ->title('Invalid file data')
                                    ->body('The uploaded file data is invalid.')
                                    ->send();
                                return;
                            }

                            // Handle the uploaded file properly using Storage facade
                            try {
                                // Get the file content using Storage facade
                                $content = Storage::disk('public')->get($uploadedFile);
                                
                                if ($content === false) {
                                    Log::error('Transaction file not found in storage:', ['path' => $uploadedFile]);
                                    Notification::make()
                                        ->danger()
                                        ->title('File not found')
                                        ->body('The uploaded file could not be found in storage.')
                                        ->send();
                                    return;
                                }
                                
                                // Generate the proper filename format
                                $originalExtension = pathinfo($uploadedFile, PATHINFO_EXTENSION);
                                $fileName = 'Transaction ' . $record->name . ' - ' . $record->date->format('Y-m-d') . '.' . $originalExtension;
                                Log::info('Transaction file successfully read:', ['fileName' => $fileName, 'size' => strlen($content)]);
                                
                                // Here you would upload to Google Drive
                                // For now, we'll just update the transaction with the file path
                                $record->attachment_path = $uploadedFile;
                                $record->save();
                                
                                Notification::make()
                                    ->success()
                                    ->title('Transaction document uploaded successfully')
                                    ->body('Transaction document has been uploaded.')
                                    ->send();
                                    
                            } catch (\Exception $e) {
                                Log::error('Transaction file access error:', ['error' => $e->getMessage(), 'path' => $uploadedFile]);
                                Notification::make()
                                    ->danger()
                                    ->title('File access error')
                                    ->body('Error accessing uploaded file: ' . $e->getMessage())
                                    ->send();
                                return;
                            }
                        } catch (\Exception $e) {
                            Log::error('Transaction upload error:', ['error' => $e->getMessage(), 'record' => $record->id]);
                            Notification::make()
                                ->danger()
                                ->title('Upload error')
                                ->body('An error occurred during upload: ' . $e->getMessage())
                                ->send();
                        }
                    }),
                Tables\Actions\EditAction::make(),
                Action::make('finalizeTransaction')
                    ->label('Finalize Transaction')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Finalize Transaction')
                    ->modalDescription('This will mark all attached bills as paid and complete the transaction. This action cannot be undone.')
                    ->modalSubmitActionLabel('Finalize')
                    ->visible(fn ($record) => $record->status === 'Draft')
                    ->action(function ($record) {
                        try {
                            $record->finalizeTransaction();
                            
                            Notification::make()
                                ->success()
                                ->title('Transaction Finalized')
                                ->body('Transaction has been finalized and bills have been marked as paid.')
                                ->send();
                                
                        } catch (\Exception $e) {
                            Notification::make()
                                ->danger()
                                ->title('Finalization Failed')
                                ->body('Error finalizing transaction: ' . $e->getMessage())
                                ->send();
                        }
                    }),
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
            'view' => Pages\ViewTransaction::route('/{record}'),
        ];
    }

    public static function getGlobalSearchResultTitle(\Illuminate\Database\Eloquent\Model $record): string
    {
        return ($record->name ?? 'Unknown') . ' (' . ($record->type ?? 'Unknown') . ')';
    }

    public static function getGlobalSearchResultDetails(\Illuminate\Database\Eloquent\Model $record): array
    {
        return [
            'Type' => $record->type ?? 'Unknown',
            'Related Type' => $record->related_type ?? 'Unknown',
            'Amount' => '€' . number_format($record->amount ?? 0, 2),
            'Date' => $record->date?->format('d/m/Y') ?? 'Unknown',
            'Bank Account' => $record->bankAccount?->beneficiary_name ?? 'Unknown',
        ];
    }

    public static function getGlobalSearchEloquentQuery(): Builder
    {
        return parent::getGlobalSearchEloquentQuery()
            ->with(['bankAccount']);
    }

    public static function getGlobalSearchResultUrl(\Illuminate\Database\Eloquent\Model $record): string
    {
        return TransactionResource::getUrl('edit', ['record' => $record]);
    }

    public static function isGlobalSearchDisabled(): bool
    {
        return true;
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
                ->whereIn('status', ['Unpaid', 'Partial'])
                ->get();
        } elseif ($relatedType === 'Branch') {
            $bills = Bill::query()
                ->whereHas('file', function ($query) use ($relatedId) {
                    $query->where('provider_branch_id', $relatedId);
                })
                ->whereDoesntHave('transactions')
                ->whereIn('status', ['Unpaid', 'Partial'])
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
