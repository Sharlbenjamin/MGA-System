@foreach ($steps as $step)
    @php
        $isActive = ($activeCategory ?? null) === $category
            && ($activeDocumentationStatus ?? null) === $step['key'];
        $activeRing = 'ring-2 ring-primary-500 ring-offset-1 dark:ring-offset-gray-900';
    @endphp
    <div class="flex flex-wrap items-center gap-x-2 text-xs text-gray-600 dark:text-gray-400">
        <span>{{ $step['label'] }}</span>
        <button
            type="button"
            class="cursor-pointer rounded px-1 font-semibold text-gray-900 underline-offset-2 hover:underline focus:outline-none focus:ring-2 focus:ring-primary-500 dark:text-gray-200 {{ $isActive ? $activeRing : '' }}"
            wire:click="applyDocumentationFilter('{{ $category }}', 'all', '{{ $step['key'] }}')"
        >
            ({{ $step['count'] }})
        </button>
    </div>
@endforeach
