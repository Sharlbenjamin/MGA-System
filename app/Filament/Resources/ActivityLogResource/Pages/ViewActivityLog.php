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
        $reference = $record->subject_reference ?? $record->subject_id;

        return $record->action_label . ' ' . $record->subject_type_label . ' · ' . $this->stringifyValue($reference);
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
                            ->formatStateUsing(fn ($state) => $this->stringifyValue($state))
                            ->placeholder('—'),
                    ])
                    ->columns(2),
                Section::make('Changes')
                    ->schema([
                        TextEntry::make('changes')
                            ->label('')
                            ->formatStateUsing(function ($state) {
                                if (empty($state)) {
                                    return 'No field changes recorded.';
                                }

                                if (! is_array($state)) {
                                    return $this->stringifyValue($state);
                                }

                                $lines = [];
                                foreach ($state as $attr => $pair) {
                                    if (is_array($pair)) {
                                        $old = $pair['old'] ?? null;
                                        $new = $pair['new'] ?? null;
                                    } else {
                                        $old = null;
                                        $new = $pair;
                                    }

                                    $oldText = $this->truncateText($this->stringifyValue($old), 80);
                                    $newText = $this->truncateText($this->stringifyValue($new), 80);
                                    $label = str_replace('_', ' ', ucfirst((string) $attr));

                                    $lines[] = "**{$label}**: {$oldText} → {$newText}";
                                }
                                return implode("\n", $lines);
                            })
                            ->markdown()
                            ->visible(fn ($record) => $record->action === ActivityLog::ACTION_UPDATED && ! empty($record->changes)),
                    ])
                    ->visible(fn ($record) => $record->action === ActivityLog::ACTION_UPDATED && ! empty($record->changes)),
            ]);
    }

    protected function stringifyValue(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '—';
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_scalar($value)) {
            return (string) $value;
        }

        $encoded = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return $encoded !== false ? $encoded : '[unserializable]';
    }

    protected function truncateText(string $text, int $limit): string
    {
        return strlen($text) > $limit ? substr($text, 0, $limit) . '…' : $text;
    }
}
