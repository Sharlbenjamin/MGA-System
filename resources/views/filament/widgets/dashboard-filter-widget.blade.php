<x-filament-widgets::widget>
    <x-filament::section>
        <div class="flex gap-4">
            <div class="flex-1">
                <label for="selectedDuration" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                    Duration
                </label>
                <select 
                    id="selectedDuration"
                    wire:model.live="selectedDuration"
                    class="mt-1 block w-full rounded-lg border-gray-300 bg-white px-3 py-2 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:border-primary-500 focus:ring-2 focus:ring-inset focus:ring-primary-500 disabled:cursor-not-allowed disabled:opacity-50 dark:border-gray-600 dark:bg-gray-700 dark:text-white dark:ring-gray-500 dark:placeholder:text-gray-400 dark:focus:border-primary-500 dark:focus:ring-primary-500 sm:text-sm sm:leading-6"
                >
                    @foreach($this->getDurationOptions() as $value => $label)
                        <option value="{{ $value }}" {{ $selectedDuration == $value ? 'selected' : '' }}>
                            {{ $label }}
                        </option>
                    @endforeach
                </select>
            </div>
            
            @if($selectedDuration === 'Day')
            <div class="flex-1">
                <label for="selectedDate" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                    Date
                </label>
                <input 
                    type="date"
                    id="selectedDate"
                    wire:model.live="selectedDate"
                    class="mt-1 block w-full rounded-lg border-gray-300 bg-white px-3 py-2 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:border-primary-500 focus:ring-2 focus:ring-inset focus:ring-primary-500 disabled:cursor-not-allowed disabled:opacity-50 dark:border-gray-600 dark:bg-gray-700 dark:text-white dark:ring-gray-500 dark:placeholder:text-gray-400 dark:focus:border-primary-500 dark:focus:ring-primary-500 sm:text-sm sm:leading-6"
                >
            </div>
            @endif
            
            @if($selectedDuration === 'Month')
            <div class="flex-1">
                <label for="selectedMonth" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                    Month
                </label>
                <select 
                    id="selectedMonth"
                    wire:model.live="selectedMonth"
                    class="mt-1 block w-full rounded-lg border-gray-300 bg-white px-3 py-2 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:border-primary-500 focus:ring-2 focus:ring-inset focus:ring-primary-500 disabled:cursor-not-allowed disabled:opacity-50 dark:border-gray-600 dark:bg-gray-700 dark:text-white dark:ring-gray-500 dark:placeholder:text-gray-400 dark:focus:border-primary-500 dark:focus:ring-primary-500 sm:text-sm sm:leading-6"
                >
                    @foreach($this->getMonthOptions() as $value => $label)
                        <option value="{{ $value }}" {{ $selectedMonth == $value ? 'selected' : '' }}>
                            {{ $label }}
                        </option>
                    @endforeach
                </select>
            </div>
            @endif

            @if($selectedDuration === 'Year')
            <div class="flex-1">
                <label for="selectedYear" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                    Year
                </label>
                <select 
                    id="selectedYear"
                    wire:model.live="selectedYear"
                    class="mt-1 block w-full rounded-lg border-gray-300 bg-white px-3 py-2 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:border-primary-500 focus:ring-2 focus:ring-inset focus:ring-primary-500 disabled:cursor-not-allowed disabled:opacity-50 dark:border-gray-600 dark:bg-gray-700 dark:text-white dark:ring-gray-500 dark:placeholder:text-gray-400 dark:focus:border-primary-500 dark:focus:ring-primary-500 sm:text-sm sm:leading-6"
                >
                    @foreach($this->getYearOptions() as $value => $label)
                        <option value="{{ $value }}" {{ $selectedYear == $value ? 'selected' : '' }}>
                            {{ $label }}
                        </option>
                    @endforeach
                </select>
            </div>
            @endif
        </div>
    </x-filament::section>
</x-filament-widgets::widget> 