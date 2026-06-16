<x-filament-widgets::widget>
    <x-filament::section>
        @php
            $breakdown = $this->breakdown;

            $trxOutSections = [
                ['key' => 'trx_out_bulk', 'workflow' => 'trx_out_bulk', 'label' => 'Trx Out Bulk'],
                ['key' => 'trx_out_single', 'workflow' => 'trx_out_single', 'label' => 'Trx Out Single'],
                ['key' => 'exp', 'workflow' => 'expense', 'label' => 'Trx Out Exp'],
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
                    <div class="space-y-3">
                        <div class="text-sm font-semibold text-gray-950 dark:text-white">
                            Trx Out
                            <span class="text-xs font-normal text-gray-500 dark:text-gray-400">(We Generate)</span>
                        </div>

                        <div class="space-y-2">
                            @foreach ($trxOutSections as $section)
                                @include('filament.widgets.partials.documentation-stat-rows', [
                                    'stats' => $breakdown[$section['key']],
                                    'workflow' => $section['workflow'],
                                    'label' => $section['label'],
                                    'color' => 'danger',
                                ])
                            @endforeach
                        </div>
                    </div>

                    <div class="space-y-3">
                        <div class="text-sm font-semibold text-gray-950 dark:text-white">
                            Card
                            <span class="text-xs font-normal text-gray-500 dark:text-gray-400">(We Show Bill)</span>
                        </div>

                        @include('filament.widgets.partials.documentation-stat-rows', [
                            'stats' => $breakdown['card'],
                            'workflow' => 'card',
                            'color' => 'danger',
                        ])
                    </div>
                </div>
            </div>

            <p class="mt-4 text-xs text-gray-500 dark:text-gray-400">
                Click a count to filter the table. Transaction counts reflect current filters.
            </p>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
