<?php

namespace App\Filament\RelationManagers;

use App\Models\ActivityLog;
use App\Models\File;
use App\Models\Provider;
use App\Models\Client;
use App\Models\Patient;
use App\Models\Invoice;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Shows activity for the owner record and all its relation-manager records (GOPs, Bills, Comments, etc.).
 * Read-only; displayed only in File/Provider/Client/Patient context.
 */
class ActivityLogRelationManager extends RelationManager
{
    protected static string $relationship = 'activityLogs';

    protected static ?string $title = 'Activity log';

    protected static ?string $recordTitleAttribute = 'subject_reference';

    public function table(Table $table): Table
    {
        $owner = $this->ownerRecord;

        return $table
            ->modifyQueryUsing(function (Builder $query) use ($owner) {
                $query->with(['user'])
                    ->select(['id', 'user_id', 'action', 'changes', 'created_at', 'subject_type', 'subject_id', 'subject_reference']);

                $this->includeRelatedActivity($query, $owner);
            })
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Date & time')
                    ->dateTime('d M Y H:i')
                    ->sortable(),
                Tables\Columns\TextColumn::make('subject_type_label')
                    ->label('Subject')
                    ->formatStateUsing(fn (ActivityLog $record) => $record->subject_type_label)
                    ->badge()
                    ->color('info'),
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
                Tables\Columns\TextColumn::make('subject_reference')
                    ->label('Reference')
                    ->limit(50)
                    ->placeholder('—'),
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

    /**
     * Add OR conditions so the table includes activity on related models (GOPs, Bills, etc.).
     */
    protected function includeRelatedActivity(Builder $query, object $owner): void
    {
        if ($owner instanceof File) {
            foreach (['gops', 'bills', 'comments', 'medicalReports', 'prescriptions', 'appointments', 'tasks', 'bankAccounts', 'invoices', 'fileAssignments'] as $relation) {
                $ids = $owner->$relation()->pluck('id');
                if ($ids->isNotEmpty()) {
                    $relationClass = $owner->$relation()->getRelated()::class;
                    $query->orWhere(fn (Builder $q) => $q->where('subject_type', $relationClass)->whereIn('subject_id', $ids));
                }
            }
        }

        if ($owner instanceof Provider) {
            foreach (['branches', 'leads', 'bankAccounts', 'bills'] as $relation) {
                $ids = $owner->$relation()->pluck('id');
                if ($ids->isNotEmpty()) {
                    $relationClass = $owner->$relation()->getRelated()::class;
                    $query->orWhere(fn (Builder $q) => $q->where('subject_type', $relationClass)->whereIn('subject_id', $ids));
                }
            }
        }

        if ($owner instanceof Client) {
            foreach (['leads', 'bankAccounts'] as $relation) {
                $ids = $owner->$relation()->pluck('id');
                if ($ids->isNotEmpty()) {
                    $relationClass = $owner->$relation()->getRelated()::class;
                    $query->orWhere(fn (Builder $q) => $q->where('subject_type', $relationClass)->whereIn('subject_id', $ids));
                }
            }
            $invoiceIds = Invoice::whereIn('patient_id', $owner->patients()->pluck('id'))->pluck('id');
            if ($invoiceIds->isNotEmpty()) {
                $query->orWhere(fn (Builder $q) => $q->where('subject_type', Invoice::class)->whereIn('subject_id', $invoiceIds));
            }
        }

        if ($owner instanceof Patient) {
            foreach (['files', 'invoices'] as $relation) {
                $ids = $owner->$relation()->pluck('id');
                if ($ids->isNotEmpty()) {
                    $relationClass = $owner->$relation()->getRelated()::class;
                    $query->orWhere(fn (Builder $q) => $q->where('subject_type', $relationClass)->whereIn('subject_id', $ids));
                }
            }
        }
    }
}
