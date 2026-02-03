<x-filament-panels::page>
    <div class="space-y-6">
        {{-- Actions: Add new month, Export --}}
        <div class="flex flex-wrap items-center justify-end gap-2">
            @if($this->getNextMonthToCreate())
                <x-filament::button wire:click="addNewMonth" icon="heroicon-o-plus-circle" size="md">
                    Add new month
                </x-filament::button>
            @endif
            <a href="{{ route('hr.monthly-report.export', ['year' => $year, 'month' => $month]) }}"
               target="_blank"
               class="inline-flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700">
                @svg('heroicon-o-arrow-down-tray', 'h-5 w-5')
                Export this month (CSV)
            </a>
        </div>

        {{-- Month tabs (navigate between created months) --}}
        @php
            $createdMonths = $this->getCreatedMonths();
        @endphp
        @if(count($createdMonths) > 0)
            <div class="rounded-xl bg-white p-2 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                <div class="flex flex-wrap gap-1">
                    @foreach($createdMonths as $m)
                        @php
                            $label = \Carbon\Carbon::createFromDate($m['year'], $m['month'], 1)->format('M Y');
                            $isActive = $year === $m['year'] && $month === $m['month'];
                        @endphp
                        <button
                            type="button"
                            wire:click="selectMonth({{ $m['year'] }}, {{ $m['month'] }})"
                            class="rounded-lg px-3 py-2 text-sm font-medium transition {{ $isActive
                                ? 'bg-primary-600 text-white dark:bg-primary-500'
                                : 'bg-gray-100 text-gray-700 hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600' }}"
                        >
                            {{ $label }}
                        </button>
                    @endforeach
                </div>
            </div>
        @endif

        {{-- Month/Year selector --}}
        <div class="flex flex-wrap items-center gap-4 rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <div class="flex items-center gap-2">
                <label for="report-month" class="text-sm font-medium text-gray-700 dark:text-gray-300">Month</label>
                <select id="report-month" wire:model.live="month"
                    class="rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white text-sm">
                    @foreach(range(1, 12) as $m)
                        <option value="{{ $m }}">{{ \Carbon\Carbon::createFromDate(2000, $m, 1)->format('F') }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex items-center gap-2">
                <label for="report-year" class="text-sm font-medium text-gray-700 dark:text-gray-300">Year</label>
                <select id="report-year" wire:model.live="year"
                    class="rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white text-sm">
                    @foreach(array_reverse(range(now()->year - 5, now()->year)) as $y)
                        <option value="{{ $y }}">{{ $y }}</option>
                    @endforeach
                </select>
            </div>
            <span class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ $this->getMonthLabel() }}</span>
        </div>

        {{-- Report table --}}
        <div class="rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-800">
                        <tr>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Employee</th>
                            <th scope="col" class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Basic salary</th>
                            <th scope="col" class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Bonus</th>
                            <th scope="col" class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Deductions</th>
                            <th scope="col" class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Net salary</th>
                            <th scope="col" class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Shifts done</th>
                            <th scope="col" class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Hours</th>
                            <th scope="col" class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Cases</th>
                            <th scope="col" class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Tasks done</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700 dark:bg-gray-900">
                        @forelse($this->getReportRows() as $row)
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/50"
                                x-data="{
                                    bonus: {{ json_encode((float) $row['bonus']) }},
                                    deductions: {{ json_encode((float) $row['deductions']) }},
                                    save(employeeId) {
                                        $wire.updateSalaryRow(employeeId, Number(this.bonus) || 0, Number(this.deductions) || 0);
                                    }
                                }"
                            >
                                <td class="whitespace-nowrap px-4 py-3 text-sm font-medium text-gray-900 dark:text-gray-100">
                                    {{ $row['employee_name'] }}
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 text-sm text-right text-gray-700 dark:text-gray-300">
                                    {{ $row['base_salary'] > 0 ? number_format($row['base_salary'], 2) : '—' }}
                                </td>
                                <td class="whitespace-nowrap px-4 py-2 text-sm text-right">
                                    <input type="number"
                                        step="0.01"
                                        min="0"
                                        x-model.number="bonus"
                                        @blur="save({{ $row['employee_id'] }})"
                                        class="w-24 rounded-lg border-gray-300 text-right text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                                        placeholder="0"
                                    >
                                </td>
                                <td class="whitespace-nowrap px-4 py-2 text-sm text-right">
                                    <input type="number"
                                        step="0.01"
                                        min="0"
                                        x-model.number="deductions"
                                        @blur="save({{ $row['employee_id'] }})"
                                        class="w-24 rounded-lg border-gray-300 text-right text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                                        placeholder="0"
                                    >
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 text-sm text-right font-medium text-gray-900 dark:text-gray-100">
                                    {{ $row['net_salary'] != 0 ? number_format($row['net_salary'], 2) : '—' }}
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 text-sm text-right text-gray-700 dark:text-gray-300">
                                    {{ $row['days_scheduled'] }}
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 text-sm text-right text-gray-500 dark:text-gray-400">
                                    {{ $row['hours'] !== null ? $row['hours'] : '—' }}
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 text-sm text-right text-gray-700 dark:text-gray-300">
                                    {{ $row['cases'] }}
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 text-sm text-right text-gray-700 dark:text-gray-300">
                                    {{ $row['tasks'] }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="px-4 py-8 text-center text-sm text-gray-500 dark:text-gray-400">
                                    No active employees for this period.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-filament-panels::page>
