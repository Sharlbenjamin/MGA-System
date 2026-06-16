<x-filament-widgets::widget>
    <x-filament::section>
        @php
            $breakdown = $this->breakdown;
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
                        Trx In → We Generate
                        <span class="font-bold text-success-600 dark:text-success-400">
                            ({{ $breakdown['trx_in']['total'] }})
                        </span>
                    </div>

                    <ul class="space-y-2 border-l-2 border-success-200 pl-4 dark:border-success-800">
                        <li class="text-sm text-gray-700 dark:text-gray-300">
                            Trx with Generated PDF
                            <span class="font-semibold text-success-600 dark:text-success-400">
                                ({{ $breakdown['trx_in']['with_pdf'] }})
                            </span>
                            <span class="text-xs text-gray-500 dark:text-gray-400">(We Generate)</span>
                        </li>
                        <li class="text-sm text-gray-700 dark:text-gray-300">
                            Trx without Generated PDF
                            <span class="font-semibold text-success-600 dark:text-success-400">
                                ({{ $breakdown['trx_in']['without_pdf'] }})
                            </span>
                            <span class="text-xs text-gray-500 dark:text-gray-400">(We Generate)</span>
                        </li>
                    </ul>
                </div>

                {{-- Trx Out column (red) --}}
                <div class="space-y-3">
                    <div class="text-sm font-semibold text-gray-950 dark:text-white">
                        Trx Out → without documents
                        <span class="font-bold text-danger-600 dark:text-danger-400">
                            ({{ $breakdown['trx_out']['total_incomplete'] }})
                        </span>
                    </div>

                    <ul class="space-y-2 border-l-2 border-danger-200 pl-4 dark:border-danger-800">
                        <li class="text-sm text-gray-700 dark:text-gray-300">
                            Bulk Trx Out without Generated PDF
                            <span class="font-semibold text-danger-600 dark:text-danger-400">
                                ({{ $breakdown['trx_out']['bulk_without_pdf'] }})
                            </span>
                            <span class="text-xs text-gray-500 dark:text-gray-400">(We Generate)</span>
                        </li>
                        <li class="text-sm text-gray-700 dark:text-gray-300">
                            Card Trx without attachments
                            <span class="font-semibold text-danger-600 dark:text-danger-400">
                                ({{ $breakdown['trx_out']['card_without_attachment'] }})
                            </span>
                            <span class="text-xs text-gray-500 dark:text-gray-400">(We Show Bill)</span>
                        </li>
                        <li class="text-sm text-gray-700 dark:text-gray-300">
                            Single Bill Trx without attachments PDF
                            <span class="font-semibold text-danger-600 dark:text-danger-400">
                                ({{ $breakdown['trx_out']['single_bill_without_pdf'] }})
                            </span>
                            <span class="text-xs text-gray-500 dark:text-gray-400">(We Show Bill)</span>
                        </li>
                        <li class="text-sm text-gray-700 dark:text-gray-300">
                            Exp Trx without attachments PDF
                            <span class="font-semibold text-danger-600 dark:text-danger-400">
                                ({{ $breakdown['trx_out']['expense_without_attachment'] }})
                            </span>
                            <span class="text-xs text-gray-500 dark:text-gray-400">(We Show Bill)</span>
                        </li>
                    </ul>
                </div>
            </div>

            <p class="mt-4 text-xs text-gray-500 dark:text-gray-400">
                Counts reflect current table filters.
            </p>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
