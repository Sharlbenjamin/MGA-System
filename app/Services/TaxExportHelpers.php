<?php

namespace App\Services;

use App\Models\Bill;
use App\Models\Client;
use App\Models\FileFee;
use App\Models\Invoice;
use Carbon\Carbon;

class TaxExportHelpers
{
    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    public static function resolvePeriodDates(int $year, string $quarter): array
    {
        if ($quarter !== 'full') {
            $startMonth = ((int) $quarter - 1) * 3 + 1;
            $endMonth = $startMonth + 2;

            return [
                Carbon::create($year, $startMonth, 1)->startOfMonth(),
                Carbon::create($year, $endMonth, 1)->endOfMonth(),
            ];
        }

        return [
            Carbon::create($year, 1, 1)->startOfYear(),
            Carbon::create($year, 12, 31)->endOfYear(),
        ];
    }

    public static function resolveNifValue(Invoice $invoice, string $nifSource): string
    {
        $clientCountry = self::resolveClientCountryFromInvoice($invoice);
        $clientNiv = $invoice->patient?->client?->niv_number ?: '-';

        if (self::isEuropeanCountry($clientCountry)) {
            return $clientNiv;
        }

        return $clientCountry ?: '-';
    }

    public static function resolveNifFromFile($file, string $nifSource): string
    {
        $clientCountry = self::resolveClientCountryFromFile($file);
        $clientNiv = $file?->patient?->client?->niv_number ?: '-';

        if (self::isEuropeanCountry($clientCountry)) {
            return $clientNiv;
        }

        return $clientCountry ?: '-';
    }

    public static function resolveClientCountryFromInvoice(Invoice $invoice): string
    {
        return self::resolveClientCountryFromClient($invoice->patient?->client);
    }

    public static function resolveClientCountryFromFile($file): string
    {
        return self::resolveClientCountryFromClient($file?->patient?->client);
    }

    public static function resolveClientCountryFromClient(?Client $client): string
    {
        if (! $client) {
            return '-';
        }

        return $client->financialContact?->country?->name
            ?? $client->operationContact?->country?->name
            ?? $client->gopContact?->country?->name
            ?? $client->country?->name
            ?? '-';
    }

    public static function isEuropeanCountry(?string $countryName): bool
    {
        if (! $countryName) {
            return false;
        }

        $euCountries = [
            'austria', 'belgium', 'bulgaria', 'croatia', 'cyprus', 'czech republic',
            'denmark', 'estonia', 'finland', 'france', 'germany', 'greece', 'hungary',
            'ireland', 'italy', 'latvia', 'lithuania', 'luxembourg', 'malta',
            'netherlands', 'poland', 'portugal', 'romania', 'slovakia', 'slovenia',
            'spain', 'sweden',
        ];

        return in_array(mb_strtolower(trim($countryName)), $euCountries, true);
    }

    public static function resolveFileFeeAmountForFile($file): ?float
    {
        if (! $file || ! $file->service_type_id) {
            return null;
        }

        $serviceTypeId = (int) $file->service_type_id;
        $countryId = $file->country_id ? (int) $file->country_id : null;
        $cityId = $file->city_id ? (int) $file->city_id : null;

        static $cache = [];
        $cacheKey = implode(':', [$serviceTypeId, $countryId ?? 'null', $cityId ?? 'null']);

        if (array_key_exists($cacheKey, $cache)) {
            return $cache[$cacheKey];
        }

        if ($countryId && $cityId) {
            $exact = FileFee::query()
                ->where('service_type_id', $serviceTypeId)
                ->where('country_id', $countryId)
                ->where('city_id', $cityId)
                ->first();
            if ($exact) {
                return $cache[$cacheKey] = (float) $exact->amount;
            }
        }

        if ($countryId) {
            $countryDefault = FileFee::query()
                ->where('service_type_id', $serviceTypeId)
                ->where('country_id', $countryId)
                ->whereNull('city_id')
                ->first();
            if ($countryDefault) {
                return $cache[$cacheKey] = (float) $countryDefault->amount;
            }
        }

        $globalDefault = FileFee::query()
            ->where('service_type_id', $serviceTypeId)
            ->whereNull('country_id')
            ->whereNull('city_id')
            ->first();

        $cache[$cacheKey] = $globalDefault ? (float) $globalDefault->amount : null;

        return $cache[$cacheKey];
    }

    public static function resolveAmountBeforeIva(float $fileFeeAmount, float $ivaRate): float
    {
        if ($ivaRate <= 0) {
            return $fileFeeAmount;
        }

        return $fileFeeAmount / (1 + $ivaRate);
    }

    public static function resolveBillAmount(Bill $bill): float
    {
        if ($bill->total_amount !== null) {
            return (float) $bill->total_amount;
        }

        if ($bill->relationLoaded('items')) {
            return (float) $bill->items->sum('amount');
        }

        return (float) $bill->items()->sum('amount');
    }

    public static function resolveTransactionAmount(object $transaction): float
    {
        if (isset($transaction->amount) && $transaction->amount !== null) {
            return (float) $transaction->amount;
        }

        return 0.0;
    }
}
