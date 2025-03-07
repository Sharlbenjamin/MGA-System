<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Notifications\DatabaseNotification;
use filament\Facades\Filament;

class Notifications extends Page implements Tables\Contracts\HasTable
{
    use Tables\Concerns\InteractsWithTable;

    // Set an icon and navigation label if desired.
    protected static ?string $navigationIcon = 'heroicon-o-bell';
    protected static ?string $navigationLabel = 'Notifications';
    protected static string $view = 'filament.pages.notifications';

    protected function getTableQuery()
    {
        // Retrieve notifications for the authenticated user.
        return auth()->user()->notifications();
    }

    protected function getTableColumns(): array
    {
        return [
            TextColumn::make('data.title')->label('Title'),
            TextColumn::make('data.message')->label('Message'),
            TextColumn::make('created_at')->dateTime()->label('Received'),
            TextColumn::make('read_at')
                ->label('Status')
                ->formatStateUsing(fn ($state): string => $state ? 'Read' : 'Unread'),
        ];
    }

    protected function getTableActions(): array
    {
        return [
            Action::make('markAsRead')
                ->action(function (DatabaseNotification $record) {
                    $record->markAsRead();
                })
                ->icon('heroicon-o-check')
                ->requiresConfirmation(),
        ];
    }
}