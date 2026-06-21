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
            @if ($hasActiveFilter)
                <div class="mb-4 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-primary-200 bg-primary-50 px-4 py-3 dark:border-primary-800 dark:bg-primary-950/40">
                    <div class="flex items-center gap-2 text-sm text-primary-800 dark:text-primary-200">
                        <x-heroicon-o-funnel class="h-5 w-5 shrink-0" />
                        <span>
                            Filtering:
                            <span class="font-semibold">{{ $this->activeFilterLabel }}</span>
                        </span>
                    </div>
                    <button
                        type="button"
                        class="rounded-lg bg-white px-3 py-1.5 text-sm font-medium text-primary-700 shadow-sm ring-1 ring-primary-200 hover:bg-primary-100 dark:bg-primary-900 dark:text-primary-100 dark:ring-primary-700 dark:hover:bg-primary-800"
                        wire:click="clearDocumentationFilter"
                    >
                        Clear filter
                    </button>
                </div>
            @endif

            @include('filament.widgets.partials.documentation-status-overview', [
                'statusOverview' => $this->statusOverview,
                'activeCategory' => $this->activeCategory,
                'activeCompletion' => $this->activeCompletion,
                'activeDocumentationStatus' => $this->activeDocumentationStatus,
                'activeDataIssue' => $this->activeDataIssue,
            ])

            <div class="mt-6 grid grid-cols-1 gap-6 xl:grid-cols-2">
                <div class="rounded-xl border border-success-200/70 bg-success-50/40 p-4 dark:border-success-900/50 dark:bg-success-950/20">
                    <div class="mb-4 flex items-center gap-2">
                        <x-heroicon-o-arrow-down-tray class="h-5 w-5 text-success-600 dark:text-success-400" />
                        <div>
                            <div class="text-sm font-semibold text-gray-950 dark:text-white">Trx In</div>
                            <div class="text-xs text-gray-500 dark:text-gray-400">Receivables</div>
                        </div>
                    </div>

                    <div class="space-y-3">
                        @foreach ($trxInCategories as $categoryKey)
                            @if (isset($breakdown[$categoryKey]) && ($breakdown[$categoryKey]['total'] ?? 0) > 0)
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
                </div>

                <div class="rounded-xl border border-danger-200/70 bg-danger-50/40 p-4 dark:border-danger-900/50 dark:bg-danger-950/20">
                    <div class="mb-4 flex items-center gap-2">
                        <x-heroicon-o-arrow-up-tray class="h-5 w-5 text-danger-600 dark:text-danger-400" />
                        <div>
                            <div class="text-sm font-semibold text-gray-950 dark:text-white">Trx Out & Expenses</div>
                            <div class="text-xs text-gray-500 dark:text-gray-400">Payables</div>
                        </div>
                    </div>

                    <div class="space-y-3">
                        @foreach ($trxOutCategories as $categoryKey)
                            @if (isset($breakdown[$categoryKey]) && ($breakdown[$categoryKey]['total'] ?? 0) > 0)
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
            </div>

            <div class="mt-4 flex items-center gap-2 text-xs text-gray-500 dark:text-gray-400">
                <x-heroicon-o-cursor-arrow-rays class="h-4 w-4" />
                <span>Click a count to filter the table below.</span>
            </div>
        @endif
    </x-filament::section>

    <x-filament::loading-section wire:loading.delay.longer wire:target="applyDocumentationFilter,applyDataIntegrityFilter,applyStatusFilter,clearDocumentationFilter" />
</x-filament-widgets::widget>
