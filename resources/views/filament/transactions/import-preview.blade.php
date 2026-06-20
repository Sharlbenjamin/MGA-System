<div class="space-y-3 text-sm text-gray-700 dark:text-gray-300">
    <div class="grid grid-cols-2 gap-2 sm:grid-cols-4">
        <div>
            <span class="font-medium text-gray-950 dark:text-white">{{ $preview['total'] ?? 0 }}</span>
            <span class="block text-xs text-gray-500">Total rows</span>
        </div>
        <div>
            <span class="font-medium text-success-600">{{ $preview['to_import'] ?? 0 }}</span>
            <span class="block text-xs text-gray-500">To import</span>
        </div>
        <div>
            <span class="font-medium text-warning-600">{{ $preview['skipped_existing'] ?? 0 }}</span>
            <span class="block text-xs text-gray-500">Skip (existing)</span>
        </div>
        <div>
            <span class="font-medium text-gray-500">{{ $preview['skipped_in_file'] ?? 0 }}</span>
            <span class="block text-xs text-gray-500">Skip (duplicate in file)</span>
        </div>
    </div>

    @if (! empty($preview['errors']))
        <div class="rounded-lg border border-danger-300 bg-danger-50 p-3 dark:border-danger-700 dark:bg-danger-950">
            <p class="mb-1 font-medium text-danger-700 dark:text-danger-300">Validation errors</p>
            <ul class="list-inside list-disc text-xs text-danger-600 dark:text-danger-400">
                @foreach (array_slice($preview['errors'], 0, 10) as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @if (! empty($preview['preview_rows']))
        <div>
            <p class="mb-1 font-medium text-gray-950 dark:text-white">Sample rows to import</p>
            <ul class="space-y-1 text-xs">
                @foreach ($preview['preview_rows'] as $row)
                    <li>
                        Row {{ $row['row'] }} · {{ $row['date'] }} · {{ $row['type'] }} · €{{ number_format((float) ($row['amount'] ?? 0), 2) }}
                        @if (! empty($row['reference']))
                            · {{ $row['reference'] }}
                        @endif
                    </li>
                @endforeach
            </ul>
        </div>
    @endif
</div>
