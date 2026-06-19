<?php

namespace App\Filament\Support;

use App\Filament\Resources\TransactionResource;
use App\Models\Transaction;
use App\Services\TransactionDocumentationService;
use App\Services\TransactionDocumentationStatsService;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Forms\Get;
use Filament\Notifications\Notification;
use Filament\Tables\Actions\Action as TableAction;
use Illuminate\Support\Facades\Auth;

class TransactionReviewForm
{
    /**
     * @return array<int, Forms\Components\Component>
     */
    public static function schema(Transaction $record): array
    {
        $documentationService = app(TransactionDocumentationService::class);
        $record->loadMissing(['bills', 'bankAccount']);

        return [
            Forms\Components\Placeholder::make('transaction_summary')
                ->label('Transaction')
                ->content(function () use ($record, $documentationService): string {
                    $category = TransactionDocumentationStatsService::categoryLabel(
                        TransactionDocumentationStatsService::resolveCategoryKey($record)
                    );
                    $docStatus = $documentationService->formatDocumentationStatusLabel(
                        $record->documentation_status === 'revised'
                            ? 'revised'
                            : $documentationService->resolveDocumentationStatus($record)
                    );

                    return implode("\n", [
                        'Date: '.$record->date->format('d/m/Y'),
                        'Name: '.$record->name,
                        'Amount: €'.number_format((float) $record->amount, 2),
                        'Type: '.$record->type,
                        'Category: '.$category,
                        'Documentation: '.$docStatus,
                    ]);
                })
                ->columnSpanFull(),

            Forms\Components\Placeholder::make('documentation_checklist')
                ->label('Documentation checklist')
                ->content(function () use ($record, $documentationService): string {
                    $tasks = $documentationService->getMissingTasks($record);
                    $done = collect($tasks)->where('status', 'done')->count();
                    $total = count($tasks);

                    $lines = collect($tasks)->map(function (array $task): string {
                        $icon = $task['status'] === 'done' ? '✓' : '⚠';

                        return "{$icon} {$task['label']}";
                    });

                    return "Progress: {$done} of {$total} complete\n\n".$lines->implode("\n");
                })
                ->columnSpanFull(),

            Forms\Components\Select::make('documentation_category')
                ->label('Category')
                ->options(fn () => TransactionDocumentationStatsService::categoryOptionsFor(
                    $record->type,
                    $record->related_type,
                ))
                ->required()
                ->live()
                ->helperText('Update the category if this transaction is misclassified before marking as revised.'),

            Forms\Components\Select::make('bills')
                ->label('Linked bills')
                ->multiple()
                ->searchable()
                ->options(fn () => in_array($record->related_type, ['Provider', 'Branch'], true) && $record->related_id
                    ? TransactionResource::availableBillOptions(
                        $record->related_type,
                        (int) $record->related_id,
                        $record->id,
                    )
                    : [])
                ->visible(fn (Get $get): bool => in_array($get('documentation_category'), ['provider_single', 'provider_bulk'], true))
                ->helperText(fn (Get $get): string => match ($get('documentation_category')) {
                    'provider_single' => 'Provider Single requires exactly 1 bill.',
                    'provider_bulk' => 'Provider Bulk requires 2 or more bills.',
                    default => '',
                }),

            Forms\Components\Toggle::make('mark_as_revised')
                ->label('Mark as revised')
                ->default(true)
                ->helperText('Sets documentation status to Revised so you can track review progress.'),
        ];
    }

    public static function apply(Transaction $record, array $data): void
    {
        $category = $data['documentation_category'] ?? null;
        $billIds = $data['bills'] ?? [];
        $markAsRevised = $data['mark_as_revised'] ?? true;

        if ($category) {
            app(TransactionDocumentationStatsService::class)->applyCategory(
                $record->fresh(),
                $category,
                is_array($billIds) ? $billIds : [],
            );
        }

        $record->refresh();

        if ($markAsRevised) {
            $record->update([
                'documentation_status' => 'revised',
                'updated_by' => Auth::id(),
            ]);
        } elseif ($record->documentation_status !== 'revised') {
            app(TransactionDocumentationService::class)->syncAndRecalculate($record->fresh());
        }

        Notification::make()
            ->success()
            ->title($markAsRevised ? 'Transaction marked as revised' : 'Transaction review saved')
            ->send();
    }

    public static function makeTableAction(): TableAction
    {
        return TableAction::make('reviewTransaction')
            ->label('Review & mark revised')
            ->icon('heroicon-o-check-badge')
            ->color('info')
            ->visible(fn (Transaction $record): bool => $record->documentation_status !== 'revised')
            ->modalHeading('Review transaction')
            ->modalDescription('Confirm category and mark this transaction as revised.')
            ->modalSubmitActionLabel('Save & mark revised')
            ->fillForm(fn (Transaction $record): array => [
                'documentation_category' => TransactionDocumentationStatsService::resolveCategoryKey($record),
                'bills' => $record->bills()->pluck('bills.id')->all(),
                'mark_as_revised' => true,
            ])
            ->form(fn (Transaction $record): array => self::schema($record))
            ->action(function (Transaction $record, array $data): void {
                self::apply($record, $data);
            });
    }

    public static function makeHeaderAction(): Action
    {
        return Action::make('reviewTransaction')
            ->label('Review & mark revised')
            ->icon('heroicon-o-check-badge')
            ->color('info')
            ->visible(fn (Transaction $record): bool => $record->documentation_status !== 'revised')
            ->modalHeading('Review transaction')
            ->modalDescription('Confirm category and mark this transaction as revised.')
            ->modalSubmitActionLabel('Save & mark revised')
            ->fillForm(fn (Transaction $record): array => [
                'documentation_category' => TransactionDocumentationStatsService::resolveCategoryKey($record),
                'bills' => $record->bills()->pluck('bills.id')->all(),
                'mark_as_revised' => true,
            ])
            ->form(fn (Transaction $record): array => self::schema($record))
            ->action(function (array $data, \Livewire\Component $livewire): void {
                if (! method_exists($livewire, 'getRecord')) {
                    return;
                }

                $record = $livewire->getRecord();

                if (! $record instanceof Transaction) {
                    return;
                }

                self::apply($record, $data);

                if (method_exists($livewire, 'refreshFormData')) {
                    $livewire->refreshFormData([
                        'documentation_status',
                        'mark_as_revised',
                        'documentation_category',
                        'type',
                        'bills',
                    ]);
                }
            });
    }
}
