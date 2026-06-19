<div class="space-y-2">
    @include('filament.widgets.partials.documentation-stat-rows', [
        'stats' => $stats,
        'workflow' => $category,
        'label' => $label,
        'color' => $color,
        'activeCategory' => $activeCategory ?? null,
        'activeCompletion' => $activeCompletion ?? null,
        'activeDocumentationStatus' => $activeDocumentationStatus ?? null,
    ])

    @if (! empty($stats['data_issues']))
        <div class="ml-4 space-y-1 border-l-2 border-warning-300 pl-3 dark:border-warning-700">
            <div class="text-xs font-semibold uppercase tracking-wide text-warning-700 dark:text-warning-400">
                Data issues
            </div>
            @include('filament.widgets.partials.documentation-nested-issues', [
                'issues' => $stats['data_issues'],
                'category' => $category,
                'activeDataIssue' => $activeDataIssue ?? null,
            ])
        </div>
    @endif

    @if (! empty($stats['missing_steps']))
        <div class="ml-4 space-y-1 border-l-2 border-gray-300 pl-3 dark:border-gray-600">
            <div class="text-xs font-semibold uppercase tracking-wide text-gray-600 dark:text-gray-400">
                Missing steps
            </div>
            @include('filament.widgets.partials.documentation-nested-steps', [
                'steps' => $stats['missing_steps'],
                'category' => $category,
                'activeCategory' => $activeCategory ?? null,
                'activeDocumentationStatus' => $activeDocumentationStatus ?? null,
            ])
        </div>
    @endif
</div>
