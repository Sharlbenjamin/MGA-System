@php
    $accentClass = $color === 'success'
        ? 'border-l-success-500'
        : 'border-l-danger-500';
    $progressClass = $color === 'success'
        ? 'bg-success-500'
        : 'bg-danger-500';
    $percent = ($stats['total'] ?? 0) > 0
        ? round((($stats['completed'] ?? 0) / $stats['total']) * 100)
        : 0;
    $hasDataIssues = ! empty($stats['data_issues']);
    $hasMissingSteps = ! empty($stats['missing_steps']);
    $issuesOpen = ($activeDataIssue ?? null) && ($activeCategory ?? null) === $category;
    $stepsOpen = ($activeDocumentationStatus ?? null) && ($activeCategory ?? null) === $category;
@endphp

<div
    class="overflow-hidden rounded-xl border border-gray-200 bg-white/80 shadow-sm dark:border-gray-700 dark:bg-gray-900/70 {{ $accentClass }} border-l-4"
    x-data="{ issuesOpen: @js($issuesOpen), stepsOpen: @js($stepsOpen) }"
>
    @include('filament.widgets.partials.documentation-stat-rows', [
        'stats' => $stats,
        'workflow' => $category,
        'label' => $label,
        'color' => $color,
        'percent' => $percent,
        'progressClass' => $progressClass,
        'activeCategory' => $activeCategory ?? null,
        'activeCompletion' => $activeCompletion ?? null,
        'activeDocumentationStatus' => $activeDocumentationStatus ?? null,
    ])

    @if ($hasDataIssues)
        <div class="border-t border-gray-100 px-4 py-2 dark:border-gray-800">
            <button
                type="button"
                class="flex w-full items-center justify-between text-left text-xs font-semibold uppercase tracking-wide text-warning-700 dark:text-warning-400"
                x-on:click="issuesOpen = ! issuesOpen"
            >
                <span>Data issues ({{ count($stats['data_issues']) }})</span>
                <x-heroicon-m-chevron-down class="h-4 w-4 transition" x-bind:class="{ 'rotate-180': issuesOpen }" />
            </button>
            <div class="mt-2 space-y-2" x-show="issuesOpen" x-collapse>
                @include('filament.widgets.partials.documentation-nested-issues', [
                    'issues' => $stats['data_issues'],
                    'category' => $category,
                    'activeDataIssue' => $activeDataIssue ?? null,
                ])
            </div>
        </div>
    @endif

    @if ($hasMissingSteps)
        <div class="border-t border-gray-100 px-4 py-2 dark:border-gray-800">
            <button
                type="button"
                class="flex w-full items-center justify-between text-left text-xs font-semibold uppercase tracking-wide text-gray-600 dark:text-gray-400"
                x-on:click="stepsOpen = ! stepsOpen"
            >
                <span>Missing steps ({{ count($stats['missing_steps']) }})</span>
                <x-heroicon-m-chevron-down class="h-4 w-4 transition" x-bind:class="{ 'rotate-180': stepsOpen }" />
            </button>
            <div class="mt-2 space-y-2" x-show="stepsOpen" x-collapse>
                @include('filament.widgets.partials.documentation-nested-steps', [
                    'steps' => $stats['missing_steps'],
                    'category' => $category,
                    'activeCategory' => $activeCategory ?? null,
                    'activeDocumentationStatus' => $activeDocumentationStatus ?? null,
                ])
            </div>
        </div>
    @endif
</div>
