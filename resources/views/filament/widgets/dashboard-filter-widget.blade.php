<x-filament-widgets::widget>
    <x-filament::section>
        <div class="flex gap-4">
            <div class="flex-1">
                <x-filament::input.wrapper>
                    <x-filament::input.select
                        wire:model.live="selectedDuration"
                    >
                        <x-slot name="label">
                            Duration
                        </x-slot>
                        @foreach($this->durationOptions as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </x-filament::input.select>
                </x-filament::input.wrapper>
            </div>
            
            <div class="flex-1">
                @if($selectedDuration === 'Day')
                    <x-filament::input.wrapper>
                        <input
                            wire:model.live="selectedDate"
                            type="date"
                            class="block w-full rounded-lg border-gray-300 bg-white px-3 py-2 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:border-primary-500 focus:ring-2 focus:ring-inset focus:ring-primary-500 disabled:cursor-not-allowed disabled:opacity-50 dark:border-gray-600 dark:bg-gray-700 dark:text-white dark:ring-gray-500 dark:placeholder:text-gray-400 dark:focus:border-primary-500 dark:focus:ring-primary-500 sm:text-sm sm:leading-6"
                        >
                        <x-slot name="label">
                            Date
                        </x-slot>
                    </x-filament::input.wrapper>
                @elseif($selectedDuration === 'Month')
                    <x-filament::input.wrapper>
                        <x-filament::input.select
                            wire:model.live="selectedMonth"
                        >
                            <x-slot name="label">
                                Month
                            </x-slot>
                            @foreach($this->monthOptions as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </x-filament::input.select>
                    </x-filament::input.wrapper>
                @elseif($selectedDuration === 'Year')
                    <x-filament::input.wrapper>
                        <x-filament::input.select
                            wire:model.live="selectedYear"
                        >
                            <x-slot name="label">
                                Year
                            </x-slot>
                            @foreach($this->yearOptions as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </x-filament::input.select>
                    </x-filament::input.wrapper>
                @endif
            </div>
        </div>
    </x-filament::section>
</x-filament-widgets::widget> 