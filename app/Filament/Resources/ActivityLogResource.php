<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ActivityLogResource\Pages;
use App\Models\ActivityLog;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ActivityLogResource extends Resource
{
    protected static ?string $model = ActivityLog::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-check';

    protected static ?string $navigationGroup = 'System';

    protected static ?string $navigationLabel = 'Activity Log';

    protected static ?string $modelLabel = 'Activity Log';

    protected static ?int $navigationSort = 90;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function form(\Filament\Forms\Form $form): \Filament\Forms\Form
    {
        return $form->schema([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Date & Time')
                    ->dateTime('d M Y H:i')
                    ->sortable(),
                Tables\Columns\TextColumn::make('user.name')
                    ->label('User')
                    ->searchable()
                    ->sortable()
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
                Tables\Columns\TextColumn::make('subject_type_label')
                    ->label('Model')
                    ->badge()
                    ->color('info'),
                Tables\Columns\TextColumn::make('subject_reference')
                    ->label('Reference')
                    ->searchable()
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('subject_id')
                    ->label('ID')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('user_id')
                    ->label('User')
                    ->relationship('user', 'name')
                    ->searchable()
                    ->preload(),
                Tables\Filters\SelectFilter::make('action')
                    ->label('Action')
                    ->options([
                        ActivityLog::ACTION_CREATED => 'Created',
                        ActivityLog::ACTION_UPDATED => 'Updated',
                        ActivityLog::ACTION_DELETED => 'Deleted',
                    ]),
                Tables\Filters\SelectFilter::make('subject_type')
                    ->label('Model')
                    ->options([
                        \App\Models\File::class => 'Case',
                        \App\Models\Provider::class => 'Provider',
                        \App\Models\Client::class => 'Client',
                        \App\Models\Patient::class => 'Patient',
                    ]),
                Tables\Filters\Filter::make('created_at')
                    ->form([
                        \Filament\Forms\Components\DatePicker::make('created_from')
                            ->label('From date'),
                        \Filament\Forms\Components\DatePicker::make('created_until')
                            ->label('Until date'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['created_from'], fn (Builder $q, $v) => $q->whereDate('created_at', '>=', $v))
                            ->when($data['created_until'], fn (Builder $q, $v) => $q->whereDate('created_at', '<=', $v));
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
            ])
            ->defaultSort('created_at', 'desc')
            ->poll(null);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListActivityLogs::route('/'),
            'view' => Pages\ViewActivityLog::route('/{record}'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->latest();
    }
}
