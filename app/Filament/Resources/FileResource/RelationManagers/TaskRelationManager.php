<?php

namespace App\Filament\Resources\FileResource\RelationManagers;

use App\Models\Task;
use Filament\Forms;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\DateTimePicker;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ToggleColumn;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/** Optimized: eager loading (user, doneBy), explicit select, user Select searchable+preload(false), pagination 10. */
class TaskRelationManager extends RelationManager
{
    protected static string $relationship = 'tasks';

    protected static ?string $title = 'Tasks';

    /** Tasks must not be deleted; they are only marked done. */
    protected static bool $canDelete = false;

    public function form(Forms\Form $form): Forms\Form
    {
        return $form
            ->schema([
                Select::make('user_id')
                    ->relationship('user', 'name')
                    ->searchable()
                    ->preload(false)
                    ->required(),

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
            ]);
    }

    public function table(Tables\Table $table): Tables\Table
    {
        return $table
            ->query(
                Task::with(['user', 'doneBy'])
                    ->select(['id', 'file_id', 'user_id', 'title', 'description', 'due_date', 'is_done', 'done_by', 'department'])
                    ->where('department', 'Operation')
                    ->where('file_id', $this->ownerRecord->id)
            )
            ->defaultPaginationPageOption(10)
            ->columns([
                TextColumn::make('title')->sortable()->searchable(),
                TextColumn::make('description')->sortable()->searchable(),
                TextColumn::make('user.name')->label('Assigned To')->sortable(),
                TextColumn::make('due_date')->dateTime()->sortable(),
                TextColumn::make('doneBy.name')->label('Done By')->sortable(),
                ToggleColumn::make('is_done')->label('Completed'),
            ])
            ->filters([
                Filter::make('is_done')
                    ->query(fn ($query) => $query->where('is_done', true))
                    ->label('Completed'),
            ]);
    }
}
