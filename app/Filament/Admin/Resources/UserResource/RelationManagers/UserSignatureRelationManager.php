<?php

namespace App\Filament\Resources\UserResource\RelationManagers;

use Filament\Forms;
use Filament\Tables;
use Filament\Resources\RelationManagers\RelationManager;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class UserSignatureRelationManager extends RelationManager
{
    protected static string $relationship = 'signature'; // Ensure your User model has this relationship

    public function form(Forms\Form $form): Forms\Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->label('Name (in signature)')
                    ->required(),

                Forms\Components\TextInput::make('job_title')
                    ->label('Job Title (in signature)')
                    ->required(),

                Forms\Components\Select::make('department')
                    ->label('Department (in signature)')
                    ->options([
                        'Operation' => 'Operation',
                        'Financial' => 'Financial',
                        'Provider Network' => 'Provider Network',
                        'Client Network' => 'Client Network',
                    ])
                    ->required(),

                Forms\Components\TextInput::make('work_phone')
                    ->label('Work Phone (in signature)')
                    ->required(),
            ]);
    }

    public function table(Tables\Table $table): Tables\Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name'),
                Tables\Columns\TextColumn::make('job_title'),
                Tables\Columns\TextColumn::make('department'),
                Tables\Columns\TextColumn::make('work_phone'),
            ])
            ->filters([])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ]);
    }
}