<x-filament-widgets::widget>
    <x-filament::section>
        @php
            $summary = $this->summary;
            $rows = [
                'all' => ['label' => 'All transactions', 'icon' => 'heroicon-o-banknotes'],
                'income' => ['label' => 'Trx In', 'icon' => 'heroicon-o-arrow-down-tray'],
                'outflow' => ['label' => 'Trx Out & Expenses', 'icon' => 'heroicon-o-arrow-up-tray'],
            ];
            $columns = [
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

            <div class="overflow-x-auto rounded-xl border border-gray-200 dark:border-gray-700">
                <table class="w-full min-w-[32rem] divide-y divide-gray-200 text-sm dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-800/60">
                        <tr>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                Category
                            </th>
                            @foreach ($columns as $label)
                                <th scope="col" class="px-4 py-3 text-center text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                    {{ $label }}
                                </th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 bg-white dark:divide-gray-700 dark:bg-gray-900">
                        @foreach ($rows as $scopeKey => $row)
                            @php
                                $counts = $summary[$scopeKey] ?? ['total' => 0, 'done' => 0, 'unlinked' => 0, 'incomplete' => 0];
                            @endphp
                            <tr class="hover:bg-gray-50/80 dark:hover:bg-gray-800/40">
                                <td class="whitespace-nowrap px-4 py-3 font-medium text-gray-950 dark:text-white">
                                    <div class="flex items-center gap-2">
                                        <x-dynamic-component :component="$row['icon']" class="h-4 w-4 shrink-0 text-gray-400" />
                                        {{ $row['label'] }}
                                    </div>
                                </td>
                                @foreach ($columns as $statKey => $statLabel)
                                    @php
                                        $count = (int) ($counts[$statKey] ?? 0);
                                        $filterStatus = $statKey === 'total' ? null : $statKey;
                                        $isActive = $hasActiveFilter
                                            && (
                                                ($scopeKey === 'all' && blank($this->activeTypeScope))
                                                || $this->activeTypeScope === $scopeKey
                                            )
                                            && ($this->activeStatus ?? null) === $filterStatus;
                                        $cellColor = match ($statKey) {
                                            'done' => 'text-success-700 dark:text-success-400',
                                            'unlinked' => 'text-warning-700 dark:text-warning-400',
                                            'incomplete' => 'text-danger-700 dark:text-danger-400',
                                            default => 'text-gray-950 dark:text-white',
                                        };
                                    @endphp
                                    <td class="px-4 py-3 text-center">
                                        <button
                                            type="button"
                                            title="Filter: {{ $row['label'] }} · {{ $statLabel }}"
                                            class="inline-flex min-w-[2.5rem] items-center justify-center rounded-lg px-3 py-1.5 text-base font-bold tabular-nums transition hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-primary-500 dark:hover:bg-gray-800 {{ $cellColor }} {{ $isActive ? 'bg-primary-50 ring-2 ring-primary-500 ring-offset-1 dark:bg-primary-950/40 dark:ring-offset-gray-900' : '' }}"
                                            wire:click="applyStatFilter('{{ $scopeKey }}', {{ $filterStatus === null ? 'null' : "'{$filterStatus}'" }})"
                                        >
                                            {{ $count }}
                                        </button>
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="mt-3 flex items-center gap-2 text-xs text-gray-500 dark:text-gray-400">
                <x-heroicon-o-cursor-arrow-rays class="h-4 w-4" />
                <span>Click a number to filter the table below.</span>
            </div>
        @endif
    </x-filament::section>

    <x-filament::loading-section wire:loading.delay.longer wire:target="applyStatFilter,clearStatFilter" />
</x-filament-widgets::widget>
