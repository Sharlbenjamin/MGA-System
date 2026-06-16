<x-filament-widgets::widget>
    <x-filament::section>
        @php
            $breakdown = $this->breakdown;

            $columns = [
                [
                    'key' => 'trx_in',
                    'label' => 'Trx In',
                    'action' => 'We Generate',
                    'color' => 'success',
                ],
                [
                    'key' => 'trx_out',
                    'label' => 'Trx Out',
                    'action' => 'We Generate',
                    'color' => 'danger',
                ],
                [
                    'key' => 'exp',
                    'label' => 'Exp',
                    'action' => 'We Show Bill',
                    'color' => 'danger',
                ],
                [
                    'key' => 'card',
                    'label' => 'Card',
                    'action' => 'We Show Bill',
                    'color' => 'danger',
                ],
            ];
        @endphp

        @if ($breakdown === null)
            <p class="text-sm text-gray-500 dark:text-gray-400">
                Documentation stats are unavailable until migrations are applied.
            </p>
        @else
            <div class="grid grid-cols-1 gap-6 md:grid-cols-2 lg:grid-cols-4">
                @foreach ($columns as $column)
                    @php
                        $stats = $breakdown[$column['key']];
                        $countClass = $column['color'] === 'success'
                            ? 'text-success-600 dark:text-success-400'
                            : 'text-danger-600 dark:text-danger-400';
                        $borderClass = $column['color'] === 'success'
                            ? 'border-success-200 dark:border-success-800'
                            : 'border-danger-200 dark:border-danger-800';
                    @endphp

                    <div class="space-y-3">
                        <div class="text-sm font-semibold text-gray-950 dark:text-white">
                            {{ $column['label'] }}
                            <span class="text-xs font-normal text-gray-500 dark:text-gray-400">
                                ({{ $column['action'] }})
                            </span>
                        </div>

                        <ul class="space-y-2 border-l-2 {{ $borderClass }} pl-4">
                            <li class="text-sm text-gray-700 dark:text-gray-300">
                                Total
                                <span class="font-semibold {{ $countClass }}">
                                    ({{ $stats['total'] }})
                                </span>
                            </li>
                            <li class="text-sm text-gray-700 dark:text-gray-300">
                                Completed
                                <span class="font-semibold {{ $countClass }}">
                                    ({{ $stats['completed'] }})
                                </span>
                            </li>
                            <li class="text-sm text-gray-700 dark:text-gray-300">
                                Uncompleted
                                <span class="font-semibold {{ $countClass }}">
                                    ({{ $stats['uncompleted'] }})
                                </span>
                            </li>
                        </ul>
                    </div>
                @endforeach
            </div>

            <p class="mt-4 text-xs text-gray-500 dark:text-gray-400">
                Transaction counts reflect current table filters.
            </p>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
