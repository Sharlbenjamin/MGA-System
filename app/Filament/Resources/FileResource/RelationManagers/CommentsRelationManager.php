<?php

namespace App\Filament\Resources\FileResource\RelationManagers;

namespace App\Filament\Resources\FileResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Components\Textarea;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Actions\CreateAction;
use Filament\Tables\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\TextInput;
use Illuminate\Support\Facades\Auth;

class CommentsRelationManager extends RelationManager
{
    protected static string $relationship = 'comments'; // This should match the method name in the File model

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
        return $table->columns([
            TextColumn::make('user.name')->label('User'),
            TextColumn::make('content')->label('Comment')->limit(50),
            TextColumn::make('created_at')->label('Date')->dateTime(),
        ])->headerActions([
            Action::make('create')
                ->label('New Comment')
                ->icon('heroicon-o-plus')
                ->modalHeading('Add a Comment')
                ->modalButton('Create')
                ->form([
                    Hidden::make('file_id')
                        ->default(fn() => $this->ownerRecord->getKey()),

                    Hidden::make('user_id')
                        ->default(fn() => auth()->id()),

                    Textarea::make('content')
                        ->label('Comment')
                        ->required(),
                ])
                ->action(function (array $data): void {
                    $this->ownerRecord->comments()->create($data);
                })
                ->successNotificationTitle('Comment Added Successfully!'),
        ]);
    }

    
}