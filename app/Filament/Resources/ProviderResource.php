<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProviderResource\RelationManagers\BillRelationManager;
use App\Filament\Resources\ProviderResource\RelationManagers\BankAccountRelationManager;
use App\Filament\RelationManagers\ActivityLogRelationManager;
use App\Filament\Resources\ProviderResource\Pages;
use App\Filament\Resources\ProviderResource\RelationManagers\ProviderBranchRelationManager;
use App\Filament\Resources\ProviderResource\RelationManagers\ProviderLeadRelationManager;
use App\Models\Provider;
use App\Models\Country;
use App\Models\City;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\FileUpload;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Resources\Resource;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\Filter;
use Filament\Forms\Components\Checkbox;
use App\Models\Contact;
use Illuminate\Database\Eloquent\Builder;
class ProviderResource extends Resource
{
    protected static ?string $model = Provider::class;

    protected static ?string $navigationGroup = 'PRM';
    protected static ?int $navigationSort = 1;
    protected static ?string $navigationIcon = 'heroicon-o-truck';
    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Forms\Form $form): Forms\Form
{
    return $form
        ->schema([
            Select::make('country_id')->label('Country')->options(Country::pluck('name', 'id'))->searchable()->required(),
            Select::make('status')->label('Status')->options([
                'Active' => 'Active',
                'Hold' => 'Hold',
                'Potential' => 'Potential',
                'Black List' => 'Black List',
            ])->required()->live(),
            Select::make('assigned_user_id')
                ->label('Assigned to (employee)')
                ->helperText('For Potential providers only. No task is created.')
                ->options(User::query()->orderBy('name')->pluck('name', 'id'))
                ->searchable()
                ->nullable()
                ->visible(fn (Forms\Get $get): bool => $get('status') === 'Potential'),
            Select::make('type')->label('Provider Type')->options([
                'Doctor' => 'Doctor',
                'Hospital' => 'Hospital',
                'Clinic' => 'Clinic',
                'Dental' => 'Dental',
                'Agency' => 'Agency',
            ])->required(),
            TextInput::make('name')->label('Provider Name')->required()->maxLength(255),

            TextInput::make('payment_due')->label('Payment Due (Days)')->numeric()->minValue(0)->nullable(),
            Select::make('payment_method')->label('Payment Method')->options([
                'Online Link' => 'Online Link',
                'Bank Transfer' => 'Bank Transfer',
                'AEAT' => 'AEAT',
            ])->nullable(),
            Textarea::make('comment')->label('Comment')->nullable(),
            Select::make('gop_contact_id')->label('GOP Contact')->options(Contact::pluck('name', 'id'))->searchable()->nullable(),
            Select::make('operation_contact_id')->label('Operation Contact')->options(Contact::pluck('name', 'id'))->searchable()->nullable(),
            Select::make('financial_contact_id')->label('Financial Contact')->options(Contact::pluck('name', 'id'))->searchable()->nullable(),
            TextInput::make('phone')->label('Phone')->tel()->nullable(),
            TextInput::make('email')->label('Email')->email()->nullable(),
            FileUpload::make('signed_contract_draft')
                ->label('Signed Contract Draft (PDF)')
                ->acceptedFileTypes(['application/pdf'])
                ->maxSize(10240) // 10MB
                ->nullable()
                ->disk('public')
                ->directory('contracts/providers')
                ->visibility('public')
                ->helperText('Upload a PDF draft of the signed contract')
                ->downloadable()
                ->openable()
                ->preserveFilenames()
                ->maxFiles(1),
        ]);
}

