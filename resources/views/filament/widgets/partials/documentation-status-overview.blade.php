@php
    $buttonClass = 'cursor-pointer rounded px-1 underline-offset-2 hover:underline focus:outline-none focus:ring-2 focus:ring-primary-500';
    $activeRing = 'ring-2 ring-primary-500 ring-offset-1 dark:ring-offset-gray-900';
    $isStatusOnlyFilter = blank($activeCategory ?? null)
        && blank($activeCompletion ?? null)
        && blank($activeDataIssue ?? null);
@endphp

<div class="space-y-2 border-b border-gray-200 pb-4 dark:border-gray-700">
    <div class="text-sm font-semibold text-gray-950 dark:text-white">
        Documentation status
        <span class="text-xs font-normal text-gray-500 dark:text-gray-400">(all transactions)</span>
    </div>

    <div class="flex flex-wrap items-center gap-x-4 gap-y-1 text-sm text-gray-700 dark:text-gray-300">
        <span>
            Total
            <button
                type="button"
                class="{{ $buttonClass }} font-semibold text-gray-950 dark:text-white {{ $isStatusOnlyFilter && blank($activeDocumentationStatus ?? null) ? $activeRing : '' }}"
                wire:click="applyStatusFilter('all')"
            >
                ({{ $statusOverview['total'] ?? 0 }})
            </button>
        </span>

        @foreach ($statusOverview['statuses'] ?? [] as $status)
            @php
                $isActive = $isStatusOnlyFilter && ($activeDocumentationStatus ?? null) === $status['key'];
                $isZero = ($status['count'] ?? 0) === 0;
                $countClass = $isZero
                    ? 'text-gray-400 dark:text-gray-500'
                    : (in_array($status['key'], ['complete'], true)
                        ? 'text-success-600 dark:text-success-400'
                        : (in_array($status['key'], ['revised'], true)
                            ? 'text-info-600 dark:text-info-400'
                            : 'text-warning-600 dark:text-warning-400'));
            @endphp
            <span class="{{ $isZero ? 'text-gray-400 dark:text-gray-500' : '' }}">
                {{ $status['label'] }}
                <button
                    type="button"
                    class="{{ $buttonClass }} font-semibold {{ $countClass }} {{ $isActive ? $activeRing : '' }}"
                    wire:click="applyStatusFilter('{{ $status['key'] }}')"
                >
                    ({{ $status['count'] }})
                </button>
            </span>
        @endforeach
    </div>
</div>
