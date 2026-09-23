<?php

namespace App\Filament\Support;

use App\Services\FileWorkflowGapService;
use Filament\Forms;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Database\Eloquent\Builder;

class FileWorkflowGapFilters
{
    /**
     * @return array<int, Filter|SelectFilter>
     */
    public static function forAssistedChecklist(): array
    {
        $filters = [
            self::checkpointFilter(
                'assisted_checkpoint',
                'Checkpoint',
                FileWorkflowGapService::assistedCheckpointOptions(),
                fn (Builder $query, string $gapKey): Builder => FileWorkflowGapService::scopeWithGap($query, $gapKey),
            ),
        ];

        return array_merge($filters, self::sharedFileFilters());
    }

    /**
     * @return array<int, Filter|SelectFilter>
     */
    public static function forInvoiceChecklist(): array
    {
        $filters = [
            self::checkpointFilter(
                'invoice_checkpoint',
                'Checkpoint',
                FileWorkflowGapService::invoiceCheckpointOptions(),
                fn (Builder $query, string $gapKey): Builder => FileWorkflowGapService::scopeWithInvoiceGap($query, $gapKey),
            ),
            SelectFilter::make('status')
                ->label('Case Status')
                ->options(FileWorkflowGapService::caseStatusOptions())
                ->multiple()
                ->searchable()
                ->default(FileWorkflowGapService::invoiceChecklistStatuses()),
        ];

        return array_merge($filters, self::sharedFileFilters(true));
    }

    /**
     * @return array<int, Filter|SelectFilter>
     */
    public static function forClientOfferChecklist(): array
    {
        $filters = [
            self::checkpointFilter(
                'offer_checkpoint',
                'Checkpoint',
                FileWorkflowGapService::clientOfferCheckpointOptions(),
                fn (Builder $query, string $gapKey): Builder => FileWorkflowGapService::scopeMissingClientOffer($query),
            ),
        ];

        return array_merge($filters, self::sharedFileFilters(true));
    }

    /**
     * @param  callable(Builder, string): Builder  $scopeApplier
     */
    protected static function checkpointFilter(
        string $name,
        string $label,
        array $options,
        callable $scopeApplier
    ): Filter {
        return Filter::make($name)
            ->label($label)
            ->form([
                Forms\Components\Select::make('gap')
                    ->label($label)
                    ->options($options)
                    ->default(FileWorkflowGapService::GAP_ANY)
                    ->native(false),
            ])
            ->query(function (Builder $query, array $data) use ($scopeApplier, $options): Builder {
                $gap = $data['gap'] ?? array_key_first($options);

                if (! filled($gap) || $gap === FileWorkflowGapService::GAP_ANY || $gap === FileWorkflowGapService::INVOICE_GAP_ANY) {
                    return $query;
                }

                return $scopeApplier($query, (string) $gap);
            })
            ->indicateUsing(function (array $data) use ($label, $options): array {
                $gap = $data['gap'] ?? null;

                if (! filled($gap) || $gap === FileWorkflowGapService::GAP_ANY || $gap === FileWorkflowGapService::INVOICE_GAP_ANY) {
                    return [];
                }

                return [$label => $options[$gap] ?? $gap];
            });
    }

    /**
     * @return array<int, SelectFilter>
     */
    protected static function sharedFileFilters(bool $includeClient = false): array
    {
        $filters = [];

        if ($includeClient) {
            $filters[] = SelectFilter::make('client')
                ->relationship(
                    'patient.client',
                    'company_name',
                    fn ($query) => $query->where('status', 'Active')
                )
                ->label('Client')
                ->searchable()
                ->preload()
                ->multiple();
        }

        $filters[] = SelectFilter::make('country_id')
            ->relationship('country', 'name')
            ->label('Country');

        $filters[] = SelectFilter::make('city_id')
            ->relationship('city', 'name')
            ->label('City');

        $filters[] = SelectFilter::make('service_type_id')
            ->relationship('serviceType', 'name')
            ->label('Service Type');

        return $filters;
    }
}
