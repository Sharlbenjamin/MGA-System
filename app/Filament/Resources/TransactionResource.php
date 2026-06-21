<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TransactionResource\Pages;
use App\Filament\Resources\TransactionResource\RelationManager\BillRelationManager;
use App\Filament\Resources\TransactionResource\RelationManager\InvoiceRelationManager;
use App\Filament\Support\TransactionDocumentationForm;
use App\Filament\Support\TransactionInvoiceLinkForm;
use App\Models\BankAccount;
use App\Models\Bill;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Patient;
use App\Models\Provider;
use App\Models\ProviderBranch;
use App\Models\Transaction;
use App\Services\BulkTransactionPdfService;
use App\Services\TransactionDocumentationService;
use App\Services\TransactionDocumentationStatsService;
use App\Services\TransactionIntegrityService;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Forms\Get;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Actions\Action;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

class TransactionResource extends Resource
{
    protected static ?string $model = Transaction::class;

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';

    protected static ?string $navigationGroup = 'Finance';

    protected static ?string $navigationLabel = 'Bank Transactions';

    protected static ?int $navigationSort = 2;

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public static function indexUrlFor(BankAccount|int $bankAccount): string
    {
        $id = $bankAccount instanceof BankAccount ? $bankAccount->getKey() : $bankAccount;

        return static::getUrl('index', ['bankAccount' => $id]);
    }

