@php
    $countClass = $color === 'success'
        ? 'text-success-600 dark:text-success-400'
        : 'text-danger-600 dark:text-danger-400';
    $borderClass = $color === 'success'
        ? 'border-success-200 dark:border-success-800'
        : 'border-danger-200 dark:border-danger-800';
    $buttonClass = 'cursor-pointer rounded px-1 underline-offset-2 hover:underline focus:outline-none focus:ring-2 focus:ring-primary-500';
@endphp

<ul class="space-y-2 border-l-2 {{ $borderClass }} pl-4">
    <li class="text-sm text-gray-700 dark:text-gray-300">
        Total
        <button
            type="button"
            class="{{ $buttonClass }} font-semibold {{ $countClass }}"
            wire:click="applyDocumentationFilter('{{ $workflow }}', 'all')"
        >
            ({{ $stats['total'] }})
        </button>
    </li>
    <li class="text-sm text-gray-700 dark:text-gray-300">
        Completed
        <button
            type="button"
            class="{{ $buttonClass }} font-semibold {{ $countClass }}"
            wire:click="applyDocumentationFilter('{{ $workflow }}', 'completed')"
        >
            ({{ $stats['completed'] }})
        </button>
    </li>
    <li class="text-sm text-gray-700 dark:text-gray-300">
        Uncompleted
        <button
            type="button"
            class="{{ $buttonClass }} font-semibold {{ $countClass }}"
            wire:click="applyDocumentationFilter('{{ $workflow }}', 'uncompleted')"
        >
            ({{ $stats['uncompleted'] }})
        </button>
    </li>
</ul>
