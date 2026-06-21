@php
    $chipBase = 'inline-flex items-center rounded-lg px-2.5 py-1 text-xs font-semibold tabular-nums transition focus:outline-none focus:ring-2 focus:ring-primary-500';
    $activeRing = 'ring-2 ring-primary-500 ring-offset-1 dark:ring-offset-gray-900';
    $countTone = $color === 'success'
        ? 'bg-success-100 text-success-800 hover:bg-success-200 dark:bg-success-900/50 dark:text-success-200 dark:hover:bg-success-900'
        : 'bg-danger-100 text-danger-800 hover:bg-danger-200 dark:bg-danger-900/50 dark:text-danger-200 dark:hover:bg-danger-900';
    $mutedTone = 'bg-gray-100 text-gray-700 hover:bg-gray-200 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700';
@endphp

<div class="space-y-3 px-4 py-3">
    <div class="flex flex-wrap items-center justify-between gap-2">
        <div class="text-sm font-semibold text-gray-950 dark:text-white">{{ $label }}</div>
        <div class="text-xs font-medium text-gray-500 dark:text-gray-400">{{ $percent }}% complete</div>
    </div>

    <div class="h-1.5 overflow-hidden rounded-full bg-gray-200 dark:bg-gray-700">
        <div class="{{ $progressClass }} h-full rounded-full transition-all duration-500" style="width: {{ $percent }}%"></div>
    </div>

    <div class="text-xs text-gray-500 dark:text-gray-400">
        {{ $stats['completed'] }} / {{ $stats['total'] }} completed
    </div>

    <div class="flex flex-wrap gap-2">
        <button
            type="button"
            class="{{ $chipBase }} {{ $countTone }} {{ ($activeCategory ?? null) === $workflow && ($activeCompletion ?? null) === 'all' && blank($activeDocumentationStatus ?? null) ? $activeRing : '' }}"
            wire:click="applyDocumentationFilter('{{ $workflow }}', 'all')"
        >
            Total {{ $stats['total'] }}
        </button>
        <button
            type="button"
            class="{{ $chipBase }} {{ $countTone }} {{ ($activeCategory ?? null) === $workflow && ($activeCompletion ?? null) === 'completed' ? $activeRing : '' }}"
            wire:click="applyDocumentationFilter('{{ $workflow }}', 'completed')"
        >
            Done {{ $stats['completed'] }}
        </button>
        <button
            type="button"
            class="{{ $chipBase }} {{ $mutedTone }} {{ ($activeCategory ?? null) === $workflow && ($activeCompletion ?? null) === 'uncompleted' && blank($activeDocumentationStatus ?? null) ? $activeRing : '' }}"
            wire:click="applyDocumentationFilter('{{ $workflow }}', 'uncompleted')"
        >
            Todo {{ $stats['uncompleted'] }}
        </button>
    </div>
</div>
