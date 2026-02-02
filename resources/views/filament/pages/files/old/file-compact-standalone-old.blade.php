{{--
  BACKUP: Old standalone compact view (uses app layout / Vite).
  To restore: copy this file to file-compact-standalone.blade.php and use
  FileCompactViewController to return this view (and run npm run build for Vite).
--}}
<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                {{ $record->mga_reference }} · {{ $record->status }}
            </h2>
            <a href="{{ route('filament.admin.resources.files.view', ['record' => $record]) }}"
               class="inline-flex items-center rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 shadow-sm hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700">
                ← Old View
            </a>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            @include('filament.pages.files.old._file-compact-content-old', [
                'record' => $record,
                'summaryText' => $summaryText,
                'compactTasks' => $compactTasks,
            ])
        </div>
    </div>
</x-app-layout>
