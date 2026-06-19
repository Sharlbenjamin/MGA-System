@foreach ($issues as $issue)
    @php
        $isActive = ($activeDataIssue ?? null) === $issue['key'];
        $activeRing = 'ring-2 ring-warning-500 ring-offset-1 dark:ring-offset-gray-900';
    @endphp
    <div class="flex flex-wrap items-center gap-x-2 text-xs text-warning-800 dark:text-warning-300">
        <span>{{ $issue['label'] }}</span>
        <button
            type="button"
            class="cursor-pointer rounded px-1 font-semibold underline-offset-2 hover:underline focus:outline-none focus:ring-2 focus:ring-warning-500 {{ $isActive ? $activeRing : '' }}"
            wire:click="applyDataIntegrityFilter('{{ $issue['key'] }}', '{{ $category }}')"
        >
            ({{ $issue['count'] }})
        </button>
    </div>
@endforeach
