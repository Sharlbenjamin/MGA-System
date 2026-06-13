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
use Filament\Facades\Filament;
use Filament\Tables\Actions\Action;
use Illuminate\Database\Eloquent\Model;

use App\Filament\Support\TransactionDocumentationForm;
use App\Services\TransactionDocumentationService;
use Filament\Notifications\Notification;


class TransactionResource extends Resource
{
    protected static ?string $model = Transaction::class;

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';
    protected static ?string $navigationGroup = 'Finance';
    protected static ?string $navigationLabel = 'Bank Transactions';
    protected static ?int $navigationSort = 2;
    protected static ?string $recordTitleAttribute = 'name';

    public static function getNavigationBadge(): ?string
    {
        if (! \Illuminate\Support\Facades\Schema::hasColumn('transactions', 'documentation_status')) {
            return null;
        }

        $count = Transaction::query()->where('documentation_status', '!=', 'complete')->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }



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
                                
                                $bills = static::resolveBillsForPaymentReason($get);
                                $reason = Bill::formatPaymentReasonSentence($bills);
                                
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
                        Forms\Components\TextInput::make('provider_iban_display')
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
                            ->reactive()
                            ->visible(fn ($get) => $get('type') === 'Outflow')
                            ->helperText('Click to copy')
                            ->suffixAction(
                                \Filament\Forms\Components\Actions\Action::make('copy_iban')
                                    ->icon('heroicon-o-clipboard')
                                    ->action(function (callable $get) {
                                        $relatedType = $get('related_type');
                                        $relatedId = $get('related_id');
                                        
                                        $iban = '';
                                        if ($relatedType === 'Provider') {
                                            $provider = \App\Models\Provider::find($relatedId);
                                            $iban = $provider?->bankAccounts()->first()?->iban ?? '';
                                        } elseif ($relatedType === 'Branch') {
                                            $branch = \App\Models\ProviderBranch::find($relatedId);
                                            $iban = $branch?->bankAccounts()->first()?->iban ?? '';
                                        }
                                        
                                        return "navigator.clipboard.writeText('{$iban}').then(() => { window.dispatchEvent(new CustomEvent('show-notification', { detail: { message: 'IBAN copied to clipboard!' } })); });";
                                    })
                            ),
                        
                        Forms\Components\TextInput::make('provider_beneficiary_display')
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
                            ->reactive()
                            ->visible(fn ($get) => $get('type') === 'Outflow')
                            ->helperText('Click to copy')
                            ->suffixAction(
                                \Filament\Forms\Components\Actions\Action::make('copy_beneficiary')
                                    ->icon('heroicon-o-clipboard')
                                    ->action(function (callable $get) {
                                        $relatedType = $get('related_type');
                                        $relatedId = $get('related_id');
                                        
                                        $beneficiary = '';
                                        if ($relatedType === 'Provider') {
                                            $provider = \App\Models\Provider::find($relatedId);
                                            $beneficiary = $provider?->bankAccounts()->first()?->beneficiary_name ?? '';
                                        } elseif ($relatedType === 'Branch') {
                                            $branch = \App\Models\ProviderBranch::find($relatedId);
                                            $beneficiary = $branch?->bankAccounts()->first()?->beneficiary_name ?? '';
                                        }
                                        
                                        return "navigator.clipboard.writeText('{$beneficiary}').then(() => { window.dispatchEvent(new CustomEvent('show-notification', { detail: { message: 'Beneficiary name copied to clipboard!' } })); });";
                                    })
                            ),
                        
                        Forms\Components\TextInput::make('provider_swift_display')
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
                            ->reactive()
                            ->visible(fn ($get) => $get('type') === 'Outflow')
                            ->helperText('Click to copy')
                            ->suffixAction(
                                \Filament\Forms\Components\Actions\Action::make('copy_swift')
                                    ->icon('heroicon-o-clipboard')
                                    ->action(function (callable $get) {
                                        $relatedType = $get('related_type');
                                        $relatedId = $get('related_id');
                                        
                                        $swift = '';
                                        if ($relatedType === 'Provider') {
                                            $provider = \App\Models\Provider::find($relatedId);
                                            $swift = $provider?->bankAccounts()->first()?->swift ?? '';
                                        } elseif ($relatedType === 'Branch') {
                                            $branch = \App\Models\ProviderBranch::find($relatedId);
                                            $swift = $branch?->bankAccounts()->first()?->swift ?? '';
                                        }
                                        
                                        return "navigator.clipboard.writeText('{$swift}').then(() => { window.dispatchEvent(new CustomEvent('show-notification', { detail: { message: 'SWIFT copied to clipboard!' } })); });";
                                    })
                            ),
                        
