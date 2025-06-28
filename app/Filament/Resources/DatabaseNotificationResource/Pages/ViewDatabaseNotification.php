<?php

namespace App\Filament\Resources\DatabaseNotificationResource\Pages;

use App\Filament\Resources\DatabaseNotificationResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\Auth;

class ViewDatabaseNotification extends ViewRecord
{
    protected static string $resource = DatabaseNotificationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('mark_as_read')
                ->label('Mark as Read')
                ->icon('heroicon-o-check')
                ->color('success')
                ->visible(fn () => $this->record->read_at === null)
                ->action(function () {
                    $this->record->markAsRead();
                    $this->notify('success', 'Notification marked as read');
                }),
            
            Actions\Action::make('mark_as_unread')
                ->label('Mark as Unread')
                ->icon('heroicon-o-clock')
                ->color('warning')
                ->visible(fn () => $this->record->read_at !== null)
                ->action(function () {
                    $this->record->markAsUnread();
                    $this->notify('success', 'Notification marked as unread');
                }),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        // Mark notification as read when viewing
        if ($this->record->read_at === null) {
            $this->record->markAsRead();
        }
        
        return $data;
    }
} 