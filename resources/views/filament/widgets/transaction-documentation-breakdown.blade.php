<x-filament-widgets::widget>
    <x-filament::section>
        @php
            $breakdown = $this->breakdown;
            $categoryLabels = \App\Services\TransactionDocumentationStatsService::allCategoryOptions();

            $trxInCategories = ['client_payment', 'account_feed', 'refund'];
            $trxOutCategories = ['provider_single', 'provider_bulk', 'card_provider', 'card_expense', 'expense_payment'];

            $hasActiveFilter = filled($this->activeCategory)
                || filled($this->activeCompletion)
                || filled($this->activeDocumentationStatus)
                || filled($this->activeDataIssue);
        @endphp

        @if ($breakdown === null)
            <p class="text-sm text-gray-500 dark:text-gray-400">
                Documentation stats are unavailable until migrations are applied.
            </p>
        @else
            @include('filament.widgets.partials.documentation-status-overview', [
                'statusOverview' => $this->statusOverview,
                'activeCategory' => $this->activeCategory,
                'activeCompletion' => $this->activeCompletion,
                'activeDocumentationStatus' => $this->activeDocumentationStatus,
                'activeDataIssue' => $this->activeDataIssue,
            ])

            <div class="mt-4 grid grid-cols-1 gap-6 md:grid-cols-2">
                {{-- Trx In column --}}
                <div class="space-y-4">
                    <div class="text-sm font-semibold text-gray-950 dark:text-white">
                        Trx In
                        <span class="text-xs font-normal text-gray-500 dark:text-gray-400">(Receivables)</span>
                    </div>

                    @foreach ($trxInCategories as $categoryKey)
                        @if (isset($breakdown[$categoryKey]))
                            @include('filament.widgets.partials.documentation-category-block', [
                                'category' => $categoryKey,
                                'label' => $categoryLabels[$categoryKey] ?? $categoryKey,
                                'stats' => $breakdown[$categoryKey],
                                'color' => 'success',
                                'activeCategory' => $this->activeCategory,
                                'activeCompletion' => $this->activeCompletion,
                                'activeDocumentationStatus' => $this->activeDocumentationStatus,
                                'activeDataIssue' => $this->activeDataIssue,
                            ])
                        @endif
                    @endforeach
                </div>

                {{-- Trx Out column --}}
                <div class="space-y-4">
                    <div class="text-sm font-semibold text-gray-950 dark:text-white">
                        Trx Out & Expenses
                        <span class="text-xs font-normal text-gray-500 dark:text-gray-400">(Payables)</span>
                    </div>

                    @foreach ($trxOutCategories as $categoryKey)
                        @if (isset($breakdown[$categoryKey]))
                            @include('filament.widgets.partials.documentation-category-block', [
                                'category' => $categoryKey,
                                'label' => $categoryLabels[$categoryKey] ?? $categoryKey,
                                'stats' => $breakdown[$categoryKey],
                                'color' => 'danger',
                                'activeCategory' => $this->activeCategory,
                                'activeCompletion' => $this->activeCompletion,
                                'activeDocumentationStatus' => $this->activeDocumentationStatus,
                                'activeDataIssue' => $this->activeDataIssue,
                            ])
                        @endif
                    @endforeach
                </div>
            </div>

            <div class="mt-4 flex flex-wrap items-center gap-x-4 gap-y-2 text-xs text-gray-500 dark:text-gray-400">
                <span>Click a count to filter the table below.</span>
                @if ($hasActiveFilter)
                    <button
                        type="button"
                        class="font-medium text-primary-600 underline-offset-2 hover:underline dark:text-primary-400"
                        wire:click="clearDocumentationFilter"
                    >
                        Clear filter
                    </button>
                @endif
            </div>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