public static function table(Tables\Table $table): Tables\Table
{
    return $table
        ->columns([
            TextColumn::make('name')->label('Provider Name')->sortable()->searchable(),
            TextColumn::make('country.name')->label('Country')->sortable()->searchable(), // ✅ Fix: Show Country Name
            BadgeColumn::make('status')
                ->colors([
                    'success' => 'Active',
                    'warning' => 'Hold',
                    'info' => 'Potential',
                    'red' => 'Black List',
                ])
                ->sortable(),
            TextColumn::make('assignedUser.name')->label('Assigned to')->sortable()->placeholder('—'),
            TextColumn::make('filesCount')->label('Files')->sortable()->counts('files'),
                TextColumn::make('filesCancelledCount')->label('Canceled')->sortable(),
                TextColumn::make('filesAssistedCount')->label('Assisted')->sortable(),
                TextColumn::make('billsTotalNumber')->label('Bills')->sortable(),
                TextColumn::make('billsTotal')->label('Bills Amount')->sortable()->money('eur'),
                TextColumn::make('billsTotalNumberPaid')->label('Paid Bills')->sortable(),
                TextColumn::make('billsTotalPaid')->label('Paid Bills Amount')->sortable()->money('eur'),
                TextColumn::make('billsTotalNumberOutstanding')->label('Unpaid Bills')->sortable(),
                TextColumn::make('billsTotalOutstanding')->label('Unpaid Bills Amount')->sortable()->money('eur'),
                TextColumn::make('transactionsLastDate')->label('Last Transaction Date')->date('d-m-Y')->sortable(),
                TextColumn::make('transactionLastAmount')->label('Last Transaction Amount')->sortable()->money('eur'),
        ])
        ->filters([
            SelectFilter::make('status')->multiple()
                    ->options([
                        'Active' => 'Active',
                        'Hold' => 'Hold',
                        'Potential' => 'Potential',
                        'Black List' => 'Black List',
                    ])->label('Filter by Status')->attribute('status'),
            Filter::make('my_providers')
                ->label('My providers')
                ->form([
                    Checkbox::make('value')->label('Only providers assigned to me'),
                ])
                ->query(function (Builder $query, array $data): Builder {
                    if (! empty($data['value'])) {
                        return $query->where('assigned_user_id', auth()->id());
                    }
                    return $query;
                })
                ->indicateUsing(fn (array $data): array => ! empty($data['value']) ? ['My providers' => 'Yes'] : []),
            SelectFilter::make('country_id')->multiple()
                    ->options(Country::pluck('name', 'id'))->label('Filter by Country')->attribute('country_id'),
        ])
        ->actions([
            Tables\Actions\EditAction::make(),
            Tables\Actions\Action::make('Overview')
            ->url(fn (Provider $record) => ProviderResource::getUrl('overview', ['record' => $record]))->color('success'),
            Tables\Actions\DeleteAction::make(),
        ])
        ->bulkActions([
            Tables\Actions\DeleteBulkAction::make(),
        ]);
}

    public static function getRelations(): array
    {
        return [
            ProviderLeadRelationManager::class,
            ProviderBranchRelationManager::class,
            BankAccountRelationManager::class,
            BillRelationManager::class,
            ActivityLogRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProviders::route('/'),
            'create' => Pages\CreateProvider::route('/create'),
            'edit' => Pages\EditProvider::route('/{record}/edit'),
            'overview' => Pages\ProviderOverView::route('/{record}'),
        ];
    }

    public static function getGlobalSearchResultTitle(\Illuminate\Database\Eloquent\Model $record): string
    {
        return ($record->name ?? 'Unknown') . ' (' . ($record->type ?? 'Unknown') . ')';
    }

    public static function getGlobalSearchResultDetails(\Illuminate\Database\Eloquent\Model $record): array
    {
        return [
            'Country' => $record->country?->name ?? 'Unknown',
            'Status' => $record->status ?? 'Unknown',
            'Type' => $record->type ?? 'Unknown',
            'Files' => $record->filesCount ?? $record->files()->count(),
            'Phone' => $record->phone ?? 'Unknown',
            'Email' => $record->email ?? 'Unknown',
        ];
    }

    public static function getGlobalSearchEloquentQuery(): Builder
    {
        return parent::getGlobalSearchEloquentQuery()
            ->with(['country'])
            ->withCount('files');
    }

    public static function getGlobalSearchResultUrl(\Illuminate\Database\Eloquent\Model $record): string
    {
        return ProviderResource::getUrl('overview', ['record' => $record]);
    }

    public static function isGlobalSearchDisabled(): bool
    {
        return true;
    }
}