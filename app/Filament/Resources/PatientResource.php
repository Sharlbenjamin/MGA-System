<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PatientResource\Pages;
use App\Filament\Resources\PatientResource\RelationManagers\FileRelationManager;
use App\Filament\Resources\PatientResource\RelationManagers\InvoiceRelationManager;
use App\Filament\Resources\PatientResource\RelationManagers\BillRelationManager;
use App\Models\Contact;
use App\Models\Patient;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class PatientResource extends Resource
{
    protected static ?string $model = Patient::class;

    protected static ?string $navigationGroup = 'Operation';
    protected static ?int $navigationSort = 1;
    protected static ?string $navigationIcon = 'heroicon-o-user-plus';
    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Basic Information')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('Enter patient full name'),
                        Forms\Components\Select::make('client_id')
                            ->relationship('client', 'company_name', fn ($query) => $query->where('status', 'Active'))
                            ->searchable()
                            ->preload()
                            ->required()
                            ->placeholder('Select client'),
                        Forms\Components\DatePicker::make('dob')
                            ->nullable()
                            ->label('Date of Birth')
                            ->maxDate(now())
                            ->placeholder('Select date of birth'),
                        Forms\Components\Select::make('gender')
                            ->options([
                                'Male' => 'Male',
                                'Female' => 'Female',
                                'Other' => 'Other',
                            ])
                            ->nullable()
                            ->placeholder('Select gender'),
                        Forms\Components\Select::make('country_id')
                            ->relationship('country', 'name')
                            ->label('Country')
                            ->searchable()
                            ->nullable()
                            ->placeholder('Select country'),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Contact Information')
                    ->schema([
                        Forms\Components\Select::make('gop_contact_id')
                            ->label('GOP Contact')
                            ->options(Contact::pluck('name', 'id'))
                            ->searchable()
                            ->nullable()
                            ->placeholder('Select GOP contact'),
                        Forms\Components\Select::make('operation_contact_id')
                            ->label('Operation Contact')
                            ->options(Contact::pluck('name', 'id'))
                            ->searchable()
                            ->nullable()
                            ->placeholder('Select operation contact'),
                        Forms\Components\Select::make('financial_contact_id')
                            ->label('Financial Contact')
                            ->options(Contact::pluck('name', 'id'))
                            ->searchable()
                            ->nullable()
                            ->placeholder('Select financial contact'),
                    ])
                    ->columns(3)
                    ->collapsible()
                    ->collapsed(),

                Forms\Components\Section::make('Additional Information')
                    ->schema([
                        Forms\Components\TextInput::make('age_display')
                            ->label('Age')
                            ->disabled()
                            ->dehydrated(false)
                            ->afterStateHydrated(function ($component, $state, $record) {
                                if ($record && $record->dob) {
                                    $age = \Carbon\Carbon::parse($record->dob)->diff(\Carbon\Carbon::now())->format('%y years, %m months');
                                    $component->state($age);
                                } else {
                                    $component->state('N/A');
                                }
                            }),
                        Forms\Components\TextInput::make('files_count_display')
                            ->label('Total Files')
                            ->disabled()
                            ->dehydrated(false)
                            ->afterStateHydrated(function ($component, $state, $record) {
                                if ($record) {
                                    $count = $record->files_count ?? $record->files()->count();
                                    $component->state($count);
                                } else {
                                    $component->state('0');
                                }
                            }),
                    ])
                    ->columns(2)
                    ->collapsible()
                    ->collapsed(),
            ]);
    }


    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->withCount('files'))
            ->columns([
                Tables\Columns\TextColumn::make('client.company_name')
                    ->label('Client')
                    ->sortable()
                    ->searchable()
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('name')
                    ->sortable()
                    ->searchable()
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('dob')
                    ->label('Age')
                    ->getStateUsing(function ($record) {
                        if (!$record->dob) return 'N/A';
                        $dob = \Carbon\Carbon::parse($record->dob);
                        return $dob->diff(\Carbon\Carbon::now())->format('%y y, %m m');
                    })
                    ->sortable()
                    ->searchable(false),
                Tables\Columns\TextColumn::make('gender')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'Male' => 'info',
                        'Female' => 'warning',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('country.name')
                    ->label('Country')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('files_count')
                    ->label('Files')
                    ->badge()
                    ->color('success')
                    ->sortable(),
                Tables\Columns\TextColumn::make('invoices_total_outstanding')
                    ->label('Outstanding')
                    ->money('USD')
                    ->color(fn ($state) => $state > 0 ? 'danger' : 'success')
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime('M j, Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('client_id')
                    ->label('Client')
                    ->relationship('client', 'company_name'),
                Tables\Filters\SelectFilter::make('gender')
                    ->options([
                        'Male' => 'Male',
                        'Female' => 'Female',
                        'Other' => 'Other',
                    ]),
                Tables\Filters\SelectFilter::make('country_id')
                    ->label('Country')
                    ->relationship('country', 'name'),
                Tables\Filters\Filter::make('has_outstanding_financials')
                    ->label('Has Outstanding Financials')
                    ->query(fn (Builder $query): Builder => $query->whereHas('invoices', fn ($q) => $q->whereRaw('total_amount > paid_amount')))
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];
                        if ($data['has_outstanding_financials'] ?? false) {
                            $indicators['has_outstanding_financials'] = 'Has Outstanding Financials';
                        }
                        return $indicators;
                    }),
                Tables\Filters\Filter::make('has_files')
                    ->label('Has Files')
                    ->query(fn (Builder $query): Builder => $query->has('files'))
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];
                        if ($data['has_files'] ?? false) {
                            $indicators['has_files'] = 'Has Files';
                        }
                        return $indicators;
                    }),
            ])
            ->actions([
                Tables\Actions\Action::make('edit')
                    ->label('Edit')
                    ->icon('heroicon-o-pencil')
                    ->url(fn (Patient $record): string => PatientResource::getUrl('edit', ['record' => $record])),
                Tables\Actions\Action::make('view_files')
                    ->label('Files')
                    ->icon('heroicon-o-clipboard-document-list')
                    ->url(fn (Patient $record): string => PatientResource::getUrl('index', ['tableFilters[client_id][value]' => $record->client_id]))
                    ->openUrlInNewTab()
                    ->badge(fn (Patient $record): string => $record->files_count ?? $record->files()->count())
                    ->color('success'),
                Tables\Actions\Action::make('financial_view')
                    ->label('Financial')
                    ->icon('heroicon-o-currency-dollar')
                    ->url(fn (Patient $record): string => PatientResource::getUrl('financial', ['record' => $record]))
                    ->openUrlInNewTab(),
            ])
            ->bulkActions([
                Tables\Actions\BulkAction::make('export')
                    ->label('Export Selected')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->action(function ($records) {
                        // Export logic would go here
                        \Filament\Notifications\Notification::make()
                            ->success()
                            ->title('Export Started')
                            ->body('Patient data export has been initiated.')
                            ->send();
                    }),
                Tables\Actions\BulkAction::make('update_client')
                    ->label('Update Client')
                    ->icon('heroicon-o-building-office')
                    ->form([
                        Forms\Components\Select::make('client_id')
                            ->label('New Client')
                            ->relationship('client', 'company_name', fn ($query) => $query->where('status', 'Active'))
                            ->searchable()
                            ->preload()
                            ->required(),
                    ])
                    ->action(function ($records, array $data) {
                        $records->each(function ($record) use ($data) {
                            $record->update(['client_id' => $data['client_id']]);
                        });
                        
                        \Filament\Notifications\Notification::make()
                            ->success()
                            ->title('Clients Updated')
                            ->body(count($records) . ' patients have been updated.')
                            ->send();
                    }),
                Tables\Actions\DeleteBulkAction::make(),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            FileRelationManager::class,
            InvoiceRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPatients::route('/'),
            'create' => Pages\CreatePatient::route('/create'),
            'edit' => Pages\EditPatient::route('/{record}/edit'),
            'financial' => Pages\PatientFinancialView::route('/{record}/financial'),
        ];
    }

    public static function getGlobalSearchResultTitle(\Illuminate\Database\Eloquent\Model $record): string
    {
        return $record->name . ' - ' . ($record->client?->company_name ?? 'Unknown Client');
    }

    public static function getGlobalSearchResultDetails(\Illuminate\Database\Eloquent\Model $record): array
    {
        return [
            'Client' => $record->client?->company_name ?? 'Unknown',
            'Country' => $record->country?->name ?? 'Unknown',
            'Age' => $record->dob ? \Carbon\Carbon::parse($record->dob)->diff(\Carbon\Carbon::now())->format('%y years') : 'N/A',
            'Files' => $record->files_count ?? $record->files()->count(),
        ];
    }

    public static function getGlobalSearchEloquentQuery(): Builder
    {
        return parent::getGlobalSearchEloquentQuery()
            ->with(['client', 'country'])
            ->withCount('files');
    }

    public static function getGlobalSearchResultUrl(\Illuminate\Database\Eloquent\Model $record): string
    {
        return PatientResource::getUrl('edit', ['record' => $record]);
    }
}
