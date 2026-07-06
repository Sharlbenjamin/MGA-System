@php
    $comparison = null;

    try {
        $livewire = $getLivewire();
        $invoice = $livewire->record ?? null;
    } catch (\Exception $e) {
        $invoice = null;
    }

    if ($invoice) {
        $comparison = app(\App\Services\GopInOfferComparisonService::class)->compare($invoice);
    }

    $formatDelta = function (float $value): string {
        $prefix = $value > 0 ? '+' : ($value < 0 ? '' : '');
        return $prefix . '€' . number_format($value, 2);
    };

    $severityStyles = [
        'match' => 'border-green-200 bg-green-50 dark:border-green-900/40 dark:bg-green-900/20',
        'info' => 'border-blue-200 bg-blue-50 dark:border-blue-900/40 dark:bg-blue-900/20',
        'warning' => 'border-amber-200 bg-amber-50 dark:border-amber-900/40 dark:bg-amber-900/20',
        'none' => 'border-gray-200 bg-gray-50 dark:border-white/10 dark:bg-white/5',
    ];
@endphp

@if ($comparison)
    <div class="rounded-lg border p-3 text-sm {{ $severityStyles[$comparison['severity']] ?? $severityStyles['none'] }}">
        <div class="mb-2 font-semibold text-gray-900 dark:text-white">Accepted offer vs invoice</div>

        @if (! $comparison['has_accepted'])
            <p class="text-gray-600 dark:text-gray-300">No accepted GOP In offer on this file.</p>
        @else
            @php $accepted = $comparison['accepted']; @endphp
            <p class="mb-2 text-xs text-gray-600 dark:text-gray-400">
                Provider: <span class="font-medium">{{ $accepted['provider'] ?? '—' }}</span>
            </p>

            <table class="w-full text-xs">
                <thead>
                    <tr class="text-left text-gray-500 dark:text-gray-400">
                        <th class="pb-1 pr-2"></th>
                        <th class="pb-1 pr-2">Cost</th>
                        <th class="pb-1 pr-2">Fee</th>
                        <th class="pb-1">Total</th>
                    </tr>
                </thead>
                <tbody class="text-gray-900 dark:text-gray-100">
                    <tr>
                        <td class="py-1 pr-2 font-medium">Accepted</td>
                        <td class="py-1 pr-2">€{{ number_format($accepted['offered_cost'], 2) }}</td>
                        <td class="py-1 pr-2">€{{ number_format($accepted['file_fee'], 2) }}</td>
                        <td class="py-1">€{{ number_format($accepted['total'], 2) }}</td>
                    </tr>
                    <tr>
                        <td class="py-1 pr-2 font-medium">Invoice</td>
                        <td class="py-1 pr-2">€{{ number_format($comparison['actual']['bill_total'], 2) }}</td>
                        <td class="py-1 pr-2">€{{ number_format($comparison['actual']['file_fee'], 2) }}</td>
                        <td class="py-1">€{{ number_format($comparison['actual']['total'], 2) }}</td>
                    </tr>
                    <tr class="border-t border-gray-200 dark:border-white/10">
                        <td class="py-1 pr-2 font-medium">Difference</td>
                        <td class="py-1 pr-2">{{ $formatDelta($comparison['delta']['offered_cost']) }}</td>
                        <td class="py-1 pr-2">{{ $formatDelta($comparison['delta']['file_fee']) }}</td>
                        <td class="py-1 font-semibold">{{ $formatDelta($comparison['delta']['total']) }}</td>
                    </tr>
                </tbody>
            </table>
        @endif

        @if ($comparison['warnings'] !== [])
            <ul class="mt-2 space-y-1 text-xs text-gray-700 dark:text-gray-300">
                @foreach ($comparison['warnings'] as $warning)
                    <li>• {{ $warning }}</li>
                @endforeach
            </ul>
        @endif
    </div>
@endif
