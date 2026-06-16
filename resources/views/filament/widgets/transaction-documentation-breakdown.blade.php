<x-filament-widgets::widget>
    <x-filament::section>
        @php
            $breakdown = $this->breakdown;

            $payableSections = [
                ['key' => 'trx_out', 'workflow' => 'trx_out', 'label' => 'Trx Out', 'action' => 'We Generate'],
                ['key' => 'exp', 'workflow' => 'expense', 'label' => 'Exp', 'action' => 'We Show Bill'],
                ['key' => 'card', 'workflow' => 'card', 'label' => 'Card', 'action' => 'We Show Bill'],
            ];
        @endphp

        @if ($breakdown === null)
            <p class="text-sm text-gray-500 dark:text-gray-400">
                Documentation stats are unavailable until migrations are applied.
            </p>
        @else
            <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                {{-- Trx In column (green) --}}
                <div class="space-y-3">
                    <div class="text-sm font-semibold text-gray-950 dark:text-white">
                        Trx In
                        <span class="text-xs font-normal text-gray-500 dark:text-gray-400">(We Generate)</span>
                    </div>

                    @include('filament.widgets.partials.documentation-stat-rows', [
                        'stats' => $breakdown['trx_in'],
                        'workflow' => 'income',
                        'color' => 'success',
                    ])
                </div>

                {{-- Payables column (red) --}}
                <div class="space-y-6">
                    @foreach ($payableSections as $section)
                        <div class="space-y-3">
                            <div class="text-sm font-semibold text-gray-950 dark:text-white">
                                {{ $section['label'] }}
                                <span class="text-xs font-normal text-gray-500 dark:text-gray-400">
                                    ({{ $section['action'] }})
                                </span>
                            </div>

                            @include('filament.widgets.partials.documentation-stat-rows', [
                                'stats' => $breakdown[$section['key']],
                                'workflow' => $section['workflow'],
                                'color' => 'danger',
                            ])
                        </div>
                    @endforeach
                </div>
            </div>

            <p class="mt-4 text-xs text-gray-500 dark:text-gray-400">
                Click a count to filter the table. Transaction counts reflect current filters. Use
                <strong>Documentation workflow</strong> for Trx Out Bulk.
            </p>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
