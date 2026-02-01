<?php

namespace App\Filament\Resources;

use App\Filament\Resources\EmployeeResource\Pages;
use App\Filament\Resources\EmployeeResource\RelationManagers\SalaryRelationManager;
use App\Filament\Resources\EmployeeResource\RelationManagers\ShiftScheduleRelationManager;
use App\Models\Employee;
use App\Models\JobTitle;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class EmployeeResource extends Resource
{
    protected static ?string $model = Employee::class;

    protected static ?string $navigationGroup = 'HR';
    protected static ?int $navigationSort = 2;
    protected static ?string $navigationIcon = 'heroicon-o-user-group';
    protected static ?string $modelLabel = 'Employee';
    protected static ?string $pluralModelLabel = 'Employees';

    public static function shouldRegisterNavigation(): bool
    {
        return Auth::check() && Auth::user()?->roles?->contains('name', 'admin');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Personal & employment')
                    ->schema([
                        Forms\Components\TextInput::make('name')->required()->maxLength(255),
                        Forms\Components\Select::make('user_id')
                            ->relationship('user', 'name')
                            ->searchable()
                            ->preload()
                            ->nullable()
                            ->helperText('Link to login user (optional)'),
                        Forms\Components\Select::make('job_title_id')
                            ->relationship('jobTitle', 'name')
                            ->required()
                            ->searchable()
                            ->preload(),
                        Forms\Components\Select::make('manager_id')
                            ->relationship('manager', 'name')
                            ->searchable()
                            ->preload()
                            ->nullable(),
                        Forms\Components\Select::make('department')
                            ->required()
                            ->options([
                                'Operation' => 'Operation',
                                'Financial' => 'Financial',
                                'Client Network' => 'Client Network',
                                'Provider Network' => 'Provider Network',
                            ]),
                        Forms\Components\DatePicker::make('start_date')->nullable(),
                        Forms\Components\Select::make('status')
                            ->options(['active' => 'Active', 'inactive' => 'Inactive'])
                            ->default('active')
                            ->required(),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Contact & identity')
                    ->schema([
                        Forms\Components\DatePicker::make('date_of_birth')->nullable(),
                        Forms\Components\TextInput::make('national_id')->maxLength(255)->nullable(),
                        Forms\Components\TextInput::make('phone')->tel()->maxLength(255)->nullable(),
                        Forms\Components\TextInput::make('social_insurance_number')->maxLength(255)->nullable(),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Compensation & documents')
                    ->schema([
                        Forms\Components\TextInput::make('basic_salary')
                            ->numeric()
                            ->minValue(0)
                            ->default(0)
                            ->required(),
                        Forms\Components\Select::make('bank_account_id')
                            ->relationship('bankAccount', 'beneficiary_name')
                            ->searchable()
                            ->preload()
                            ->nullable(),
                        Forms\Components\Toggle::make('signed_contract')->default(false),
                        Forms\Components\FileUpload::make('signed_contract_path')
                            ->label('Signed contract (file)')
                            ->directory('employee-contracts')
                            ->nullable(),
                        Forms\Components\FileUpload::make('photo_id_path')
                            ->label('Photo of ID')
                            ->directory('employee-photo-ids')
                            ->image()
                            ->nullable(),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('jobTitle.name')->label('Job title')->sortable(),
                Tables\Columns\TextColumn::make('department')->sortable(),
                Tables\Columns\TextColumn::make('phone')->searchable(),
                Tables\Columns\TextColumn::make('basic_salary')->money()->sortable(),
                Tables\Columns\TextColumn::make('start_date')->date()->sortable(),
                Tables\Columns\TextColumn::make('status')->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'active' => 'success',
                        'inactive' => 'gray',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('manager.name')->label('Manager')->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('department')
                    ->options([
                        'Operation' => 'Operation',
                        'Financial' => 'Financial',
                        'Client Network' => 'Client Network',
                        'Provider Network' => 'Provider Network',
                    ]),
                Tables\Filters\SelectFilter::make('status')
                    ->options(['active' => 'Active', 'inactive' => 'Inactive']),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
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
            ShiftScheduleRelationManager::class,
            SalaryRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListEmployees::route('/'),
            'create' => Pages\CreateEmployee::route('/create'),
            'edit' => Pages\EditEmployee::route('/{record}/edit'),
        ];
    }
}
