@php
    $heading = $this->getHeading();
    $description = $this->getDescription();
    $hasHeading = filled($heading);
    $hasDescription = filled($description);
@endphp

<x-filament-widgets::widget class="fi-wi-stats-overview grid gap-y-4">
    @if ($hasHeading || $hasDescription)
        <div class="fi-wi-stats-overview-header grid gap-y-1">
            @if ($hasHeading)
                <h3 class="fi-wi-stats-overview-header-heading text-base font-semibold leading-6 text-gray-950 dark:text-white">
                    {{ $heading }}
                </h3>
            @endif

            @if ($hasDescription)
                <p class="fi-wi-stats-overview-header-description overflow-hidden break-words text-sm text-gray-500 dark:text-gray-400">
                    {{ $description }}
                </p>
            @endif
        </div>
    @endif

    <div
        @if ($pollingInterval = $this->getPollingInterval())
            wire:poll.{{ $pollingInterval }}
        @endif
        class="fi-wi-stats-overview-stats-ctn grid gap-6"
        style="grid-template-columns: repeat(auto-fit, minmax(14rem, 1fr));"
    >
        @foreach ($this->getCachedStats() as $stat)
            {{ $stat }}
        @endforeach
    </div>
</x-filament-widgets::widget>
