<?php

namespace App\Filament\Resources;

use App\Filament\Resources\JobTitleResource\Pages;
use App\Models\JobTitle;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class JobTitleResource extends Resource
{
    protected static ?string $model = JobTitle::class;

    protected static ?string $navigationGroup = 'HR';
    protected static ?int $navigationSort = 1;
    protected static ?string $navigationIcon = 'heroicon-o-briefcase';
    protected static ?string $modelLabel = 'Job Title';
    protected static ?string $pluralModelLabel = 'Job Titles';

    public static function shouldRegisterNavigation(): bool
    {
        return Auth::check() && Auth::user()?->roles?->contains('name', 'admin');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Job title details')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('code')
                            ->required()
                            ->maxLength(50)
                            ->unique(ignoreRecord: true)
                            ->helperText('Unique code e.g. OPS_TM, OPS_TL'),
                        Forms\Components\Select::make('department')
                            ->required()
                            ->options([
                                'Operation' => 'Operation',
                                'Financial' => 'Financial',
                                'Client Network' => 'Client Network',
                                'Provider Network' => 'Provider Network',
                            ]),
                        Forms\Components\TextInput::make('level')
                            ->numeric()
                            ->minValue(1)
                            ->default(1)
                            ->helperText('Higher = more senior (for hierarchy)'),
                        Forms\Components\TextInput::make('bonus_multiplier')
                            ->numeric()
                            ->minValue(0)
                            ->step(0.01)
                            ->default(1)
                            ->helperText('1.0, 1.5, 2.0 for bonus calculation'),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('code')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('department')->sortable(),
                Tables\Columns\TextColumn::make('level')->sortable(),
                Tables\Columns\TextColumn::make('bonus_multiplier')->sortable(),
                Tables\Columns\TextColumn::make('employees_count')->counts('employees')->label('Employees'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('department')
                    ->options([
                        'Operation' => 'Operation',
                        'Financial' => 'Financial',
                        'Client Network' => 'Client Network',
                        'Provider Network' => 'Provider Network',
                    ]),
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
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListJobTitles::route('/'),
            'create' => Pages\CreateJobTitle::route('/create'),
            'edit' => Pages\EditJobTitle::route('/{record}/edit'),
        ];
    }
}