                        Forms\Components\TextInput::make('provider_country_display')
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
                            ->reactive()
                            ->visible(fn ($get) => $get('type') === 'Outflow')
                            ->helperText('Click to copy')
                            ->suffixAction(
                                \Filament\Forms\Components\Actions\Action::make('copy_country')
                                    ->icon('heroicon-o-clipboard')
                                    ->action(function (callable $get) {
                                        $relatedType = $get('related_type');
                                        $relatedId = $get('related_id');
                                        
                                        $country = '';
                                        if ($relatedType === 'Provider') {
                                            $provider = \App\Models\Provider::find($relatedId);
                                            $country = $provider?->bankAccounts()->first()?->country?->name ?? '';
                                        } elseif ($relatedType === 'Branch') {
                                            $branch = \App\Models\ProviderBranch::find($relatedId);
                                            $country = $branch?->bankAccounts()->first()?->country?->name ?? '';
                                        }
                                        
                                        return "navigator.clipboard.writeText('{$country}').then(() => { window.dispatchEvent(new CustomEvent('show-notification', { detail: { message: 'Country copied to clipboard!' } })); });";
                                    })
                            ),
                        
                        Forms\Components\Placeholder::make('provider_reason_display')
                            ->key('transaction_form_outflow_provider_reason')
                            ->label('Transaction Reason')
                            ->content(fn (callable $get) => Bill::formatPaymentReasonSentence(static::resolveBillsForPaymentReason($get)))
                            ->hint('Use the clipboard icon to copy')
                            ->hintAction(
                                \Filament\Forms\Components\Actions\Action::make('copy_reason')
                                    ->icon('heroicon-o-clipboard')
                                    ->iconButton()
                                    ->action(function (callable $get) {
                                        $reason = Bill::formatPaymentReasonSentence(static::resolveBillsForPaymentReason($get));
                                        $jsReason = json_encode($reason, JSON_THROW_ON_ERROR | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);

                                        return "navigator.clipboard.writeText({$jsReason}).then(() => { window.dispatchEvent(new CustomEvent('show-notification', { detail: { message: 'Transaction reason copied to clipboard!' } })); });";
                                    })
                            )
                            ->visible(fn ($get) => $get('type') === 'Outflow')
                            ->columnSpanFull(),
                    ])
                    ->visible(fn ($get) => $get('type') === 'Outflow' && in_array($get('related_type'), ['Provider', 'Branch']))
                    ->collapsible()
                    ->collapsed(false),
                Forms\Components\TextInput::make('name')->required()->maxLength(255)->default(fn () => request()->get('name')),
                Forms\Components\TextInput::make('amount')->required()->numeric()->prefix('€')->default(fn () => request()->get('amount')),
                Forms\Components\DatePicker::make('date')->required()->default(fn () => request()->get('date') ?? now()),
                Forms\Components\Textarea::make('notes')->columnSpanFull(),
                Forms\Components\Section::make('Transaction Document')
                    ->description('Upload payment proof or transaction document.')
                    ->icon('heroicon-o-document-arrow-up')
                    ->schema([
                        Forms\Components\FileUpload::make('attachment_file')
                            ->label('Bill Payment / Transaction Proof')
                            ->disk('public')
                            ->directory('transactions')
                            ->visibility('public')
                            ->acceptedFileTypes([
                                'application/pdf',
                                'image/*',
                                'application/msword',
                                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                            ])
                            ->maxSize(10240)
                            ->helperText('Upload payment proof (PDF, image, or Word document).')
                            ->downloadable()
                            ->openable()
                            ->preserveFilenames()
                            ->maxFiles(1)
                            ->fetchFileInformation(false)
                            ->dehydrated(false)
                            ->afterStateHydrated(function ($component, $state, ?Transaction $record): void {
                                $existingPath = $record?->attachment_path;

                                if (is_string($existingPath) && $existingPath !== ''
                                    && ! str_starts_with($existingPath, 'http')
                                    && ! str_contains($existingPath, 'drive.google.com')) {
                                    $component->state([$existingPath]);

                                    return;
                                }

                                $component->state([]);
                            })
                            ->afterStateUpdated(function ($state, callable $set): void {
                                if (is_string($state)) {
                                    $state = [$state];
                                }

                                if (is_array($state)) {
                                    $state = $state[0] ?? null;
                                }

                                $set('attachment_path', $state ?: null);
                            }),
                        Forms\Components\Hidden::make('attachment_path')
                            ->default(fn (?Transaction $record) => $record?->attachment_path),
                    ])
                    ->columnSpanFull(),

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
            static::documentationStatusSection(),
            ]);
    }

    public static function documentationStatusSection(): Forms\Components\Section
    {
        return Forms\Components\Section::make('Documentation status')
            ->schema([
                Forms\Components\Placeholder::make('documentation_checklist')
                    ->label('Checklist')
                    ->content(function (?Transaction $record) {
                        if (! $record?->exists) {
                            return 'Save the transaction to see documentation status.';
                        }

                        $service = app(TransactionDocumentationService::class);
                        $tasks = $service->getMissingTasks($record);
                        $done = collect($tasks)->where('status', 'done')->count();

                        return collect($tasks)->map(function ($task) {
                            $icon = $task['status'] === 'done' ? '✓' : '⚠';

                            return "{$icon} {$task['label']}";
                        })->prepend('Progress: ' . $done . ' of ' . count($tasks) . ' complete')->implode("\n");
                    })
                    ->columnSpanFull(),
                Forms\Components\Placeholder::make('reference_display')
                    ->label('Reference')
                    ->content(fn (?Transaction $record) => $record?->reference ?? '—'),
                Forms\Components\Placeholder::make('documentation_status_display')
                    ->label('Status')
                    ->content(fn (?Transaction $record) => ucfirst(str_replace('_', ' ', $record?->documentation_status ?? 'incomplete'))),
            ])
            ->visible(fn ($livewire) => $livewire instanceof Pages\EditTransaction);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(function (Builder $query): Builder {
                $query->with([
                    'bankAccount',
                    'invoices.file.patient.client',
                    'bills.file.patient.client',
                ]);

                $user = Filament::auth()->user();
                $privilegedRoles = [
                    'admin',
                    'Admin',
                    'financial',
                    'Financial',
                    'financial manager',
                    'Financial Manager',
                    'manager',
                    'Manager',
                ];

                $canViewAllTypes = $user
                    && method_exists($user, 'hasAnyRole')
                    && (bool) call_user_func([$user, 'hasAnyRole'], $privilegedRoles);

                if (!$canViewAllTypes) {
                    $query->where('type', 'Outflow');
                }

                return $query;
            })
            ->defaultSort('date', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('date')->date()->sortable(),
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->limit(25)
                    ->tooltip(fn (Transaction $record): ?string => $record->name),
                Tables\Columns\TextColumn::make('related_type')
                    ->label('Type')
                    ->badge()
                    ->searchable(),
                Tables\Columns\TextColumn::make('related_party')
                    ->label('Related to')
                    ->getStateUsing(fn (Transaction $record): ?string => $record->getRelatedPartyLabel())
                    ->placeholder('—')
                    ->limit(30)
                    ->tooltip(fn (Transaction $record): ?string => $record->getRelatedPartyLabel()),
                Tables\Columns\TextColumn::make('amount'),
                Tables\Columns\TextColumn::make('type')->searchable()
                ->color(fn ($record) => match ($record->type) {'Income' => 'success','Outflow' => 'warning','Expense' => 'danger',})->badge(),
                Tables\Columns\TextColumn::make('documentation_status')
                    ->label('Documentation')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'complete' => 'Complete',
                        'incomplete' => 'Incomplete',
                        'missing_attachment' => 'Missing attachment',
                        'missing_linked_record' => 'Missing linked record',
                        'missing_generated_pdf' => 'Missing PDF',
                        default => ucfirst(str_replace('_', ' ', $state ?? 'incomplete')),
                    })
                    ->color(fn (Transaction $record): string => match ($record->documentation_status) {
                        'complete' => 'success',
                        'incomplete' => 'warning',
                        default => 'danger',
                    })
                    ->sortable(),
                Tables\Columns\TextColumn::make('pending_documentation_count')
                    ->label('Missing tasks')
                    ->badge()
                    ->color(fn ($state) => ((int) $state) > 0 ? 'danger' : 'success')
                    ->getStateUsing(fn (Transaction $record) => $record->pending_documentation_count),
                Tables\Columns\TextColumn::make('status')
                    ->label('Payment status')
                    ->badge()
                    ->colors([
                        'Draft' => 'gray',
                        'Completed' => 'success',
                        'Pending' => 'warning',
                    ])
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('documentation_label')
                    ->label('Category')
                    ->getStateUsing(fn (Transaction $record) => $record->documentation_label),
                Tables\Columns\TextColumn::make('attachment_path')
                    ->label('Link/Text')
                    ->searchable()
                    ->formatStateUsing(function ($record) {
                        if (!$record->attachment_path) {
                            return 'No Link/Text';
                        }

                        // Check if it's an uploaded local file
                        if ($record->isUploadedFile()) {
                            return 'View Uploaded File';
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
                        return $record->getAttachmentUrl();
                    })
                    ->openUrlInNewTab()
                    ->icon(fn ($record) => !$record->attachment_path ? 'heroicon-o-document' : (($record->isUploadedFile() || $record->isGoogleDriveAttachment() || filter_var($record->attachment_path, FILTER_VALIDATE_URL)) ? 'heroicon-o-link' : 'heroicon-o-document-text'))
                    ->color(fn ($record) => !$record->attachment_path ? 'gray' : (($record->isUploadedFile() || $record->isGoogleDriveAttachment() || filter_var($record->attachment_path, FILTER_VALIDATE_URL)) ? 'primary' : 'gray')),
            ])
            ->filters([
                Tables\Filters\Filter::make('transaction_date')
                    ->label('Transaction date')
                    ->form([
                        Forms\Components\DatePicker::make('date_from')
                            ->label('From'),
                        Forms\Components\DatePicker::make('date_until')
                            ->label('Until'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['date_from'] ?? null,
                                fn (Builder $query, $date): Builder => $query->whereDate('date', '>=', $date),
                            )
                            ->when(
                                $data['date_until'] ?? null,
                                fn (Builder $query, $date): Builder => $query->whereDate('date', '<=', $date),
                            );
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];

                        if ($data['date_from'] ?? null) {
                            $indicators['date_from'] = 'From ' . \Carbon\Carbon::parse($data['date_from'])->format('d/m/Y');
                        }

                        if ($data['date_until'] ?? null) {
                            $indicators['date_until'] = 'Until ' . \Carbon\Carbon::parse($data['date_until'])->format('d/m/Y');
                        }

                        return $indicators;
                    }),
                Tables\Filters\SelectFilter::make('type')->options(['Income' => 'Income', 'Outflow' => 'Outflow', 'Expense' => 'Expense'])->multiple(),
                Tables\Filters\SelectFilter::make('documentation_status')
                    ->options([
                        'complete' => 'Complete',
                        'incomplete' => 'Incomplete',
                        'missing_attachment' => 'Missing attachment',
                        'missing_linked_record' => 'Missing linked record',
                        'missing_generated_pdf' => 'Missing generated PDF',
                    ])
                    ->multiple(),
                Tables\Filters\Filter::make('incomplete_only')
                    ->label('Incomplete documentation only')
                    ->toggle()
                    ->query(fn (Builder $query) => $query->where('documentation_status', '!=', 'complete'))
                    ->indicateUsing(fn (): array => ['incomplete_only' => 'Incomplete documentation only']),
                Tables\Filters\SelectFilter::make('status')
                    ->label('Payment status')
                    ->options(['Draft' => 'Draft', 'Completed' => 'Completed', 'Pending' => 'Pending'])
                    ->multiple(),
                Tables\Filters\Filter::make('missing_documents')
                    ->label('Missing documents (Outflow)')
                    ->toggle()
                    ->query(fn (Builder $query): Builder => $query
                        ->where('type', 'Outflow')
                        ->whereNull('attachment_path'))
                    ->indicateUsing(fn (): array => ['missing_documents' => 'Missing documents (Outflow)']),
            ])
            ->groups([
                Tables\Grouping\Group::make('documentation_status')
                    ->label('Documentation status')
                    ->collapsible(),
                Tables\Grouping\Group::make('type')
                    ->label('Type')
                    ->collapsible(),
                Tables\Grouping\Group::make('date')
                    ->label('Month')
                    ->date()
                    ->collapsible()
                    ->getTitleFromRecordUsing(fn (Transaction $record): string => $record->date->format('F Y'))
                    ->getDescriptionFromRecordUsing(fn (Transaction $record) => $record->date->format('F Y') . ' Balance: ' . $record->bankAccount->monthlyBalance($record->date)),

            ])
            ->actions([
                TransactionDocumentationForm::makeTableAction(),
                Tables\Actions\ViewAction::make(),
                Action::make('uploadDocument')
                    ->label('Upload Document')
                    ->icon('heroicon-o-document-arrow-up')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Upload Transaction Document')
                    ->modalDescription('Upload the transaction document to Google Drive.')
                    ->modalSubmitActionLabel('Upload')
                    ->form(static::documentUploadFormSchema('transaction_document_main'))
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

                            static::saveUploadedDocument($record, $data['transaction_document_main']);
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
            'import' => Pages\ImportTransactions::route('/import'),
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


    /**
     * Bills for payment reference text: use the Bills multi-select (or bill_ids / bill_id from the URL),
     * otherwise every unpaid or partial bill for the related provider or branch.
     *
     * @return \Illuminate\Support\Collection<int, Bill>
     */
    public static function resolveBillsForPaymentReason(callable $get): \Illuminate\Support\Collection
    {
        $relatedType = $get('related_type');
        $relatedId = $get('related_id');

        if (! $relatedId || ! in_array($relatedType, ['Provider', 'Branch'], true)) {
            return collect();
        }

        $relatedId = (int) $relatedId;

        $orderedIds = [];

        $fromForm = $get('bills');
        if (is_array($fromForm)) {
            foreach ($fromForm as $id) {
                if ($id === null || $id === '' || $id === false) {
                    continue;
                }
                $orderedIds[] = (int) $id;
            }
        }

        if ($orderedIds === [] && request()->filled('bill_ids')) {
            foreach (explode(',', (string) request()->get('bill_ids')) as $raw) {
                $raw = trim($raw);
                if ($raw !== '' && ctype_digit($raw)) {
                    $orderedIds[] = (int) $raw;
                }
            }
        }

        if ($orderedIds === [] && request()->filled('bill_id')) {
            $bid = request()->get('bill_id');
            if (is_numeric($bid)) {
                $orderedIds[] = (int) $bid;
            }
        }

        $orderedIds = array_values(array_unique($orderedIds));

        if ($orderedIds !== []) {
            $query = static::billsBaseQueryForRelated($relatedType, $relatedId)
                ->whereIn('id', $orderedIds);

            $models = $query->get()->keyBy('id');

            return collect($orderedIds)
                ->map(fn (int $id) => $models->get($id))
                ->filter()
                ->values();
        }

        return static::allUnpaidPartialBillsForRelated($relatedType, $relatedId);
    }

    protected static function billsBaseQueryForRelated(string $relatedType, int $relatedId): Builder
    {
        $q = Bill::query();

        if ($relatedType === 'Provider') {
            $q->whereHas('file', function ($query) use ($relatedId) {
                $query->whereHas('provider', function ($providerQuery) use ($relatedId) {
                    $providerQuery->where('providers.id', $relatedId);
                })
                    ->orWhereHas('providerBranch', function ($branchQuery) use ($relatedId) {
                        $branchQuery->where('provider_branches.provider_id', $relatedId);
                    });
            });
        } elseif ($relatedType === 'Branch') {
            $q->whereHas('file', function ($query) use ($relatedId) {
                $query->where('provider_branch_id', $relatedId);
            });
        }

        return $q;
    }

    /**
     * @return \Illuminate\Support\Collection<int, Bill>
     */
    protected static function allUnpaidPartialBillsForRelated(string $relatedType, int $relatedId): \Illuminate\Support\Collection
    {
        return static::billsBaseQueryForRelated($relatedType, $relatedId)
            ->whereIn('status', ['Unpaid', 'Partial'])
            ->get();
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
                'Lawyer' => 'Lawyer',
                'Taxes' => 'Taxes',
                'Utility' => 'Utility',
                'Salary' => 'Salary',
                'Rent' => 'Rent',
                'Marketing' => 'Marketing',
                'Insurance' => 'Insurance',
                'Legal' => 'Legal',
                'Accounting' => 'Accounting',
                'Travel' => 'Travel',
                'Other' => 'Other',

            ],
            default => [],
        };
    }

    public static function documentUploadFormSchema(string $fieldName = 'transaction_document'): array
    {
        return [
            Forms\Components\FileUpload::make($fieldName)
                ->label('Upload Transaction Document')
                ->acceptedFileTypes(['application/pdf', 'image/*'])
                ->maxSize(10240)
                ->required()
                ->disk('public')
                ->directory('transactions')
                ->visibility('public')
                ->helperText('Upload the transaction document (PDF or image)')
                ->downloadable()
                ->openable()
                ->preserveFilenames()
                ->maxFiles(1),
        ];
    }

    public static function saveUploadedDocument(Transaction $record, mixed $uploadedFile): bool
    {
        $uploadedFile = TransactionDocumentationForm::normalizeUploadedFilePath($uploadedFile);

        if (! $uploadedFile) {
            Notification::make()
                ->danger()
                ->title('Invalid file data')
                ->body('The uploaded file data is invalid.')
                ->send();

            return false;
        }

        $record->attachment_path = $uploadedFile;
        $record->save();

        app(TransactionDocumentationService::class)->syncAndRecalculate($record);

        Notification::make()
            ->success()
            ->title('Transaction document uploaded successfully')
            ->body('Transaction document has been uploaded.')
            ->send();

        return true;
    }
}
