<x-filament-widgets::widget>
    <x-filament::section>
        @php
            $issueLabels = \App\Services\InvoiceSettlementIntegrityService::issueTypeLabels();
            $issueChipClasses = [
                \App\Services\InvoiceSettlementIntegrityService::ISSUE_NO_TRANSACTION_LINK => 'border-danger-200 bg-danger-50 text-danger-800 hover:bg-danger-100 dark:border-danger-800 dark:bg-danger-950/40 dark:text-danger-200 dark:hover:bg-danger-950/60 [&_span:last-child]:bg-danger-100 dark:[&_span:last-child]:bg-danger-900',
                \App\Services\InvoiceSettlementIntegrityService::ISSUE_AMOUNT_MISMATCH => 'border-warning-200 bg-warning-50 text-warning-800 hover:bg-warning-100 dark:border-warning-800 dark:bg-warning-950/40 dark:text-warning-200 dark:hover:bg-warning-950/60 [&_span:last-child]:bg-warning-100 dark:[&_span:last-child]:bg-warning-900',
                \App\Services\InvoiceSettlementIntegrityService::ISSUE_STATUS_UNDERSTATES => 'border-info-200 bg-info-50 text-info-800 hover:bg-info-100 dark:border-info-800 dark:bg-info-950/40 dark:text-info-200 dark:hover:bg-info-950/60 [&_span:last-child]:bg-info-100 dark:[&_span:last-child]:bg-info-900',
                \App\Services\InvoiceSettlementIntegrityService::ISSUE_STATUS_OVERSTATES => 'border-warning-200 bg-warning-50 text-warning-800 hover:bg-warning-100 dark:border-warning-800 dark:bg-warning-950/40 dark:text-warning-200 dark:hover:bg-warning-950/60 [&_span:last-child]:bg-warning-100 dark:[&_span:last-child]:bg-warning-900',
                \App\Services\InvoiceSettlementIntegrityService::ISSUE_LINKED_ZERO_PAYMENT => 'border-danger-200 bg-danger-50 text-danger-800 hover:bg-danger-100 dark:border-danger-800 dark:bg-danger-950/40 dark:text-danger-200 dark:hover:bg-danger-950/60 [&_span:last-child]:bg-danger-100 dark:[&_span:last-child]:bg-danger-900',
                \App\Services\InvoiceSettlementIntegrityService::ISSUE_OVER_ALLOCATED => 'border-danger-200 bg-danger-50 text-danger-800 hover:bg-danger-100 dark:border-danger-800 dark:bg-danger-950/40 dark:text-danger-200 dark:hover:bg-danger-950/60 [&_span:last-child]:bg-danger-100 dark:[&_span:last-child]:bg-danger-900',
            ];
            $chipBase = 'inline-flex items-center gap-2 rounded-full border px-3 py-1.5 text-xs font-medium transition focus:outline-none focus:ring-2 focus:ring-primary-500';
            $activeRing = 'ring-2 ring-primary-500 ring-offset-1 dark:ring-offset-gray-900';
            $hasActiveFilter = filled($this->activeIssueType);
        @endphp

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
                    wire:click="clearIssueFilter"
                >
                    Clear filter
                </button>
            </div>
        @endif

        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <div class="text-sm font-semibold text-gray-950 dark:text-white">Invoice settlement issues</div>
                <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                    Click a count to filter the table below.
                </div>
            </div>
            <button
                type="button"
                class="{{ $chipBase }} border-gray-200 bg-gray-50 text-gray-800 hover:bg-gray-100 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700 {{ blank($this->activeIssueType) ? $activeRing : '' }}"
                wire:click="clearIssueFilter"
            >
                <span>All issues</span>
                <span class="rounded-full bg-gray-200 px-2 py-0.5 text-[11px] font-semibold tabular-nums dark:bg-gray-700">{{ $this->totalIssues }}</span>
            </button>
        </div>

        <div class="mt-4 flex flex-wrap gap-2">
            @foreach ($this->issueCounts as $issueType => $count)
                @if ($count > 0)
                    <button
                        type="button"
                        class="{{ $chipBase }} {{ $issueChipClasses[$issueType] ?? 'border-gray-200 bg-gray-50 text-gray-800' }} {{ $this->activeIssueType === $issueType ? $activeRing : '' }}"
                        wire:click="applyIssueFilter('{{ $issueType }}')"
                    >
                        <span>{{ $issueLabels[$issueType] ?? $issueType }}</span>
                        <span class="rounded-full px-2 py-0.5 text-[11px] font-semibold tabular-nums">{{ $count }}</span>
                    </button>
                @endif
            @endforeach
        </div>

        @if ($this->transactionLinkIssues > 0)
            <div class="mt-6 rounded-xl border border-success-200/70 bg-success-50/40 p-4 dark:border-success-900/50 dark:bg-success-950/20">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <div class="text-sm font-semibold text-gray-950 dark:text-white">Transaction-side linking issues</div>
                        <div class="text-xs text-gray-500 dark:text-gray-400">
                            Income transactions that are unlinked or have invoice amount mismatches.
                        </div>
                    </div>
                    <a
                        href="{{ $this->getTransactionsInWithoutInvoicesUrl() }}"
                        class="inline-flex items-center gap-2 rounded-lg bg-success-100 px-3 py-1.5 text-xs font-semibold text-success-800 hover:bg-success-200 dark:bg-success-900/50 dark:text-success-200 dark:hover:bg-success-900"
                    >
                        <span>View {{ $this->transactionLinkIssues }} transaction(s)</span>
                        <x-heroicon-o-arrow-right class="h-4 w-4" />
                    </a>
                </div>
            </div>
        @endif
    </x-filament::section>

    <x-filament::loading-section wire:loading.delay.longer wire:target="applyIssueFilter,clearIssueFilter" />
</x-filament-widgets::widget>
