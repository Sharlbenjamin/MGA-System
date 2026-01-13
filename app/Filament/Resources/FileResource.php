<?php

namespace App\Filament\Resources;

use App\Filament\Resources\FileResource\Pages;
use App\Filament\Resources\FileResource\RelationManagers\GopRelationManager;
use App\Filament\Resources\FileResource\RelationManagers\MedicalReportRelationManager;
use App\Filament\Resources\FileResource\RelationManagers\PrescriptionRelationManager;
use App\Filament\Resources\FileResource\RelationManagers\PatientRelationManager;
use App\Filament\Resources\FileResource\RelationManagers\CommentsRelationManager;
use App\Filament\Resources\FileResource\RelationManagers\AppointmentsRelationManager;
use App\Filament\Resources\FileResource\RelationManagers\TaskRelationManager;
use App\Filament\Resources\FileResource\RelationManagers\BankAccountRelationManager;
use App\Filament\Resources\FileResource\RelationManagers\BillRelationManager;
use App\Filament\Resources\FileResource\RelationManagers\InvoiceRelationManager;
use App\Models\Client;
use App\Models\Country;
use App\Models\File;
use App\Models\Patient;
use Filament\Forms;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Tables\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Textarea;
use App\Filament\Forms\Components\PatientNameInput;
use Filament\Tables\Columns\Summarizers\Count;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Grouping\Group;

class FileResource extends Resource
{
    protected static ?string $model = File::class;

