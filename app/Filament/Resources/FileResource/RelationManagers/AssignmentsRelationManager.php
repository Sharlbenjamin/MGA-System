<?php

namespace App\Filament\Resources\FileResource\RelationManagers;

use App\Models\User;
use App\Services\CaseAssignmentService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Optimized: eager loading (user, assignedBy), User Select searchable+preload(false)+getSearchResultsUsing, pagination 10.
 * Explicit select to limit columns (id, file_id, user_id, assigned_by_id, assigned_at, unassigned_at, is_primary).
 */
class AssignmentsRelationManager extends RelationManager
{
    protected static string $relationship = 'fileAssignments';

    protected static ?string $title = 'Case assignment';

    protected static ?string $recordTitleAttribute = 'id';

    public function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['user', 'assignedBy'])->select(['id', 'file_id', 'user_id', 'assigned_by_id', 'assigned_at', 'unassigned_at', 'is_primary']))
            ->defaultPaginationPageOption(10)
            ->columns([
                Tables\Columns\TextColumn::make('user.name')->label('Assigned to')->sortable(),
                Tables\Columns\TextColumn::make('assignedBy.name')->label('Assigned by')->sortable(),
                Tables\Columns\TextColumn::make('assigned_at')->dateTime()->sortable(),
                Tables\Columns\TextColumn::make('unassigned_at')->dateTime()->sortable()->placeholder('—'),
                Tables\Columns\IconColumn::make('is_primary')->boolean()->label('Primary'),
            ])
            ->defaultSort('assigned_at', 'desc')
            ->headerActions([
                Tables\Actions\Action::make('assign')
                    ->label('Assign to employee')
                    ->icon('heroicon-o-user-plus')
                    ->form([
                        Forms\Components\Select::make('user_id')
                            ->label('Employee / User')
                            ->searchable()
                            ->preload(false)
                            ->getSearchResultsUsing(fn (string $search) => User::query()->where('name', 'like', '%' . $search . '%')->orderBy('name')->limit(50)->pluck('name', 'id'))
                            ->getOptionLabelUsing(fn ($value) => User::find($value)?->name ?? '')
                            ->required(),
                    ])
                    ->action(function (array $data): void {
                        $user = User::find($data['user_id']);
                        if (!$user) {
                            Notification::make()->danger()->title('User not found')->send();
                            return;
                        }
                        app(CaseAssignmentService::class)->assign(
                            $this->ownerRecord,
                            $user,
                            auth()->user()
                        );
                        Notification::make()
                            ->success()
                            ->title('Case assigned')
                            ->body("Assigned to {$user->name}.")
                            ->send();
                    }),
            ])
            ->actions([
                Tables\Actions\Action::make('unassign')
                    ->label('Unassign')
                    ->icon('heroicon-o-user-minus')
                    ->color('warning')
                    ->visible(fn (Model $record) => $record->unassigned_at === null)
                    ->requiresConfirmation()
                    ->action(function (Model $record): void {
                        app(CaseAssignmentService::class)->unassign($record);
                        Notification::make()->success()->title('Unassigned')->send();
                    }),
            ]);
    }
}
