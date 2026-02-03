<?php

namespace App\Filament\Resources\ActivityLogResource\Pages;

use App\Filament\Resources\ActivityLogResource;
use App\Models\ActivityLog;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\ViewRecord;

class ViewActivityLog extends ViewRecord
{
    protected static string $resource = ActivityLogResource::class;

    public function getTitle(): string
    {
        $record = $this->record;
        return $record->action_label . ' ' . $record->subject_type_label . ' · ' . ($record->subject_reference ?? $record->subject_id);
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Section::make('Activity details')
                    ->schema([
                        TextEntry::make('created_at')
                            ->label('Date & Time')
                            ->dateTime('d M Y H:i:s'),
                        TextEntry::make('user.name')
                            ->label('User')
                            ->placeholder('System'),
                        TextEntry::make('action')
                            ->label('Action')
                            ->formatStateUsing(fn ($state, ActivityLog $record) => $record->action_label),
                        TextEntry::make('subject_type')
                            ->label('Model')
                            ->formatStateUsing(fn ($state, ActivityLog $record) => $record->subject_type_label),
                        TextEntry::make('subject_id')
                            ->label('Subject ID'),
                        TextEntry::make('subject_reference')
                            ->label('Reference')
                            ->placeholder('—'),
                    ])
                    ->columns(2),
                Section::make('Changes')
                    ->schema([
                        TextEntry::make('changes')
                            ->label('')
                            ->formatStateUsing(function (?array $state) {
                                if (empty($state)) {
                                    return 'No field changes recorded.';
                                }
                                $lines = [];
                                foreach ($state as $attr => $pair) {
                                    $old = $pair['old'] ?? null;
                                    $new = $pair['new'] ?? null;
                                    if (is_array($old) || is_array($new)) {
                                        $old = json_encode($old);
                                        $new = json_encode($new);
                                    }
                                    $lines[] = "**" . str_replace('_', ' ', ucfirst($attr)) . "**: " . (strlen((string) $old) > 80 ? substr((string) $old, 0, 80) . '…' : $old) . " → " . (strlen((string) $new) > 80 ? substr((string) $new, 0, 80) . '…' : $new);
                                }
                                return implode("\n", $lines);
                            })
                            ->markdown()
                            ->visible(fn ($record) => $record->action === ActivityLog::ACTION_UPDATED && ! empty($record->changes)),
                    ])
                    ->visible(fn ($record) => $record->action === ActivityLog::ACTION_UPDATED && ! empty($record->changes)),
            ]);
    }
}