    protected static ?string $navigationGroup = 'Ops';
    protected static ?int $navigationSort = 2;
    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';
    protected static ?string $recordTitleAttribute = 'mga_reference';

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::whereIn('status', ['New', 'Handling', 'Available', 'Confirmed', 'Hold'])->count();
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }



    public static function form(Form $form): Form
    {
        return $form->schema([
            Checkbox::make('new_patient')->label('New Patient')->default(true)->live()
                ->disabled(fn ($context) => $context === 'edit'),
            Select::make('patient_id')
                ->relationship(
                    'patient',
                    'name',
                    fn ($query) => $query->whereHas('client', fn ($q) => $q->where('status', 'Active'))
                )
                ->getOptionLabelFromRecordUsing(fn ($record) => "{$record->name} - {$record->client->company_name}")
                ->label('Patient')
                ->required()
                ->live(onBlur: false)
                ->searchable()
                ->preload()
                ->disabled(fn ($context) => $context === 'edit')
                ->dehydrated()
                ->afterStateUpdated(function ($state, callable $set) {
                    $set('mga_reference', File::generateMGAReference($state, 'patient'));
                })->hidden(fn ($get) => $get('new_patient') == true),
            PatientNameInput::make('patient_name')->label('Patient Name')->required()->hidden(fn ($get) => $get('new_patient') == false),
            DatePicker::make('patient_dob')->label('Date of Birth')->nullable()->hidden(fn ($get) => $get('new_patient') == false),
            Select::make('patient_gender')->label('Gender')->options(['Male' => 'Male', 'Female' => 'Female'])->nullable()->hidden(fn ($get) => $get('new_patient') == false),
            Select::make('client_id')->options(Client::where('status', 'Active')->pluck('company_name', 'id'))->searchable()->preload()->required()->live()->afterStateUpdated(function ($state, callable $set) {
                    $set('mga_reference', File::generateMGAReference($state, 'client'));
                })
                ->label('Client')
                ->hidden(fn ($get) => !$get('new_patient')),
            TextInput::make('mga_reference')->label('MGA Reference')->required()->readOnly()->unique(ignoreRecord: true)->helperText('Auto-generated based on the patient'),
            Select::make('service_type_id')->relationship('serviceType', 'name')->label('Service Type')->required()->live(),
            TextInput::make('client_reference')->label('Client Reference')->nullable(),
            Select::make('country_id')->relationship('country', 'name')->label('Country')->preload()->searchable()->nullable()->live(),
            Select::make('city_id')->label('City')->searchable()->nullable()->options(fn ($get) => \App\Models\City::where('country_id', $get('country_id'))->pluck('name', 'id'))->reactive(),
            Select::make('provider_branch_id')->label('Provider Branch')->searchable()->nullable()->options(fn ($get) => \App\Models\ProviderBranch::when($get('service_type_id') != 2, function ($query) use ($get) {
                return $query->whereHas('branchCities', fn ($q) => $q->where('city_id', $get('city_id')));
            })->when($get('service_type_id'), function ($query) use ($get) {
                return $query->whereHas('services', function ($q) use ($get) {
                    $q->where('service_type_id', $get('service_type_id'));
                });
            })->orderBy('priority', 'asc')->pluck('branch_name', 'id'))->reactive(),
            Select::make('status')->options(['New' => 'New','Handling' => 'Handling','Available' => 'Available', 'Confirmed' => 'Confirmed', 'Assisted' => 'Assisted','Hold' => 'Hold','Waiting MR' => 'Waiting MR','Refund' => 'Refund','Cancelled' => 'Cancelled','Void' => 'Void',])->default('New')->required()->live(),
            DatePicker::make('service_date')->label('Service Date')->nullable(),
            TimePicker::make('service_time')->label('Service Time')->nullable(),
            TextInput::make('email')->label('Email')->email()->nullable(),
            TextInput::make('phone')->label('Phone')->tel()->nullable(),
            TextInput::make('address')->label('Address')->nullable(),
            Select::make('contact_patient')->label('Who will Contact the Patient?')->options(['Client' => 'Client','MGA' => 'MGA', 'Ask' => 'Ask'])->default('Client'),
            Textarea::make('symptoms')->label('Symptoms')->nullable(),
            Textarea::make('diagnosis')->label('Diagnosis')->nullable(),
        ]);
    }


    public static function table(Table $table): Table
    {
        // sort by service_date
        return $table->groups([
            Group::make('patient.client.company_name')->collapsible()->label('Client'),
            Group::make('status')->collapsible(),
            Group::make('country.name')->collapsible()->label('Country'),
            Group::make('serviceType.name')->collapsible()->label('Service Type'),
        ])
            ->modifyQueryUsing(fn ($query) => $query->with([
                'patient.client',
                'country',
                'city',
                'serviceType',
                'comments',
                'providerBranch.provider',
                'gops',
                'bills'
            ]))
            ->defaultSort('created_at', 'desc')
            ->columns([
                // Enhanced columns
                     
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Case Date')
                    ->date('d/m/Y')
                    ->sortable()
                    ->searchable(),
                    
                Tables\Columns\TextColumn::make('patient.client.company_name')
                    ->label('Client')
                    ->description(fn ($record) => $record->client_reference ? "Ref: {$record->client_reference}" : null)
                    ->sortable()
                    ->searchable(),
                
                Tables\Columns\TextColumn::make('patient.name')
                    ->label('Patient')
                    ->description(fn ($record) => 
                        $record->mga_reference . 
                        ($record->patient?->dob ? ' | DOB: ' . $record->patient->dob->format('d/m/Y') : '') .
                        ($record->patient?->gender ? ' | ' . $record->patient->gender : '')
                    )
                    ->sortable()
                    ->searchable(),
                
                Tables\Columns\TextColumn::make('country.name')
                    ->label('Location')
                    ->description(fn ($record) => $record->city?->name)
                    ->sortable()
                    ->searchable(),
                
                Tables\Columns\TextColumn::make('service_date')
                    ->label('Service Date')
                    ->date('d/m/Y')
                    ->description(fn ($record) => 
                        ($record->serviceType?->name ?? 'No Service Type') . 
                        ($record->service_time ? ' at ' . \Carbon\Carbon::parse($record->service_time)->format('H:i') : '')
                    )
                    ->sortable()
                    ->searchable()
                    ->placeholder('No Date Set'),
                
                Tables\Columns\TextColumn::make('providerBranch.branch_name')
                    ->label('Provider')
                    ->description(fn ($record) => $record->providerBranch?->provider?->name)
                    ->sortable()
                    ->searchable(),
                
                Tables\Columns\TextColumn::make('status')
                    ->sortable()
                    ->searchable()
                    ->badge()
                    ->color(fn ($state) => match ($state) {
                        'New' => 'success',
                        'Handling' => 'info',
                        'Available' => 'info',
                        'Confirmed' => 'success',
                        'Assisted' => 'success',
                        'Hold' => 'warning',
                        'Waiting MR' => 'primary',
                        'Refund' => 'primary',
                        'Cancelled' => 'danger',
                        'Void' => 'gray',
                    }),
                
                Tables\Columns\TextColumn::make('last_comment')
                    ->label('Last Comment')
                    ->getStateUsing(function ($record) {
                        try {
                            $lastComment = $record->comments()->latest()->first();
                            return $lastComment ? $lastComment->content : null;
                        } catch (\Exception $e) {
                            return null;
                        }
                    })
                    ->limit(50)
                    ->placeholder('No comments')
                    ->weight('bold')
                    ->extraAttributes(['class' => 'text-xs']),
                
                Tables\Columns\TextColumn::make('first_gop_in_amount')
                    ->label('GOP')
                    ->badge()
                    ->color(function ($state, $record) {
                        $firstGopIn = $record->gops()->where('type', 'In')->first();
                        if (!$firstGopIn) {
                            return 'danger'; // No GOP exists
                        }
                        // Green if attached (has document_path), red if not attached
                        return !empty($firstGopIn->document_path) ? 'success' : 'danger';
                    })
                    ->formatStateUsing(function ($state, $record) {
                        $firstGopIn = $record->gops()->where('type', 'In')->first();
                        return $firstGopIn ? '€' . number_format($firstGopIn->amount, 2) : '€0.00';
                    })
                    ->getStateUsing(function ($record) {
                        $firstGopIn = $record->gops()->where('type', 'In')->first();
                        return $firstGopIn ? $firstGopIn->amount : 0;
                    }),
                
                Tables\Columns\TextColumn::make('bills_details')
                    ->label('Bills')
                    ->state(function (File $record) {
                        // Ensure bills are loaded
                        if (!$record->relationLoaded('bills')) {
                            $record->load('bills');
                        }
                        
                        $bills = $record->bills;
                        
                        if ($bills->isEmpty()) {
                            return 'No Bills';
                        }
                        
                        $badges = [];
                        foreach ($bills as $bill) {
                            $hasAttachment = !empty($bill->bill_document_path) || !empty($bill->bill_google_link);
                            $amount = '€' . number_format($bill->total_amount, 2);
                            
                            $paymentStatus = match($bill->status) {
                                'Paid' => 'Paid',
                                'Partial' => 'Partial',
                                'Unpaid' => 'Not Paid',
                                default => $bill->status ?? 'Unknown',
                            };
                            
                            // Amount badge - green if attachment present, red if not
                            $amountBgColor = $hasAttachment ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' : 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200';
                            
                            // Payment status badge
                            $paymentBgColor = match($bill->status) {
                                'Paid' => 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
                                'Partial' => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200',
                                'Unpaid' => 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200',
                                default => 'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-200',
                            };
                            
                            $badges[] = sprintf(
                                '<div class="flex gap-1"><span class="inline-flex items-center rounded-md px-2 py-1 text-xs font-medium ring-1 ring-inset %s">%s</span> <span class="inline-flex items-center rounded-md px-2 py-1 text-xs font-medium ring-1 ring-inset %s">%s</span></div>',
                                $amountBgColor,
                                $amount,
                                $paymentBgColor,
                                $paymentStatus
                            );
                        }
                        
                        return '<div class="flex flex-col gap-1">' . implode('', $badges) . '</div>';
                    })
                    ->html(),
                
                Tables\Columns\TextColumn::make('email')
                    ->label('Email')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('phone')
                    ->label('Phone')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Filter::make('opened_cases')
                    ->label('Opened Cases Only')
                    ->default(true)
                    ->query(function (Builder $query) {
                        return $query->whereIn('status', ['New', 'Handling', 'Available', 'Confirmed', 'Hold']);
                    })
                    ->indicateUsing(function (array $data): ?string {
                        if ($data['opened_cases'] ?? false) {
                            return 'Opened Cases Only';
                        }
                        return null;
                    }),
                Filter::make('client_id')
                    ->label('Client')
                    ->form([
                        Forms\Components\Select::make('client_id')
                            ->label('Client')
                            ->options(Client::where('status', 'Active')->pluck('company_name', 'id'))
                            ->searchable()
                            ->preload(),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        if (!empty($data['client_id'])) {
                            return $query->whereHas('patient', function ($q) use ($data) {
                                $q->where('client_id', $data['client_id']);
                            });
                        }
                        return $query;
                    }),
                Filter::make('case_date')
                    ->label('Case Date')
                    ->form([
                        Forms\Components\DatePicker::make('case_date_from')
                            ->label('From'),
                        Forms\Components\DatePicker::make('case_date_until')
                            ->label('Until'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                !empty($data['case_date_from']),
                                fn (Builder $query): Builder => $query->whereDate('created_at', '>=', $data['case_date_from']),
                            )
                            ->when(
                                !empty($data['case_date_until']),
                                fn (Builder $query): Builder => $query->whereDate('created_at', '<=', $data['case_date_until']),
                            );
                    }),
                SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'New' => 'New',
                        'Handling' => 'Handling',
                        'Available' => 'Available',
                        'Confirmed' => 'Confirmed',
                        'Assisted' => 'Assisted',
                        'Hold' => 'Hold',
                        'Waiting MR' => 'Waiting MR',
                        'Refund' => 'Refund',
                        'Cancelled' => 'Cancelled',
                        'Void' => 'Void',
                    ]),
                SelectFilter::make('country_id')
                    ->label('Country')
                    ->options(\App\Models\Country::pluck('name', 'id')),
                SelectFilter::make('city_id')
                    ->label('City')
                    ->options(\App\Models\City::pluck('name', 'id')),
                SelectFilter::make('service_type_id')
                    ->label('Service Type')
                    ->options(\App\Models\ServiceType::pluck('name', 'id')),
            ])
            ->actions([
                Tables\Actions\Action::make('View')
                ->url(fn (File $record) => FileResource::getUrl('view', ['record' => $record->id]))
                ->icon('heroicon-o-eye'),
                Tables\Actions\Action::make('Edit')
                ->url(fn (File $record) => FileResource::getUrl('edit', ['record' => $record->id]))
                ->icon('heroicon-o-pencil'),

            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            GopRelationManager::class,
            BillRelationManager::class,
            MedicalReportRelationManager::class,
            PrescriptionRelationManager::class,
            PatientRelationManager::class,
            CommentsRelationManager::class,
            AppointmentsRelationManager::class,
            TaskRelationManager::class,
            BankAccountRelationManager::class,
            InvoiceRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => FileResource\Pages\ListFiles::route('/'),
            'create' => FileResource\Pages\CreateFile::route('/create'),
            'edit' => FileResource\Pages\EditFile::route('/{record}/edit'),
            'view' => FileResource\Pages\ViewFile::route('/{record}/show'),
        ];
    }

    public static function getGlobalSearchResultTitle(\Illuminate\Database\Eloquent\Model $record): string
    {
        return $record->mga_reference . ' - ' . ($record->patient?->name ?? 'Unknown Patient');
    }

    public static function getGlobalSearchResultDetails(\Illuminate\Database\Eloquent\Model $record): array
    {
        return [
            'Patient' => $record->patient?->name ?? 'Unknown',
            'Client' => $record->patient?->client?->company_name ?? 'Unknown',
            'Status' => $record->status ?? 'Unknown',
            'Service Date' => $record->service_date?->format('d/m/Y') ?? 'Unknown',
        ];
    }

    public static function getGlobalSearchEloquentQuery(): Builder
    {
        return parent::getGlobalSearchEloquentQuery()
            ->with(['patient.client', 'country', 'city', 'serviceType']);
    }

    public static function getGlobalSearchResultUrl(\Illuminate\Database\Eloquent\Model $record): string
    {
        return FileResource::getUrl('view', ['record' => $record]);
    }
}
