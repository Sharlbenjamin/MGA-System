<x-filament-panels::page>
    <div class="space-y-4">
        {{-- Toolbar: view mode, navigation, today, assign --}}
        <div class="flex flex-wrap items-center justify-between gap-4 rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <div class="flex flex-wrap items-center gap-2">
                <div class="flex rounded-lg bg-gray-100 p-1 dark:bg-gray-800" role="group">
                    <button
                        type="button"
                        wire:click="setViewMode('month')"
                        class="rounded-md px-3 py-1.5 text-sm font-medium transition {{ $viewMode === 'month' ? 'bg-white text-primary-600 shadow dark:bg-gray-700 dark:text-primary-400' : 'text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-gray-100' }}"
                    >
                        Month
                    </button>
                    <button
                        type="button"
                        wire:click="setViewMode('week')"
                        class="rounded-md px-3 py-1.5 text-sm font-medium transition {{ $viewMode === 'week' ? 'bg-white text-primary-600 shadow dark:bg-gray-700 dark:text-primary-400' : 'text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-gray-100' }}"
                    >
                        Week
                    </button>
                </div>
                <div class="flex items-center gap-1">
                    <button
                        type="button"
                        wire:click="previousPeriod"
                        class="rounded-lg p-2 text-gray-500 hover:bg-gray-100 hover:text-gray-700 dark:hover:bg-gray-800 dark:hover:text-gray-300"
                    >
                        <x-heroicon-o-chevron-left class="h-5 w-5" />
                    </button>
                    <span class="min-w-[180px] text-center text-sm font-semibold text-gray-900 dark:text-gray-100">
                        {{ $this->getPeriodLabel() }}
                    </span>
                    <button
                        type="button"
                        wire:click="nextPeriod"
                        class="rounded-lg p-2 text-gray-500 hover:bg-gray-100 hover:text-gray-700 dark:hover:bg-gray-800 dark:hover:text-gray-300"
                    >
                        <x-heroicon-o-chevron-right class="h-5 w-5" />
                    </button>
                </div>
                <button
                    type="button"
                    wire:click="goToToday"
                    class="rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700"
                >
                    Today
                </button>
            </div>
            <div class="flex items-center gap-2">
                <x-filament::button wire:click="openAssignModal()" icon="heroicon-o-plus" size="sm">
                    Assign shift
                </x-filament::button>
            </div>
        </div>

        {{-- Calendar grid --}}
        <div class="rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 overflow-hidden">
            <div class="grid grid-cols-7 border-b border-gray-200 dark:border-gray-700">
                @foreach (['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'] as $day)
                    <div class="border-r border-gray-200 bg-gray-50 px-2 py-2 text-center text-xs font-semibold uppercase text-gray-500 last:border-r-0 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400">
                        {{ $day }}
                    </div>
                @endforeach
            </div>
            <div class="grid grid-cols-7 auto-rows-fr" style="min-height: 480px;">
                @php
                    $days = $this->getCalendarDays();
                    $firstDayOfWeek = $days[0]['date']->dayOfWeek ?? 0;
                @endphp
                @foreach ($days as $day)
                    <div
                        class="group relative border-b border-r border-gray-200 p-2 dark:border-gray-700 {{ !$day['isCurrentMonth'] ? 'bg-gray-50 dark:bg-gray-800/50' : '' }} {{ $day['isToday'] ? 'ring-1 ring-inset ring-primary-500 dark:ring-primary-400' : '' }}"
                    >
                        <div class="flex items-center justify-between">
                            <span class="text-sm font-medium {{ $day['isCurrentMonth'] ? 'text-gray-900 dark:text-gray-100' : 'text-gray-400 dark:text-gray-500' }} {{ $day['isToday'] ? 'rounded bg-primary-100 px-1.5 py-0.5 text-primary-700 dark:bg-primary-900/30 dark:text-primary-300' : '' }}">
                                {{ $day['date']->format('j') }}
                            </span>
                            <button
                                type="button"
                                wire:click="openAssignModal('{{ $day['date']->format('Y-m-d') }}')"
                                class="opacity-0 group-hover:opacity-100 rounded p-1 text-gray-400 hover:bg-primary-100 hover:text-primary-600 dark:hover:bg-primary-900/30 dark:hover:text-primary-400 transition"
                                title="Assign shift"
                            >
                                <x-heroicon-o-plus class="h-4 w-4" />
                            </button>
                        </div>
                        <div class="mt-1 space-y-1 overflow-y-auto" style="max-height: 120px;">
                            @foreach ($day['schedules'] as $schedule)
                                @php
                                    $shiftColor = $schedule->shift->color ? (\Illuminate\Support\Str::startsWith($schedule->shift->color, '#') ? $schedule->shift->color : '#' . $schedule->shift->color) : '#6b7280';
                                @endphp
                                <div
                                    class="flex items-center justify-between gap-1 rounded px-2 py-1 text-xs"
                                    style="background-color: {{ $shiftColor }}20; border-left: 3px solid {{ $shiftColor }};"
                                >
                                    <div class="min-w-0 flex-1">
                                        <span class="font-medium text-gray-900 dark:text-gray-100 truncate block">{{ $schedule->employee->name }}</span>
                                        <span class="text-gray-600 dark:text-gray-400 truncate block">{{ $schedule->shift->name }}</span>
                                    </div>
                                    <button
                                        type="button"
                                        wire:click="removeSchedule({{ $schedule->id }})"
                                        wire:confirm="Remove this shift assignment?"
                                        class="shrink-0 rounded p-0.5 text-gray-400 hover:bg-red-100 hover:text-red-600 dark:hover:bg-red-900/30 dark:hover:text-red-400"
                                        title="Remove"
                                    >
                                        <x-heroicon-o-x-mark class="h-3.5 w-3.5" />
                                    </button>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    {{-- Assign shift modal (always in DOM for form binding, visibility via Livewire) --}}
    <div
        x-data="{ open: @entangle('showAssignModal') }"
        x-show="open"
        x-cloak
        class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/50 p-4 dark:bg-gray-950/80"
        style="display: none;"
        @click.self="$wire.closeAssignModal()"
    >
        <div
            class="w-full max-w-md rounded-xl bg-white shadow-2xl dark:bg-gray-900 dark:ring-1 dark:ring-white/10"
            @click.stop
        >
            <div class="border-b border-gray-200 px-6 py-4 dark:border-gray-700">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Assign shift</h3>
            </div>
            <form wire:submit="saveAssignment" class="p-6">
                {{ $this->assignForm }}
                <div class="mt-6 flex justify-end gap-2">
                    <x-filament::button type="button" color="gray" wire:click="closeAssignModal">
                        Cancel
                    </x-filament::button>
                    <x-filament::button type="submit">
                        Assign
                    </x-filament::button>
                </div>
            </form>
        </div>
    </div>
</x-filament-panels::page>
