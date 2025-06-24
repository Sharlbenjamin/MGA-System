<x-filament-widgets::widget>
    <x-filament::section>
        <div class="grid grid-cols-2 gap-4">
            <div>
                <label for="selectedYear" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                    Year
                </label>
                <select 
                    id="selectedYear"
                    wire:model.live="selectedYear"
                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white sm:text-sm"
                >
                    @foreach($this->getYearOptions() as $value => $label)
                        <option value="{{ $value }}" {{ $selectedYear == $value ? 'selected' : '' }}>
                            {{ $label }}
                        </option>
                    @endforeach
                </select>
            </div>
            
            <div>
                <label for="selectedQuarter" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                    Quarter
                </label>
                <select 
                    id="selectedQuarter"
                    wire:model.live="selectedQuarter"
                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white sm:text-sm"
                >
                    @foreach($this->getQuarterOptions() as $value => $label)
                        <option value="{{ $value }}" {{ $selectedQuarter == $value ? 'selected' : '' }}>
                            {{ $label }}
                        </option>
                    @endforeach
                </select>
            </div>
        </div>
    </x-filament::section>
</x-filament-widgets::widget> 