    public static function getUrl(string $name = 'index', array $parameters = [], bool $isAbsolute = true, ?string $panel = null, ?Model $tenant = null): string
    {
        if ($name === 'index' && ! array_key_exists('bankAccount', $parameters)) {
            return BankAccountResource::getUrl('index', $parameters, $isAbsolute, $panel, $tenant);
        }

        return parent::getUrl($name, $parameters, $isAbsolute, $panel, $tenant);
    }

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
                ])->required()->live()->afterStateUpdated(function (?string $state, callable $set, Get $get): void {
                    $default = TransactionDocumentationStatsService::defaultCategoryFor($state, $get('related_type'));
                    if ($default) {
                        $set('documentation_category', $default);
                    }
                })->default(function () {
                    $type = request()->get('type');
                    $allParams = request()->all();
                    Log::info('Transaction form defaults:', [
                        'type' => $type,
                        'all_params' => $allParams,
                        'url' => request()->url(),
                    ]);

                    return $type;
                }),
                Forms\Components\Select::make('related_type')
                    ->options(fn ($get) => self::relatedTypes($get('type')))
                    ->required()
                    ->searchable()
                    ->live()
                    ->afterStateUpdated(function (?string $state, callable $set, Get $get): void {
                        $default = TransactionDocumentationStatsService::defaultCategoryFor($get('type'), $state);
                        if ($default) {
                            $set('documentation_category', $default);
                        }
                    })
                    ->default(fn () => request()->get('related_type')),
                // I want to select an invoice if realted_type is Client
                Forms\Components\Select::make('related_id')->label('Client')->required()->options(Client::all()->pluck('company_name', 'id'))->visible(fn ($get) => $get('related_type') === 'Client')->searchable()->default(fn () => request()->get('related_id')),
                Forms\Components\Select::make('related_id')->label('Provider')->required()->options(Provider::all()->pluck('name', 'id'))->visible(fn ($get) => $get('related_type') === 'Provider')->searchable()->default(fn () => request()->get('related_id')),
                Forms\Components\Select::make('related_id')->label('Provider')->required()->options(ProviderBranch::all()->pluck('name', 'id'))->visible(fn ($get) => $get('related_type') === 'Branch')->searchable()->default(fn () => request()->get('related_id')),
                Forms\Components\Select::make('related_id')->label('Patient')->required(fn (Get $get): bool => $get('type') === 'Income')->options(Patient::all()->pluck('name', 'id'))->visible(fn ($get) => $get('related_type') === 'Patient')->searchable()->default(fn () => request()->get('related_id')),
                static::categorySelect(),
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

                                if ($type !== 'Outflow' || ! $relatedId) {
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

                                if (! $bankAccount) {
                                    return 'No bank account found for the selected provider/branch.';
                                }

                                $bills = static::resolveBillsForPaymentReason($get);
                                $reason = Bill::formatPaymentReasonSentence($bills);

                                $details = [
                                    'IBAN: '.$bankAccount->iban,
                                    'Beneficiary Name: '.$bankAccount->beneficiary_name,
                                    'SWIFT: '.$bankAccount->swift,
                                    'Country: '.($bankAccount->country?->name ?? 'N/A'),
                                    'Reason: '.$reason,
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
                    ->collapsed(false)
                    ->hiddenOn('edit'),
                Forms\Components\Select::make('status')
                    ->label('Payment status')
                    ->options([
                        'Completed' => 'Completed',
                        'Draft' => 'Draft (awaiting bank)',
                        'Pending' => 'Pending',
                    ])
                    ->default(fn () => request()->get('status', 'Completed'))
                    ->required()
                    ->helperText(fn (Get $get): ?string => $get('status') === 'Draft'
                        ? 'Draft — awaiting bank statement confirmation. Bills will not be marked paid until you finalize.'
                        : null)
                    ->visible(fn ($livewire) => $livewire instanceof Pages\CreateTransaction),
                Forms\Components\Placeholder::make('draft_payment_notice')
                    ->label('Draft payment')
                    ->content('This is a draft payment awaiting bank statement confirmation. Enter the transaction name manually or update it after import.')
                    ->visible(fn (Get $get, $livewire): bool => $livewire instanceof Pages\CreateTransaction && $get('status') === 'Draft')
                    ->columnSpanFull(),
                Forms\Components\Placeholder::make('payment_status_display')
                    ->label('Payment status')
                    ->content(fn (?Transaction $record): string => match ($record?->status) {
                        'Draft' => 'Draft (awaiting bank) — bills are not marked paid until you confirm payment.',
                        'Pending' => 'Pending',
                        default => 'Completed',
                    })
                    ->visible(fn ($livewire, ?Transaction $record): bool => $livewire instanceof Pages\EditTransaction && $record?->status === 'Draft')
                    ->columnSpanFull(),
                Forms\Components\TextInput::make('name')->required()->maxLength(255)->default(fn () => request()->get('name')),
                Forms\Components\TextInput::make('amount')->required()->numeric()->prefix('€')->default(fn () => request()->get('amount')),
                Forms\Components\DatePicker::make('date')->required()->default(fn () => request()->get('date') ?? now()),
                Forms\Components\Textarea::make('notes')->label('Comment')->columnSpanFull(),

                Forms\Components\TextInput::make('bank_charges')
                    ->numeric()
                    ->prefix('€')
                    ->maxValue(999999.99)
                    ->default(0)
                    ->helperText('Manual only — not auto-calculated from bills.'),

                Forms\Components\Toggle::make('charges_covered_by_client')
                    ->default(false),

                Forms\Components\Repeater::make('invoice_links')
                    ->label('Invoices')
                    ->addActionLabel('Add invoice')
                    ->schema(TransactionInvoiceLinkForm::createRepeaterSchema())
                    ->default(function (): array {
                        $invoiceId = request()->integer('invoice_id');

                        if (! $invoiceId) {
                            return [];
                        }

                        return [['invoice_id' => $invoiceId, 'amount_paid' => null]];
                    })
                    ->visible(fn ($get, $livewire) => $get('related_type') === 'Client'
                        && $livewire instanceof Pages\CreateTransaction)
                    ->columnSpanFull(),
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

                        if (! $relatedId || ! in_array($relatedType, ['Provider', 'Branch'], true)) {
                            return [];
                        }

                        return static::availableBillOptions(
                            $relatedType,
                            (int) $relatedId,
                            $record?->id,
                        );
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
                        })->prepend('Progress: '.$done.' of '.count($tasks).' complete')->implode("\n");
                    })
                    ->columnSpanFull(),
                Forms\Components\Placeholder::make('reference_display')
                    ->label('Reference')
                    ->content(fn (?Transaction $record) => $record?->reference ?? '—'),
                Forms\Components\Placeholder::make('documentation_status_display')
                    ->label('Auto status preview')
                    ->content(fn (?Transaction $record) => $record
                        ? app(TransactionDocumentationService::class)->formatDocumentationStatusLabel(
                            $record->documentation_status ?? 'incomplete'
                        )
                        : '—'),
            ])
            ->visible(fn ($livewire) => $livewire instanceof Pages\EditTransaction || $livewire instanceof Pages\CreateTransaction);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(function (Builder $query): Builder {
                $query->select('transactions.*');
                static::applyRelatedPartyLabelSelect($query);

                $query->with([
                    'bankAccount',
                    'invoices',
                    'bills',
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

                if (! $canViewAllTypes) {
                    $query->where('transactions.type', 'Outflow');
                }

                return $query;
            })
            ->defaultSort('transactions.date', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('date')
                    ->date()
                    ->sortable(query: fn (Builder $query, string $direction): Builder => $query->orderBy('transactions.date', $direction)),
                Tables\Columns\TextColumn::make('name')
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query->where(
                        'transactions.name',
                        'like',
                        "%{$search}%",
                    ))
                    ->limit(25)
                    ->tooltip(fn (Transaction $record): ?string => $record->name),
                Tables\Columns\TextColumn::make('notes')
                    ->label('Comment')
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query->where(
                        'transactions.notes',
                        'like',
                        "%{$search}%",
                    ))
                    ->limit(20)
                    ->tooltip(fn (Transaction $record): ?string => $record->notes)
                    ->toggleable(),
                Tables\Columns\TextColumn::make('related_type')
                    ->label('Type')
                    ->badge()
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query->where(
                        'transactions.related_type',
                        'like',
                        "%{$search}%",
                    )),
                Tables\Columns\TextColumn::make('related_party')
                    ->label('Related to')
                    ->getStateUsing(fn (Transaction $record): ?string => $record->related_party_label ?? $record->getRelatedPartyLabel())
                    ->placeholder('—')
                    ->limit(30)
                    ->tooltip(fn (Transaction $record): ?string => $record->related_party_label ?? $record->getRelatedPartyLabel()),
                Tables\Columns\TextColumn::make('amount'),
                Tables\Columns\TextColumn::make('linking_status')
                    ->label('Linking status')
                    ->badge()
                    ->getStateUsing(fn (Transaction $record): string => $record->type === 'Income'
                        ? TransactionIntegrityService::linkingStatusLabel($record)
                        : '—')
                    ->color(fn (Transaction $record): string => $record->type === 'Income' && TransactionIntegrityService::hasInvoiceTotalMismatch($record)
                        ? 'warning'
                        : 'success')
                    ->tooltip(fn (Transaction $record): ?string => $record->type === 'Income'
                        ? TransactionIntegrityService::invoiceTotalMismatchTooltip($record)
                        : null)
                    ->toggleable(),
                Tables\Columns\TextColumn::make('documentation_status')
                    ->label('Documentation')
                    ->formatStateUsing(fn (?string $state): string => app(TransactionDocumentationService::class)->formatDocumentationStatusLabel($state))
                    ->badge()
                    ->color(fn (?string $state): string => TransactionDocumentationService::colorForStatusKey($state))
                    ->sortable(),
                Tables\Columns\TextColumn::make('type')
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query->where(
                        'transactions.type',
                        'like',
                        "%{$search}%",
                    ))
                    ->color(fn ($record) => match ($record->type) {
                        'Income' => 'success','Outflow' => 'warning','Expense' => 'danger',
                    })->badge(),
                Tables\Columns\TextColumn::make('status')
                    ->label('Payment status')
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'Draft' => 'Draft (awaiting bank)',
                        default => $state ?? '—',
                    })
                    ->badge()
                    ->colors([
                        'Draft' => 'gray',
                        'Completed' => 'success',
                        'Pending' => 'warning',
                    ])
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('documentation_label')
                    ->label('Category')
                    ->getStateUsing(fn (Transaction $record) => TransactionDocumentationStatsService::categoryLabel(
                        TransactionDocumentationStatsService::resolveCategoryKey($record)
                    )),
            ])
            ->recordClasses(fn (Transaction $record): ?string => TransactionIntegrityService::hasInvoiceTotalMismatch($record)
                ? 'bg-warning-50 dark:bg-warning-950/30'
                : null)
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
                                fn (Builder $query, $date): Builder => $query->whereDate('transactions.date', '>=', $date),
                            )
                            ->when(
                                $data['date_until'] ?? null,
                                fn (Builder $query, $date): Builder => $query->whereDate('transactions.date', '<=', $date),
                            );
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];

                        if ($data['date_from'] ?? null) {
                            $indicators['date_from'] = 'From '.\Carbon\Carbon::parse($data['date_from'])->format('d/m/Y');
                        }

                        if ($data['date_until'] ?? null) {
                            $indicators['date_until'] = 'Until '.\Carbon\Carbon::parse($data['date_until'])->format('d/m/Y');
                        }

                        return $indicators;
                    }),
                Tables\Filters\SelectFilter::make('type')->options(['Income' => 'Income', 'Outflow' => 'Outflow', 'Expense' => 'Expense'])->multiple(),
                Tables\Filters\SelectFilter::make('documentation_category')
                    ->label('Category')
                    ->options(TransactionDocumentationStatsService::allCategoryOptions())
                    ->visible(fn (): bool => \Illuminate\Support\Facades\Schema::hasColumn('transactions', 'documentation_category'))
                    ->query(function (Builder $query, array $data): Builder {
                        $value = $data['value'] ?? null;

                        if (! filled($value)) {
                            return $query;
                        }

                        return TransactionDocumentationStatsService::applyCategoryScope($query, $value);
                    }),
                Tables\Filters\SelectFilter::make('documentation_workflow')
                    ->label('Documentation workflow')
                    ->options(TransactionDocumentationStatsService::allCategoryOptions())
                    ->query(function (Builder $query, array $data): Builder {
                        $value = $data['value'] ?? null;

                        if (! filled($value)) {
                            return $query;
                        }

                        return TransactionDocumentationStatsService::applyCategoryScope($query, $value);
                    }),
                Tables\Filters\SelectFilter::make('documentation_status')
                    ->options([
                        'unlinked' => 'Unlinked',
                        'complete' => 'Complete (ready for taxes)',
                        'incomplete' => 'Incomplete',
                        'missing_attachment' => 'Missing attachment',
                        'missing_generated_pdf' => 'Missing PDF',
                    ])
                    ->multiple(),
                Tables\Filters\Filter::make('linking_status_mismatch')
                    ->label('Amount mismatch only')
                    ->toggle()
                    ->query(fn (Builder $query): Builder => TransactionIntegrityService::applyInvoiceTotalMismatchScope($query))
                    ->indicateUsing(fn (): array => ['linking_status_mismatch' => 'Amount mismatch only']),
                Tables\Filters\Filter::make('data_integrity_paid_invoice')
                    ->label('Paid invoice amount mismatch')
                    ->toggle()
                    ->query(fn (Builder $query): Builder => TransactionIntegrityService::applyPaidInvoiceAmountMismatchScope($query))
                    ->indicateUsing(fn (): array => ['data_integrity_paid_invoice' => 'Paid invoice amount mismatch']),
                Tables\Filters\SelectFilter::make('status')
                    ->label('Payment status')
                    ->options(['Draft' => 'Draft', 'Completed' => 'Completed', 'Pending' => 'Pending'])
                    ->multiple(),
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
                    ->getTitleFromRecordUsing(fn (Transaction $record): string => $record->date->format('F Y')),
            ])
            ->actions([
                TransactionDocumentationForm::makeTableAction(),
                Tables\Actions\ViewAction::make(),
                Action::make('viewTrxInPdf')
                    ->label('Trx In PDF')
                    ->icon('heroicon-o-document-text')
                    ->color('info')
                    ->url(fn (Transaction $record) => $record->getTrxInPdfUrl())
                    ->openUrlInNewTab()
                    ->visible(fn (Transaction $record) => (bool) $record->getTrxInPdfUrl()),
                Action::make('viewTrxOutPdf')
                    ->label('Trx Out PDF')
                    ->icon('heroicon-o-document-text')
                    ->color('info')
                    ->url(fn (Transaction $record) => $record->getTrxOutPdfUrl())
                    ->openUrlInNewTab()
                    ->visible(fn (Transaction $record) => (bool) $record->getTrxOutPdfUrl()),
                Tables\Actions\EditAction::make(),
                Action::make('finalizeTransaction')
                    ->label('Confirm payment (finalize)')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Confirm payment')
                    ->modalDescription('This will mark all attached bills as paid and complete the transaction. Use after the bank statement confirms the payment.')
                    ->modalSubmitActionLabel('Confirm payment')
                    ->visible(fn ($record) => $record->status === 'Draft')
                    ->action(function (Transaction $record, \Livewire\Component $livewire): void {
                        try {
                            $record->finalizeTransaction();

                            Notification::make()
                                ->success()
                                ->title('Payment confirmed')
                                ->body('Transaction finalized and bills marked as paid.')
                                ->send();

                            $livewire->dispatch('refresh-transaction-documentation-stats');
                        } catch (\Exception $e) {
                            Notification::make()
                                ->danger()
                                ->title('Finalization Failed')
                                ->body('Error finalizing transaction: '.$e->getMessage())
                                ->send();
                        }
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('updateProvider')
                        ->label('Update provider')
                        ->icon('heroicon-o-building-office')
                        ->form([
                            Forms\Components\Select::make('related_type')
                                ->label('Link as')
                                ->options([
                                    'Provider' => 'Provider',
                                    'Branch' => 'Branch',
                                ])
                                ->default('Provider')
                                ->required()
                                ->live(),
                            Forms\Components\Select::make('related_id')
                                ->label(fn (Get $get): string => $get('related_type') === 'Branch' ? 'Branch' : 'Provider')
                                ->options(fn (Get $get): array => match ($get('related_type')) {
                                    'Branch' => ProviderBranch::query()->orderBy('branch_name')->pluck('branch_name', 'id')->all(),
                                    default => Provider::query()->orderBy('name')->pluck('name', 'id')->all(),
                                })
                                ->searchable()
                                ->required(),
                        ])
                        ->action(function (\Illuminate\Database\Eloquent\Collection $records, array $data): void {
                            $relatedType = $data['related_type'];
                            $relatedId = (int) $data['related_id'];

                            foreach ($records as $transaction) {
                                $transaction->update([
                                    'related_type' => $relatedType,
                                    'related_id' => $relatedId,
                                ]);
                            }

                            Notification::make()
                                ->success()
                                ->title('Provider updated')
                                ->body('Updated '.$records->count().' transaction(s).')
                                ->send();
                        })
                        ->deselectRecordsAfterCompletion(),
                    Tables\Actions\BulkAction::make('generatePdfs')
                        ->label('Generate PDFs')
                        ->icon('heroicon-o-document-plus')
                        ->requiresConfirmation()
                        ->action(function (\Illuminate\Database\Eloquent\Collection $records): void {
                            $result = app(BulkTransactionPdfService::class)->generateForTransactions(
                                $records,
                                'both',
                                false,
                            );

                            Notification::make()
                                ->title('PDF generation completed')
                                ->body(sprintf(
                                    'Generated: %d | Skipped: %d | Failed: %d',
                                    $result->generated,
                                    $result->skipped,
                                    $result->failed,
                                ))
                                ->color($result->failed > 0 ? 'warning' : 'success')
                                ->send();
                        }),
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
            'index' => Pages\ListTransactions::route('/bank-account/{bankAccount}'),
            'create' => Pages\CreateTransaction::route('/create'),
            'edit' => Pages\EditTransaction::route('/{record}/edit'),
            'view' => Pages\ViewTransaction::route('/{record}'),
        ];
    }

    public static function getGlobalSearchResultTitle(\Illuminate\Database\Eloquent\Model $record): string
    {
        return ($record->name ?? 'Unknown').' ('.($record->type ?? 'Unknown').')';
    }

    public static function getGlobalSearchResultDetails(\Illuminate\Database\Eloquent\Model $record): array
    {
        return [
            'Type' => $record->type ?? 'Unknown',
            'Related Type' => $record->related_type ?? 'Unknown',
            'Amount' => '€'.number_format($record->amount ?? 0, 2),
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
     * otherwise every bill for the related provider or branch.
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

        return static::availableBillsForRelated($relatedType, $relatedId);
    }

    /**
     * @return array<int, string>
     */
    public static function availableBillOptions(string $relatedType, ?int $relatedId, ?int $transactionId = null): array
    {
        if (! $relatedId || ! in_array($relatedType, ['Provider', 'Branch'], true)) {
            return [];
        }

        $query = static::billsBaseQueryForRelated($relatedType, $relatedId);
        static::applyAvailableBillForTransactionScope($query, $transactionId);

        return $query->orderByDesc('id')->get()
            ->mapWithKeys(fn (Bill $bill) => [
                $bill->id => static::formatBillOptionLabel($bill),
            ])->all();
    }

    /**
     * @return array<int, string>
     */
    public static function availableInvoiceOptions(?int $clientId, ?int $transactionId = null): array
    {
        if (! $clientId) {
            return [];
        }

        $query = Invoice::query()
            ->whereHas('patient', fn (Builder $q) => $q->where('client_id', $clientId));

        static::applyAvailableInvoiceForTransactionScope($query, $transactionId);

        return $query->orderByDesc('id')->get()
            ->mapWithKeys(fn (Invoice $invoice) => [
                $invoice->id => static::formatInvoiceOptionLabel($invoice),
            ])->all();
    }

    public static function applyRelatedPartyLabelSelect(Builder $query): Builder
    {
        if (collect($query->getQuery()->joins ?? [])->contains(fn ($join): bool => ($join->table ?? null) === 'clients')) {
            return $query;
        }

        return $query
            ->leftJoin('clients', function ($join): void {
                $join->on('transactions.related_id', '=', 'clients.id')
                    ->where('transactions.related_type', '=', 'Client');
            })
            ->leftJoin('providers', function ($join): void {
                $join->on('transactions.related_id', '=', 'providers.id')
                    ->where('transactions.related_type', '=', 'Provider');
            })
            ->leftJoin('provider_branches', function ($join): void {
                $join->on('transactions.related_id', '=', 'provider_branches.id')
                    ->where('transactions.related_type', '=', 'Branch');
            })
            ->leftJoin('providers as branch_providers', 'provider_branches.provider_id', '=', 'branch_providers.id')
            ->leftJoin('patients', function ($join): void {
                $join->on('transactions.related_id', '=', 'patients.id')
                    ->where('transactions.related_type', '=', 'Patient');
            })
            ->addSelect(DB::raw("COALESCE(NULLIF(clients.company_name, ''), providers.name, branch_providers.name, provider_branches.branch_name, patients.name) as related_party_label"));
    }

    public static function formatBillOptionLabel(Bill $bill): string
    {
        $date = $bill->bill_date ?? $bill->due_date;
        $dateStr = $date ? $date->format('d/m/Y') : '—';
        $amount = number_format((float) $bill->total_amount, 2);

        return "{$bill->name} · {$dateStr} · €{$amount}";
    }

    public static function formatInvoiceOptionLabel(Invoice $invoice): string
    {
        $dateStr = $invoice->invoice_date?->format('d/m/Y') ?? '—';
        $amount = number_format((float) $invoice->total_amount, 2);
        $remaining = number_format($invoice->remainingBalance(), 2);
        $status = $invoice->status ?: 'Unpaid';

        return "{$invoice->name} · {$dateStr} · €{$amount} · {$status} · €{$remaining} left";
    }

    protected static function applyAvailableBillForTransactionScope(Builder $query, ?int $transactionId): void
    {
        $query->where(function (Builder $q) use ($transactionId) {
            $q->whereDoesntHave('transactions');
            if ($transactionId) {
                $q->orWhereHas('transactions', fn (Builder $t) => $t->where('transactions.id', $transactionId));
            }
        });
    }

    protected static function applyAvailableInvoiceForTransactionScope(Builder $query, ?int $transactionId): void
    {
        $query->where(function (Builder $q) use ($transactionId) {
            $q->whereRaw('invoices.total_amount - COALESCE((
                SELECT SUM(it.amount_paid)
                FROM invoice_transaction it
                WHERE it.invoice_id = invoices.id
            ), 0) >= 0.01');

            if ($transactionId) {
                $q->orWhereHas('transactions', fn (Builder $t) => $t->where('transactions.id', $transactionId));
            }
        });
    }

    /** @deprecated Use applyAvailableBillForTransactionScope or applyAvailableInvoiceForTransactionScope */
    protected static function applyAvailableForTransactionScope(Builder $query, ?int $transactionId): void
    {
        static::applyAvailableBillForTransactionScope($query, $transactionId);
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
    protected static function availableBillsForRelated(string $relatedType, int $relatedId): \Illuminate\Support\Collection
    {
        return static::billsBaseQueryForRelated($relatedType, $relatedId)->get();
    }

    public static function getBills($relatedType, $relatedId)
    {
        if (! $relatedId || ! in_array($relatedType, ['Provider', 'Branch'], true)) {
            return collect();
        }

        $query = static::billsBaseQueryForRelated($relatedType, (int) $relatedId);
        static::applyAvailableForTransactionScope($query, null);

        return $query->get();
    }

    public static function categorySelect(): Forms\Components\Select
    {
        return Forms\Components\Select::make('documentation_category')
            ->label('Category')
            ->options(fn (Get $get): array => TransactionDocumentationStatsService::categoryOptionsFor(
                $get('type'),
                $get('related_type'),
            ))
            ->required()
            ->live()
            ->default(fn () => request()->get('documentation_category'))
            ->afterStateHydrated(function (Forms\Components\Select $component, $state, ?Transaction $record, Get $get): void {
                if ($record?->exists) {
                    $component->state(
                        $record->documentation_category
                            ?? TransactionDocumentationStatsService::resolveCategoryKey($record)
                    );

                    return;
                }

                if (blank($state)) {
                    $default = TransactionDocumentationStatsService::defaultCategoryFor(
                        $get('type'),
                        $get('related_type'),
                    );
                    if ($default) {
                        $component->state($default);
                    }
                }
            })
            ->afterStateUpdated(function (?string $state, callable $set, Get $get): void {
                match ($state) {
                    'client_payment', 'account_feed', 'refund' => [$set('type', 'Income'), $set('bills', [])],
                    'expense_payment' => [$set('type', 'Expense'), $set('bills', [])],
                    'card_provider', 'card_expense' => [$set('type', 'Outflow'), $set('bills', [])],
                    'provider_single', 'provider_bulk' => $set('type', 'Outflow'),
                    'patient_refund' => [$set('type', 'Outflow'), $set('related_type', 'Patient'), $set('bills', [])],
                    'capital_return' => [$set('type', 'Outflow'), $set('related_type', 'Other'), $set('bills', [])],
                    default => null,
                };

                $options = TransactionDocumentationStatsService::categoryOptionsFor($get('type'), $get('related_type'));
                if ($state && ! array_key_exists($state, $options)) {
                    $set('documentation_category', array_key_first($options));
                }
            })
            ->helperText('Category drives documentation requirements. Changing it may update type and bill links on save.');
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
                'Patient' => 'Patient',
                'Other' => 'Other',
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
}
