<?php

namespace App\Filament\Resources\FileResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Components\Textarea;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\EditAction;
use Filament\Forms\Components\Hidden;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\Builder;

/**
 * Optimized: eager loading (user), explicit select (id, file_id, user_id, content, created_at), pagination 10.
 */
class CommentsRelationManager extends RelationManager
{
    protected static string $relationship = 'comments';

    public function form(\Filament\Forms\Form $form): \Filament\Forms\Form
    {
        return $form->schema([
            Textarea::make('content')
                ->label('Comment')
                ->required(),
        ]);
    }

    public function table(\Filament\Tables\Table $table): \Filament\Tables\Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['user'])->select(['id', 'file_id', 'user_id', 'content', 'created_at']))
            ->defaultPaginationPageOption(10)
            ->columns([
                TextColumn::make('user.name')->label('User'),
                TextColumn::make('content')
                    ->label('Comment')
                    ->wrap()
                    ->extraAttributes(['class' => 'max-w-[12rem]']),
                TextColumn::make('created_at')->label('Date')->dateTime(),
            ])
            ->headerActions([
                Action::make('create')
                    ->label('New Comment')
                    ->icon('heroicon-o-plus')
                    ->modalHeading('Add a Comment')
                    ->modalButton('Create')
                    ->form([
                        Hidden::make('file_id')
                            ->default(fn() => $this->ownerRecord->getKey()),

                        Hidden::make('user_id')
                            ->default(fn() => Auth::id()),

                        Textarea::make('content')
                            ->label('Comment')
                            ->required(),
                    ])
                    ->action(function (array $data): void {
                        $this->ownerRecord->comments()->create($data);
                    })
                    ->successNotificationTitle('Comment Added Successfully!'),
            ])
            ->actions([
                EditAction::make()
                    ->visible(fn ($record) => $record->user_id === Auth::id())
                    ->successNotificationTitle('Comment Updated Successfully!'),
            ]);
    }
}