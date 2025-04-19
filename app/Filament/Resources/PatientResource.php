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
                Forms\Components\TextInput::make('name')->required(),
                Forms\Components\Select::make('client_id')->relationship('client', 'company_name', fn ($query) => $query->where('status', 'Active'))->searchable()->preload()->required(),
                Forms\Components\DatePicker::make('dob')->nullable(),
                Forms\Components\Select::make('gender')->options(['male' => 'Male','female' => 'Female','other' => 'Other',])->nullable(),
                Forms\Components\Select::make('country_id')->relationship('country', 'name')->label('Country')->searchable()->nullable(),
                Forms\Components\Select::make('gop_contact_id')->label('GOP Contact')->options(Contact::pluck('title', 'id'))->searchable()->nullable(),
                Forms\Components\Select::make('operation_contact_id')->label('Operation Contact')->options(Contact::pluck('title', 'id'))->searchable()->nullable(),
                Forms\Components\Select::make('financial_contact_id')->label('Financial Contact')->options(Contact::pluck('title', 'id'))->searchable()->nullable(),
            ]);
    }


    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->withCount('files'))
            ->columns([
                Tables\Columns\TextColumn::make('client.company_name')->label('Client')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('name')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('client.company_name')
                    ->label('Client'),
                Tables\Columns\TextColumn::make('dob')
                    ->label('Age')
                    ->getStateUsing(function ($record) {
                        $dob = \Carbon\Carbon::parse($record->dob);
                        return $dob->diff(\Carbon\Carbon::now())->format('%y y, %m m');
                    })
                    ->sortable(),
                Tables\Columns\TextColumn::make('country.name')->label('Country')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('files_count')
                    ->label('Files')
                    ->badge()
                    ->color('success')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('client_id')->label('Client')->relationship('client', 'company_name'),
            ])
            ->actions([
            ])
            ->bulkActions([
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
}
