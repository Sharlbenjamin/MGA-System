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
use App\Models\Country;
use App\Models\File;
use App\Models\Patient;
use Filament\Forms;
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
use Filament\Tables\Columns\Summarizers\Count;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Grouping\Group;

class FinancialListResource extends Resource
{
    protected static ?string $model = File::class;

    protected static ?string $navigationGroup = 'Finance';
    protected static ?int $navigationSort = 1;
    protected static ?string $navigationIcon = 'heroicon-o-wallet';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('status', 'Assisted')
            ->where(function ($query) {
                $query->doesntHave('bills')
                    ->orDoesntHave('invoices');
            });
    }

    public static function getNavigationBadge(): ?string
    {
        //we need to sum  the total_amount of invocies and make sure it is bigger than the bills sum
        return static::getModel()::where('status', 'Assisted')->where(function ($query) {
            $query->doesntHave('bills')
                ->orDoesntHave('invoices');
        })->count();
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }


    public static function table(Table $table): Table
    {
        return $table
            ->groups([
            Group::make('patient.client.company_name')->collapsible(),
            Group::make('country.name')->collapsible(),
            Group::make('city.name')->collapsible(),
            Group::make('serviceType.name')->collapsible(),
            Group::make('providerBranch.branch_name')->collapsible(),
        ])
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('patient.client.company_name')->label('Client')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('mga_reference')->sortable()->searchable()->summarize(Count::make()),
                Tables\Columns\TextColumn::make('patient.name')->label('Patient')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('country.name')->label('Country')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('city.name')->label('City')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('serviceType.name')->label('Service Type')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('providerBranch.branch_name')->label('Provider Branch')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('service_date')->date()->sortable(),
                Tables\Columns\TextColumn::make('InvoiceAmount')->sortable()->money('EUR'),
                Tables\Columns\TextColumn::make('BillAmount')->sortable()->money('EUR'),

            ])
            ->filters([
                // Filter by status
                SelectFilter::make('status')
                    ->options([
                        'New' => 'New',
                        'Handling' => 'Handling',
                        'Available' => 'Available',
                        'Confirmed' => 'Confirmed',
                        'Assisted' => 'Assisted',
                        'Hold' => 'Hold',
                        'Cancelled' => 'Cancelled',
                        'Void' => 'Void',
                    ]),
                SelectFilter::make('country_id')->options(\App\Models\Country::pluck('name', 'id'))->label('Country'),
                SelectFilter::make('city_id')->options(\App\Models\City::pluck('name', 'id'))->label('City'),
                SelectFilter::make('service_type_id')->options(\App\Models\ServiceType::pluck('name', 'id'))->label('Service Type'),
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
            MedicalReportRelationManager::class,
            PrescriptionRelationManager::class,
            PatientRelationManager::class,
            BankAccountRelationManager::class,
            BillRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => FinancialListResource\Pages\ListFiles::route('/'),
            'create' => FileResource\Pages\CreateFile::route('/create'),
            'edit' => FileResource\Pages\EditFile::route('/{record}/edit'),
            'view' => FileResource\Pages\ViewFile::route('/{record}/show'),
        ];
    }

}
