<?php

namespace App\Support;

use App\Models\Bill;

class BillExtractPdf
{
    /**
     * @return array{
     *     providerName: string,
     *     addressLines: list<string>,
     *     date: string,
     *     billName: ?string,
     *     items: \Illuminate\Support\Collection,
     *     subtotal: float,
     *     discount: float,
     *     total: float
     * }
     */
    public static function data(Bill $bill): array
    {
        $bill->loadMissing([
            'file',
            'provider.country',
            'branch.city',
            'branch.province',
            'items',
        ]);

        return [
            'providerName' => (string) ($bill->provider?->name ?? ''),
            'addressLines' => static::addressLines($bill),
            'date' => $bill->bill_date?->format('d/m/Y') ?? '',
            'billName' => $bill->hasCustomName() ? (string) $bill->name : null,
            'items' => $bill->items,
            'subtotal' => (float) $bill->subtotal,
            'discount' => (float) $bill->discount,
            'total' => (float) $bill->total_amount,
        ];
    }

    public static function filename(Bill $bill): string
    {
        $bill->loadMissing('file');

        $base = $bill->hasCustomName() ? (string) $bill->name : $bill->generatedName();
        $safe = trim((string) preg_replace('/[\\\\\\/:*?"<>|]+/', '-', $base));

        return ($safe !== '' ? $safe : 'bill').'.pdf';
    }

    /**
     * @return list<string>
     */
    public static function addressLines(Bill $bill): array
    {
        $branch = $bill->branch;
        $lines = [];

        if (filled($branch?->address)) {
            $lines[] = (string) $branch->address;
        }

        $locality = collect([$branch?->city?->name, $branch?->province?->name])
            ->filter(fn ($part) => filled($part))
            ->implode(', ');

        if ($locality !== '') {
            $lines[] = $locality;
        }

        if (filled($bill->provider?->country?->name)) {
            $lines[] = (string) $bill->provider->country->name;
        }

        return $lines;
    }
}
