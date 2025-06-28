<?php

namespace App\Filament\Resources;

use App\Filament\Resources\NotificationResource\Pages;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Notifications\DatabaseNotification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\BulkAction;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class NotificationResource extends Resource
{
    protected static ?string $model = DatabaseNotification::class;

    protected static ?string $navigationIcon = 'heroicon-o-bell';

    protected static ?string $navigationGroup = 'System';

    protected static ?int $navigationSort = 100;

    protected static ?string $navigationLabel = 'Notifications';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('type')
                    ->label('Type')
                    ->disabled(),
                Forms\Components\TextInput::make('notifiable_type')
                    ->label('Notifiable Type')
                    ->disabled(),
                Forms\Components\TextInput::make('notifiable_id')
                    ->label('Notifiable ID')
                    ->disabled(),
                Forms\Components\Textarea::make('data')
                    ->label('Data')
                    ->disabled()
                    ->columnSpanFull(),
                Forms\Components\DateTimePicker::make('read_at')
                    ->label('Read At')
                    ->disabled(),
                Forms\Components\DateTimePicker::make('created_at')
                    ->label('Created At')
                    ->disabled(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('type')
                    ->label('Type')
                    ->searchable()
                    ->sortable()
                    ->formatStateUsing(function ($state) {
                        return class_basename($state);
                    }),
                TextColumn::make('data')
                    ->label('Message')
                    ->searchable()
                    ->formatStateUsing(function ($state) {
                        $data = is_string($state) ? json_decode($state, true) : $state;
                        return $data['message'] ?? $data['title'] ?? 'No message';
                    })
                    ->limit(50),
                TextColumn::make('notifiable.name')
                    ->label('User')
                    ->searchable()
                    ->sortable(),
                IconColumn::make('read_at')
                    ->label('Status')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-clock')
                    ->trueColor('success')
                    ->falseColor('warning'),
                TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('read_at')
                    ->label('Status')
                    ->options([
                        'unread' => 'Unread',
                        'read' => 'Read',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        if ($data['value'] === 'unread') {
                            return $query->whereNull('read_at');
                        }
                        if ($data['value'] === 'read') {
                            return $query->whereNotNull('read_at');
                        }
                        return $query;
                    }),
            ])
            ->actions([
                Action::make('mark_as_read')
                    ->label('Mark as Read')
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->visible(fn (DatabaseNotification $record): bool => $record->read_at === null)
                    ->action(function (DatabaseNotification $record): void {
                        $record->markAsRead();
                    }),
                Action::make('mark_as_unread')
                    ->label('Mark as Unread')
                    ->icon('heroicon-o-clock')
                    ->color('warning')
                    ->visible(fn (DatabaseNotification $record): bool => $record->read_at !== null)
                    ->action(function (DatabaseNotification $record): void {
                        $record->markAsUnread();
                    }),
                Action::make('view')
                    ->label('View Details')
                    ->icon('heroicon-o-eye')
                    ->url(fn (DatabaseNotification $record): string => route('filament.admin.resources.notifications.edit', $record))
                    ->openUrlInNewTab(),
            ])
            ->bulkActions([
                BulkAction::make('mark_as_read')
                    ->label('Mark as Read')
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->action(function (Collection $records): void {
                        $records->each(function ($record) {
                            $record->markAsRead();
                        });
                    }),
                BulkAction::make('mark_as_unread')
                    ->label('Mark as Unread')
                    ->icon('heroicon-o-clock')
                    ->color('warning')
                    ->action(function (Collection $records): void {
                        $records->each(function ($record) {
                            $record->markAsUnread();
                        });
                    }),
                BulkAction::make('delete')
                    ->label('Delete')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(function (Collection $records): void {
                        $records->each(function ($record) {
                            $record->delete();
                        });
                    }),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListNotifications::route('/'),
            'edit' => Pages\EditNotification::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->latest();
    }
} 