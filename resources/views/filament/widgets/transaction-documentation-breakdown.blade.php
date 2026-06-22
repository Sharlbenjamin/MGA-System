<x-filament-widgets::widget>
    <x-filament::section>
        @php
            $summary = $this->summary;
            $sections = [
                'all' => ['label' => 'All transactions', 'icon' => 'heroicon-o-banknotes', 'color' => 'primary'],
                'income' => ['label' => 'Trx In', 'icon' => 'heroicon-o-arrow-down-tray', 'color' => 'success'],
                'outflow' => ['label' => 'Trx Out & Expenses', 'icon' => 'heroicon-o-arrow-up-tray', 'color' => 'danger'],
            ];
            $stats = [
                'total' => 'Total',
                'done' => 'Done',
                'unlinked' => 'Not linked',
                'incomplete' => 'Incomplete',
            ];
            $hasActiveFilter = filled($this->activeTypeScope) || filled($this->activeStatus);
        @endphp

        @if ($summary === null)
            <p class="text-sm text-gray-500 dark:text-gray-400">
                Transaction summary is unavailable until migrations are applied.
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
                        wire:click="clearStatFilter"
                    >
                        Clear filter
                    </button>
                </div>
            @endif

            <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
                @foreach ($sections as $scopeKey => $section)
                    @php
                        $counts = $summary[$scopeKey] ?? ['total' => 0, 'done' => 0, 'unlinked' => 0, 'incomplete' => 0];
                        $borderClass = match ($section['color']) {
                            'success' => 'border-success-200/70 dark:border-success-900/50',
                            'danger' => 'border-danger-200/70 dark:border-danger-900/50',
                            default => 'border-gray-200 dark:border-gray-700',
                        };
                    @endphp

                    <div class="rounded-xl border {{ $borderClass }} p-4">
                        <div class="mb-4 flex items-center gap-2">
                            <x-dynamic-component :component="$section['icon']" class="h-5 w-5 text-gray-500 dark:text-gray-400" />
                            <div class="text-sm font-semibold text-gray-950 dark:text-white">{{ $section['label'] }}</div>
                        </div>

                        <div class="grid grid-cols-2 gap-3">
                            @foreach ($stats as $statKey => $statLabel)
                                @php
                                    $count = (int) ($counts[$statKey] ?? 0);
                                    $filterStatus = $statKey === 'total' ? null : $statKey;
                                    $isActive = $hasActiveFilter
                                        && (
                                            ($scopeKey === 'all' && blank($this->activeTypeScope))
                                            || $this->activeTypeScope === $scopeKey
                                        )
                                        && ($this->activeStatus ?? null) === $filterStatus;
                                @endphp

                                <button
                                    type="button"
                                    class="rounded-lg border px-3 py-2 text-left transition hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-primary-500 dark:hover:bg-gray-800 {{ $isActive ? 'border-primary-500 bg-primary-50 ring-2 ring-primary-500 ring-offset-1 dark:border-primary-600 dark:bg-primary-950/40 dark:ring-offset-gray-900' : 'border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900' }}"
                                    wire:click="applyStatFilter('{{ $scopeKey }}', {{ $filterStatus === null ? 'null' : "'{$filterStatus}'" }})"
                                >
                                    <div class="text-xs text-gray-500 dark:text-gray-400">{{ $statLabel }}</div>
                                    <div class="mt-1 text-xl font-bold tabular-nums text-gray-950 dark:text-white">{{ $count }}</div>
                                </button>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="mt-4 flex items-center gap-2 text-xs text-gray-500 dark:text-gray-400">
                <x-heroicon-o-cursor-arrow-rays class="h-4 w-4" />
                <span>Click a count to filter the table below.</span>
            </div>
        @endif
    </x-filament::section>

    <x-filament::loading-section wire:loading.delay.longer wire:target="applyStatFilter,clearStatFilter" />
</x-filament-widgets::widget>
