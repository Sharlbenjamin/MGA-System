<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\PatientResource\Pages;
use App\Filament\Admin\Resources\PatientResource\RelationManagers;
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

    protected static ?string $navigationGroup = null; // Removes it from any group
    protected static ?int $navigationSort = null; // Ensures it's not sorted
    protected static ?string $navigationIcon = null; // Hides from sidebar
    protected static bool $shouldRegisterNavigation = false; // Hides it completely

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')->required(),
                Forms\Components\Select::make('client_id')->relationship('client', 'company_name')->required(),
                Forms\Components\DatePicker::make('dob')->nullable(),
                Forms\Components\Select::make('gender')->options(['male' => 'Male','female' => 'Female','other' => 'Other',])->nullable(),
                Forms\Components\Select::make('country_id')->relationship('country', 'name')->label('Country')->searchable()->nullable(),
            ]);
    }


    public static function table(Table $table): Table
{
    return $table
        ->columns([
            Tables\Columns\TextColumn::make('id')->sortable(),
            Tables\Columns\TextColumn::make('name')->sortable()->searchable(),
            Tables\Columns\TextColumn::make('client.company_name')
                ->label('Client'),
            // Calculate Age in Years and Months
            Tables\Columns\TextColumn::make('dob')
                ->label('Age')
                ->getStateUsing(function ($record) {
                    $dob = \Carbon\Carbon::parse($record->dob);
                    return $dob->diff(\Carbon\Carbon::now())->format('%y y, %m m');
                })
                ->sortable(),
            // Display Country Name via Relationship
            Tables\Columns\TextColumn::make('country.name')
                ->label('Country')
                ->sortable()
                ->searchable(),
        ])
        ->filters([
            Tables\Filters\SelectFilter::make('client_id')
            ->label('Client')
            ->relationship('client', 'company_name'),
    ])
        ->actions([
        ])
        ->bulkActions([
            Tables\Actions\DeleteBulkAction::make(),
        ]);
}
    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPatients::route('/'),
            'create' => Pages\CreatePatient::route('/create'),
            'edit' => Pages\EditPatient::route('/{record}/edit'),
        ];
    }
}
