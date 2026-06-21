@foreach ($steps as $step)
    @php
        $isActive = ($activeCategory ?? null) === $category
            && ($activeDocumentationStatus ?? null) === $step['key'];
        $activeRing = 'ring-2 ring-primary-500 ring-offset-1 dark:ring-offset-gray-900';
    @endphp
    <button
        type="button"
        class="inline-flex items-center gap-2 rounded-full border border-gray-200 bg-gray-50 px-3 py-1 text-xs font-medium text-gray-700 hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-primary-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700 {{ $isActive ? $activeRing : '' }}"
        wire:click="applyDocumentationFilter('{{ $category }}', 'all', '{{ $step['key'] }}')"
    >
        <span>{{ $step['label'] }}</span>
        <span class="rounded-full bg-gray-200 px-2 py-0.5 text-[11px] font-semibold tabular-nums dark:bg-gray-700">{{ $step['count'] }}</span>
    </button>
@endforeach
