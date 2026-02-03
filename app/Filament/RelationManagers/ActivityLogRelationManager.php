<?php

namespace App\Filament\RelationManagers;

use App\Models\ActivityLog;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class ActivityLogRelationManager extends RelationManager
{
    protected static string $relationship = 'activityLogs';

    protected static ?string $title = 'Activity log';

    protected static ?string $recordTitleAttribute = 'subject_reference';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Date & time')
                    ->dateTime('d M Y H:i')
                    ->sortable(),
                Tables\Columns\TextColumn::make('user.name')
                    ->label('User')
                    ->placeholder('System'),
                Tables\Columns\TextColumn::make('action')
                    ->label('Action')
                    ->badge()
                    ->formatStateUsing(fn (ActivityLog $record) => $record->action_label)
                    ->color(fn (string $state): string => match ($state) {
                        ActivityLog::ACTION_CREATED => 'success',
                        ActivityLog::ACTION_UPDATED => 'warning',
                        ActivityLog::ACTION_DELETED => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('changes_summary')
                    ->label('Changes')
                    ->getStateUsing(function (ActivityLog $record) {
                        if ($record->action !== ActivityLog::ACTION_UPDATED || empty($record->changes)) {
                            return '—';
                        }
                        $keys = array_keys($record->changes);
                        if (count($keys) <= 3) {
                            return implode(', ', array_map(fn ($k) => str_replace('_', ' ', ucfirst($k)), $keys));
                        }
                        return count($keys) . ' fields updated';
                    })
                    ->placeholder('—'),
            ])
            ->defaultSort('created_at', 'desc')
            ->paginated([10, 25, 50])
            ->headerActions([])
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->url(fn (ActivityLog $record) => \App\Filament\Resources\ActivityLogResource::getUrl('view', ['record' => $record])),
            ])
            ->bulkActions([]);
    }
}
