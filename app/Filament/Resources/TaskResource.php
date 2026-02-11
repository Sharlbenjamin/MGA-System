<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TaskResource\Pages;
use App\Models\Client;
use App\Models\Lead;
use App\Models\Patient;
use App\Models\ProviderBranch;
use App\Models\ProviderLead;
use App\Models\Task;
use Filament\Forms;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Get;
use Filament\Tables;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;

class TaskResource extends Resource
{
    protected static ?string $model = Task::class;

    protected static ?string $navigationGroup = 'System';
    protected static ?int $navigationSort = 90;
    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document';
    protected static bool $shouldRegisterNavigation = true;

    public static function form(Forms\Form $form): Forms\Form
    {
        return $form
            ->schema([
                Select::make('user_id')
                    ->relationship('user', 'name')
                    ->label('Assign to user')
                    ->searchable()
                    ->required(),
                Select::make('department')
                    ->options([
                        'Operation' => 'Operation',
                        'Financial' => 'Financial',
                        'Network' => 'Client Network',
                        'Network Providers' => 'Providers Network',
                    ])
                    ->required(),
                Select::make('file_id')
                    ->relationship('file', 'mga_reference')
                    ->label('Linked case (file)')
                    ->searchable()
                    ->nullable(),

                Select::make('taskable_type')
                    ->label('Linked entity type')
                    ->options([
                        Lead::class => 'Client Lead',
                        ProviderLead::class => 'Provider Lead',
                        ProviderBranch::class => 'Provider Branch',
                        Patient::class => 'Patient',
                        Client::class => 'Client',
                    ])
                    ->live()
                    ->searchable()
                    ->nullable(),

                Select::make('taskable_id')
                    ->label('Linked record')
                    ->options(function (Get $get) {
                        return match ($get('taskable_type')) {
                            Lead::class => Lead::query()->orderBy('name')->pluck('name', 'id'),
                            ProviderLead::class => ProviderLead::query()->orderBy('name')->pluck('name', 'id'),
                            ProviderBranch::class => ProviderBranch::query()->orderBy('branch_name')->pluck('branch_name', 'id'),
                            Patient::class => Patient::query()->orderBy('name')->pluck('name', 'id'),
                            Client::class => Client::query()->orderBy('company_name')->pluck('company_name', 'id'),
                            default => [],
                        };
                    })
                    ->searchable()
                    ->preload(false)
                    ->visible(fn (Get $get): bool => filled($get('taskable_type')))
                    ->nullable(),

                TextInput::make('title')
                    ->required(),
                Textarea::make('description')
                    ->label('Comment')
                    ->nullable(),
                DateTimePicker::make('due_date')->nullable(),

                Select::make('is_done')
                    ->options([
                        0 => 'Pending',
                        1 => 'Completed',
                    ])
                    ->default(0),

                Select::make('done_by')
                    ->relationship('doneBy', 'name')
                    ->label('Completed by')
                    ->nullable(),
            ]);
    }

    public static function table(Tables\Table $table): Tables\Table
    {
        return $table
            ->columns([
                TextColumn::make('title')->sortable()->searchable(),
                TextColumn::make('file.mga_reference')->label('File')->sortable()->searchable(),
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