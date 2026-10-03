<?php

namespace App\Filament\Resources;

use App\Filament\Pages\EmploymentContractDraft;
use App\Filament\Resources\EmployeeResource\Pages;
use App\Filament\Resources\EmployeeResource\RelationManagers\SalaryRelationManager;
use App\Filament\Resources\EmployeeResource\RelationManagers\ShiftScheduleRelationManager;
use App\Models\Employee;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

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
                        Forms\Components\DatePicker::make('start_date')
                            ->label('Hiring date')
                            ->nullable(),
                        Forms\Components\DatePicker::make('end_date')
                            ->label('Leaving / firing date')
                            ->nullable()
                            ->visible(fn (Forms\Get $get): bool => $get('status') === Employee::STATUS_FORMER),
                        Forms\Components\Select::make('status')
                            ->options(Employee::statusOptions())
                            ->default(Employee::STATUS_INTERVIEWING)
                            ->required()
                            ->live(),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Application & interview')
                    ->schema([
                        Forms\Components\FileUpload::make('cv_path')
                            ->label('CV')
                            ->directory('employee-cvs')
                            ->nullable(),
                        Forms\Components\DateTimePicker::make('interview_date')->nullable(),
                        Forms\Components\TextInput::make('linkedin_url')
                            ->label('LinkedIn')
                            ->url()
                            ->maxLength(255)
                            ->nullable(),
                        Forms\Components\Select::make('employment_type')
                            ->label('Full time or part time')
                            ->options([
                                'full_time' => 'Full time',
                                'part_time' => 'Part time',
                            ])
                            ->nullable(),
                        Forms\Components\TextInput::make('notice_period')->maxLength(255)->nullable(),
                        Forms\Components\Select::make('english_level')
                            ->options([
                                'basic' => 'Basic',
                                'good' => 'Good',
                                'fluent' => 'Fluent',
                                'native' => 'Native',
                            ])
                            ->nullable(),
                        Forms\Components\Toggle::make('flexible_shifts')->nullable(),
                        Forms\Components\TextInput::make('expected_salary')
                            ->numeric()
                            ->minValue(0)
                            ->nullable(),
                        Forms\Components\TextInput::make('offered_salary')
                            ->numeric()
                            ->minValue(0)
                            ->nullable(),
                        Forms\Components\Textarea::make('reason_for_leaving')
                            ->label('Why leave last job?')
                            ->rows(3)
                            ->columnSpanFull()
                            ->nullable(),
                        Forms\Components\Toggle::make('has_laptop')
                            ->label('Has laptop')
                            ->nullable(),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Contact & identity')
                    ->schema([
                        Forms\Components\DatePicker::make('date_of_birth')->nullable(),
                        Forms\Components\Select::make('gender')
                            ->options([
                                'female' => 'Female',
                                'male' => 'Male',
                                'other' => 'Other',
                            ])
                            ->nullable(),
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
                        Forms\Components\TextInput::make('full_salary')
                            ->label('Full salary (contract wage)')
                            ->numeric()
                            ->minValue(0)
                            ->nullable(),
                        Forms\Components\TextInput::make('social_insurance_salary')
                            ->label('Social insurance salary')
                            ->numeric()
                            ->minValue(0)
                            ->nullable(),
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
                Tables\Columns\TextColumn::make('interview_date')->dateTime()->sortable()->placeholder('—'),
                Tables\Columns\TextColumn::make('cv_path')
                    ->label('CV')
                    ->formatStateUsing(fn (?string $state): string => $state ? 'View' : '—')
                    ->url(fn (Employee $record): ?string => $record->cv_path ? Storage::url($record->cv_path) : null)
                    ->openUrlInNewTab(),
                Tables\Columns\TextColumn::make('basic_salary')->money()->sortable(),
                Tables\Columns\TextColumn::make('start_date')->label('Hiring date')->date()->sortable(),
                Tables\Columns\TextColumn::make('end_date')->date()->sortable()->placeholder('—'),
                Tables\Columns\TextColumn::make('status')->badge()
                    ->formatStateUsing(fn (string $state): string => Employee::statusOptions()[$state] ?? $state)
                    ->color(fn (string $state): string => match ($state) {
                        Employee::STATUS_ACTIVE => 'success',
                        Employee::STATUS_INTERVIEWING => 'warning',
                        Employee::STATUS_FORMER => 'gray',
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
                    ->options(Employee::statusOptions()),
            ])
            ->actions([
                Tables\Actions\Action::make('draftContract')
                    ->label('Draft contract')
                    ->icon('heroicon-o-document-text')
                    ->url(fn (Employee $record): string => EmploymentContractDraft::getUrl().'?employee='.$record->id)
                    ->visible(fn (Employee $record): bool => $record->status === Employee::STATUS_ACTIVE
                        && (Auth::user()?->can('generateContract', $record) ?? false)),
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
