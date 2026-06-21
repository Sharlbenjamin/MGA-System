@foreach ($issues as $issue)
    @php
        $isActive = ($activeDataIssue ?? null) === $issue['key'];
        $activeRing = 'ring-2 ring-warning-500 ring-offset-1 dark:ring-offset-gray-900';
    @endphp
    <button
        type="button"
        class="inline-flex items-center gap-2 rounded-full border border-warning-200 bg-warning-50 px-3 py-1 text-xs font-medium text-warning-800 hover:bg-warning-100 focus:outline-none focus:ring-2 focus:ring-warning-500 dark:border-warning-800 dark:bg-warning-950/40 dark:text-warning-200 dark:hover:bg-warning-950/60 {{ $isActive ? $activeRing : '' }}"
        wire:click="applyDataIntegrityFilter('{{ $issue['key'] }}', '{{ $category }}')"
    >
        <span>{{ $issue['label'] }}</span>
        <span class="rounded-full bg-warning-100 px-2 py-0.5 text-[11px] font-semibold tabular-nums dark:bg-warning-900">{{ $issue['count'] }}</span>
    </button>
@endforeach
