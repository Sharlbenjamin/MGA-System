@php
    $buttonClass = 'inline-flex items-center gap-2 rounded-lg border px-3 py-2 text-sm transition focus:outline-none focus:ring-2 focus:ring-primary-500';
    $activeRing = 'ring-2 ring-primary-500 ring-offset-1 dark:ring-offset-gray-900';
    $isStatusOnlyFilter = blank($activeCategory ?? null)
        && blank($activeCompletion ?? null)
        && blank($activeDataIssue ?? null);

    $total = (int) ($statusOverview['total'] ?? 0);
    $completeCount = collect($statusOverview['statuses'] ?? [])->firstWhere('key', 'complete')['count'] ?? 0;
    $completePercent = $total > 0 ? round(($completeCount / $total) * 100) : 0;
@endphp

<div class="space-y-4 border-b border-gray-200 pb-5 dark:border-gray-700">
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <div class="text-sm font-semibold text-gray-950 dark:text-white">Documentation status</div>
            <div class="text-xs text-gray-500 dark:text-gray-400">All transactions on this bank account</div>
        </div>
        <div class="text-right">
            <div class="text-2xl font-bold tabular-nums text-gray-950 dark:text-white">{{ $completePercent }}%</div>
            <div class="text-xs text-gray-500 dark:text-gray-400">{{ $completeCount }} / {{ $total }} ready for taxes</div>
        </div>
    </div>

    <div class="h-2 overflow-hidden rounded-full bg-gray-200 dark:bg-gray-700">
        <div
            class="h-full rounded-full bg-success-500 transition-all duration-500"
            style="width: {{ $completePercent }}%"
        ></div>
    </div>

    <div class="flex flex-wrap gap-2">
        <button
            type="button"
            class="{{ $buttonClass }} border-gray-200 bg-white text-gray-800 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100 dark:hover:bg-gray-800 {{ $isStatusOnlyFilter && blank($activeDocumentationStatus ?? null) ? $activeRing : '' }}"
            wire:click="applyStatusFilter('all')"
        >
            <x-heroicon-o-queue-list class="h-4 w-4" />
            <span>Total</span>
            <span class="rounded-md bg-gray-100 px-2 py-0.5 text-xs font-semibold tabular-nums dark:bg-gray-800">{{ $total }}</span>
        </button>

        @foreach ($statusOverview['statuses'] ?? [] as $status)
            @php
                $isActive = $isStatusOnlyFilter && ($activeDocumentationStatus ?? null) === $status['key'];
                $isZero = ($status['count'] ?? 0) === 0;
                $chipColor = match ($status['key']) {
                    'complete' => 'border-success-200 bg-success-50 text-success-800 dark:border-success-800 dark:bg-success-950/40 dark:text-success-200',
                    'incomplete', 'unlinked' => 'border-warning-200 bg-warning-50 text-warning-800 dark:border-warning-800 dark:bg-warning-950/40 dark:text-warning-200',
                    default => 'border-gray-200 bg-white text-gray-700 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200',
                };
                $badgeColor = match ($status['key']) {
                    'complete' => 'bg-success-100 text-success-700 dark:bg-success-900 dark:text-success-200',
                    'incomplete', 'unlinked' => 'bg-warning-100 text-warning-700 dark:bg-warning-900 dark:text-warning-200',
                    default => 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-200',
                };
            @endphp
            <button
                type="button"
                class="{{ $buttonClass }} {{ $chipColor }} {{ $isZero ? 'opacity-50' : '' }} {{ $isActive ? $activeRing : '' }}"
                wire:click="applyStatusFilter('{{ $status['key'] }}')"
            >
                @switch($status['key'])
                    @case('complete')
                        <x-heroicon-o-check-circle class="h-4 w-4" />
                        @break
                    @case('unlinked')
                        <x-heroicon-o-link-slash class="h-4 w-4" />
                        @break
                    @case('missing_attachment')
                        <x-heroicon-o-paper-clip class="h-4 w-4" />
                        @break
                    @case('missing_generated_pdf')
                        <x-heroicon-o-document class="h-4 w-4" />
                        @break
                    @default
                        <x-heroicon-o-exclamation-circle class="h-4 w-4" />
                @endswitch
                <span>{{ $status['label'] }}</span>
                <span class="rounded-md px-2 py-0.5 text-xs font-semibold tabular-nums {{ $badgeColor }}">{{ $status['count'] }}</span>
            </button>
        @endforeach
    </div>
</div>
