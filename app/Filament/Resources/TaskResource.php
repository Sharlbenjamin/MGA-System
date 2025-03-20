<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TaskResource\Pages;
use App\Models\Task;
use Filament\Forms;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\DateTimePicker;
use Filament\Tables;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;

class TaskResource extends Resource
{
    protected static ?string $model = Task::class;
    
    protected static ?string $navigationGroup = null; // Removes it from any group
    protected static ?int $navigationSort = null; // Ensures it's not sorted
    protected static ?string $navigationIcon = null; // Hides from sidebar
    protected static bool $shouldRegisterNavigation = false; // Hides it completely


    public static function form(Forms\Form $form): Forms\Form
    {
        return $form
            ->schema([
                Select::make('user_id')
                    ->relationship('user', 'name')
                    ->required(),

                Select::make('department')
                    ->options([
                        'Operation' => 'Operation',
                        'Financial' => 'Financial',
                        'Network' => 'Client Network',
                        'Network' => 'Providers Network',
                    ])
                    ->required(),

                Select::make('file_id')
                    ->relationship('file', 'reference')
                    ->searchable()
                    ->nullable(),

                Select::make('taskable_type')
                    ->options([
                        'App\Models\Lead' => 'Client Lead',
                        'App\Models\ProviderLead' => 'Provider Lead',
                        'App\Models\Branch' => 'Provider Branch',
                        'App\Models\Patient' => 'Patient',
                        'App\Models\Client' => 'Client',
                    ])->searchable()->nullable(),

                TextInput::make('title')
                    ->required(),

                Textarea::make('description')
                    ->nullable(),

                DateTimePicker::make('due_date')
                    ->nullable(),

                Select::make('is_done')
                    ->options([
                        0 => 'Pending',
                        1 => 'Completed'
                    ])
                    ->default(0),

                Select::make('done_by')
                    ->relationship('doneBy', 'name')
                    ->nullable(),
            ]);
    }

    public static function table(Tables\Table $table): Tables\Table
    {
        return $table
            ->columns([
                TextColumn::make('title')->sortable()->searchable(),
                TextColumn::make('user.name')->label('Assigned To')->sortable(),
                TextColumn::make('department')->sortable(),
                TextColumn::make('due_date')->dateTime()->sortable(),
                TextColumn::make('is_done')->badge(),
                TextColumn::make('doneBy.name')->label('Completed By')->sortable(),
            ])
            ->filters([
                Filter::make('is_done')
                    ->query(fn ($query) => $query->where('is_done', true))
                    ->label('Completed'),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTasks::route('/'),
            'create' => Pages\CreateTask::route('/create'),
            'edit' => Pages\EditTask::route('/{record}/edit'),
        ];
    }
}