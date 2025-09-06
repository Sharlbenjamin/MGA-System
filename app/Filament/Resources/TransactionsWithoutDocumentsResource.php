<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TransactionsWithoutDocumentsResource\Pages;
use App\Models\Transaction;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class TransactionsWithoutDocumentsResource extends Resource
{
    protected static ?string $model = Transaction::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-magnifying-glass';
    protected static ?string $navigationGroup = 'Workflow';
    protected static ?int $navigationSort = 9;
    protected static ?string $navigationLabel = 'Transaction without documents';
    protected static ?string $modelLabel = 'Transaction without documents';
    protected static ?string $pluralModelLabel = 'Transactions without documents';

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::whereNull('attachment_path')->orWhere('attachment_path', '')->count();
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
                Forms\Components\Select::make('bank_account_id')
                    ->relationship('bankAccount', 'beneficiary_name')
                    ->required(),
                Forms\Components\Select::make('related_type')
                    ->options([
                        'Client' => 'Client',
                        'Patient' => 'Patient',
                        'Provider' => 'Provider',
                        'Branch' => 'Branch',
                    ])
                    ->required(),
                Forms\Components\TextInput::make('amount')
                    ->numeric()
                    ->required(),
                Forms\Components\Select::make('type')
                    ->options([
                        'Income' => 'Income',
                        'Outflow' => 'Outflow',
                        'Expense' => 'Expense',
                    ])
                    ->required(),
                Forms\Components\DatePicker::make('date')
                    ->required(),
                Forms\Components\Textarea::make('notes')
                    ->maxLength(65535)
                    ->columnSpanFull(),
                Forms\Components\TextInput::make('attachment_path')
                    ->label('Link or Text')
                    ->maxLength(1000),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->whereNull('attachment_path')
                ->orWhere('attachment_path', '')
                ->with(['bankAccount', 'invoices.file.patient.client', 'bills.file.patient.client']))
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('bankAccount.beneficiary_name')
                    ->label('Bank Account')
                    ->searchable()
                    ->sortable(),
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
                Tables\Columns\TextColumn::make('related_type')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'Client' => 'success',
                        'Patient' => 'info',
                        'Provider' => 'warning',
                        'Branch' => 'warning',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('amount')
                    ->money('EUR')
                    ->sortable(),
                Tables\Columns\TextColumn::make('type')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'Income' => 'success',
                        'Outflow' => 'danger',
                        'Expense' => 'warning',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('date')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->options([
                        'Income' => 'Income',
                        'Outflow' => 'Outflow',
                        'Expense' => 'Expense',
                    ]),
                Tables\Filters\SelectFilter::make('related_type')
                    ->options([
                        'Client' => 'Client',
                        'Patient' => 'Patient',
                        'Provider' => 'Provider',
                        'Branch' => 'Branch',
                    ]),
            ])
            ->actions([
                Tables\Actions\Action::make('edit_transaction')
                    ->url(fn (Transaction $record): string => route('filament.admin.resources.transactions.edit', $record))
                    ->icon('heroicon-o-pencil')
                    ->label('Edit Transaction')
                    ->color('primary'),
                Tables\Actions\Action::make('upload_document')
                    ->label('Upload Document')
                    ->icon('heroicon-o-document-arrow-up')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Upload Transaction Document')
                    ->modalDescription('Upload the transaction document to Google Drive.')
                    ->modalSubmitActionLabel('Upload')
                    ->form([
                        Forms\Components\FileUpload::make('transaction_document')
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
                            if (!isset($data['transaction_document']) || empty($data['transaction_document'])) {
                                \Filament\Notifications\Notification::make()
                                    ->danger()
                                    ->title('No document uploaded')
                                    ->body('Please upload a document first.')
                                    ->send();
                                return;
                            }

                            // Handle the uploaded file properly
                            $uploadedFile = $data['transaction_document'];
                            
                            // Log the uploaded file data for debugging
                            \Illuminate\Support\Facades\Log::info('Transaction upload file data:', ['data' => $data, 'uploadedFile' => $uploadedFile]);
                            
                            // If it's an array (multiple files), take the first one
                            if (is_array($uploadedFile)) {
                                $uploadedFile = $uploadedFile[0] ?? null;
                            }
                            
                            if (!$uploadedFile) {
                                \Filament\Notifications\Notification::make()
                                    ->danger()
                                    ->title('Invalid file data')
                                    ->body('The uploaded file data is invalid.')
                                    ->send();
                                return;
                            }

                            // Handle the uploaded file properly using Storage facade
                            try {
                                // Get the file content using Storage facade
                                $content = \Illuminate\Support\Facades\Storage::disk('public')->get($uploadedFile);
                                
                                if ($content === false) {
                                    \Illuminate\Support\Facades\Log::error('Transaction file not found in storage:', ['path' => $uploadedFile]);
                                    \Filament\Notifications\Notification::make()
                                        ->danger()
                                        ->title('File not found')
                                        ->body('The uploaded file could not be found in storage.')
                                        ->send();
                                    return;
                                }
                                
                                // Generate the proper filename format
                                $originalExtension = pathinfo($uploadedFile, PATHINFO_EXTENSION);
                                $fileName = 'Transaction ' . $record->name . ' - ' . $record->date->format('Y-m-d') . '.' . $originalExtension;
                                \Illuminate\Support\Facades\Log::info('Transaction file successfully read:', ['fileName' => $fileName, 'size' => strlen($content)]);
                                
                                // Here you would upload to Google Drive
                                // For now, we'll just update the transaction with the file path
                                $record->attachment_path = $uploadedFile;
                                $record->save();
                                
                                \Filament\Notifications\Notification::make()
                                    ->success()
                                    ->title('Transaction document uploaded successfully')
                                    ->body('Transaction document has been uploaded.')
                                    ->send();
                                    
                            } catch (\Exception $e) {
                                \Illuminate\Support\Facades\Log::error('Transaction file access error:', ['error' => $e->getMessage(), 'path' => $uploadedFile]);
                                \Filament\Notifications\Notification::make()
                                    ->danger()
                                    ->title('File access error')
                                    ->body('Error accessing uploaded file: ' . $e->getMessage())
                                    ->send();
                                return;
                            }
                        } catch (\Exception $e) {
                            \Illuminate\Support\Facades\Log::error('Transaction upload error:', ['error' => $e->getMessage(), 'record' => $record->id]);
                            \Filament\Notifications\Notification::make()
                                ->danger()
                                ->title('Upload error')
                                ->body('An error occurred during upload: ' . $e->getMessage())
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

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTransactionsWithoutDocuments::route('/'),
        ];
    }
} 