@php
    $countClass = $color === 'success'
        ? 'text-success-600 dark:text-success-400'
        : 'text-danger-600 dark:text-danger-400';
    $borderClass = $color === 'success'
        ? 'border-success-200 dark:border-success-800'
        : 'border-danger-200 dark:border-danger-800';
    $buttonClass = 'cursor-pointer rounded px-1 underline-offset-2 hover:underline focus:outline-none focus:ring-2 focus:ring-primary-500';
    $activeRing = 'ring-2 ring-primary-500 ring-offset-1 dark:ring-offset-gray-900';
@endphp

<div class="flex flex-wrap items-center gap-x-4 gap-y-1 border-l-2 {{ $borderClass }} pl-4 text-sm text-gray-700 dark:text-gray-300">
    @if (! empty($label))
        <span class="font-medium text-gray-950 dark:text-white">{{ $label }}</span>
        <span class="text-gray-400 dark:text-gray-500" aria-hidden="true">·</span>
    @endif
    <span>
        Total
        <button
            type="button"
            class="{{ $buttonClass }} font-semibold {{ $countClass }} {{ ($activeCategory ?? null) === $workflow && ($activeCompletion ?? null) === 'all' && blank($activeDocumentationStatus ?? null) ? $activeRing : '' }}"
            wire:click="applyDocumentationFilter('{{ $workflow }}', 'all')"
        >
            ({{ $stats['total'] }})
        </button>
    </span>
    <span>
        Completed
        <button
            type="button"
            class="{{ $buttonClass }} font-semibold {{ $countClass }} {{ ($activeCategory ?? null) === $workflow && ($activeCompletion ?? null) === 'completed' ? $activeRing : '' }}"
            wire:click="applyDocumentationFilter('{{ $workflow }}', 'completed')"
        >
            ({{ $stats['completed'] }})
        </button>
    </span>
    <span>
        Uncompleted
        <button
            type="button"
            class="{{ $buttonClass }} font-semibold {{ $countClass }} {{ ($activeCategory ?? null) === $workflow && ($activeCompletion ?? null) === 'uncompleted' && blank($activeDocumentationStatus ?? null) ? $activeRing : '' }}"
            wire:click="applyDocumentationFilter('{{ $workflow }}', 'uncompleted')"
        >
            ({{ $stats['uncompleted'] }})
        </button>
    </span>
</div>
