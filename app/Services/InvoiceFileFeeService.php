<?php

namespace App\Services;

use App\Models\Client;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\ServiceType;
use Illuminate\Support\Collection;

class InvoiceFileFeeService
{
    public const ITEM_TYPE_BILL = 'bill';

    public const ITEM_TYPE_FILE_FEE = 'file_fee';

    private static bool $syncing = false;

    /**
     * Sum invoice line items that represent bill charges (excludes auto file fees).
     */
    public function calculateBillItemsTotal(Invoice $invoice): float
    {
        $items = $this->billRelatedItems($invoice);

        return round((float) $items->sum(fn (InvoiceItem $item) => (float) $item->amount), 2);
    }

    /**
     * @return Collection<int, InvoiceItem>
     */
    public function billRelatedItems(Invoice $invoice): Collection
    {
        if ($invoice->relationLoaded('items')) {
            return $invoice->items
                ->filter(fn (InvoiceItem $item) => $item->item_type !== self::ITEM_TYPE_FILE_FEE)
                ->values();
        }

        return $invoice->items()
            ->where('item_type', '!=', self::ITEM_TYPE_FILE_FEE)
            ->get();
    }

    public function resolveClientForInvoice(Invoice $invoice): ?Client
    {
        $invoice->loadMissing('file.patient.client');

        return $invoice->file?->patient?->client;
    }

    /**
     * Determine the file fee tier from the bill-related items total.
     *
     * @return 'simple'|'middle'|'complex'|null
     */
    public function determineTier(float $billItemsTotal): ?string
    {
        if ($billItemsTotal <= 0) {
            return null;
        }

        $simpleMax = (float) config('invoice.file_fee_tiers.simple.max_total', 350);
        $middleMax = (float) config('invoice.file_fee_tiers.middle.max_total', 1000);

        if ($billItemsTotal < $simpleMax) {
            return 'simple';
        }

        if ($billItemsTotal < $middleMax) {
            return 'middle';
        }

        return 'complex';
    }

    public function calculateMultiplierUnits(float $billItemsTotal): int
    {
        if ($billItemsTotal <= 0) {
            return 0;
        }

        $cap = (float) config('invoice.multiplier_cap', 350);

        return max(1, (int) ceil($billItemsTotal / $cap));
    }

    /**
     * @return array{strategy: string, tier: ?string, units: ?int, bill_total: float, amount: float, description: string, service_type: string}|null
     */
    public function resolveForInvoice(Invoice $invoice, ?Client $client = null): ?array
    {
        $invoice->loadMissing(['file', 'items']);
        $client ??= $this->resolveClientForInvoice($invoice);

        $billTotal = $this->calculateBillItemsTotal($invoice);

        if ($billTotal <= 0) {
            return null;
        }

        if ($client?->usesMultiplierFileFeeStrategy()) {
            return $this->buildMultiplierPayload($invoice, $billTotal);
        }

        $tier = $this->determineTier($billTotal);

        if ($tier === null) {
            return null;
        }

        return $this->buildTierPayload($invoice, $tier, $billTotal);
    }

    /**
     * @return array{strategy: string, tier: ?string, units: ?int, bill_total: float, amount: float, description: string, service_type: string}|null
     */
    public function resolveFileFeeForInvoice(Invoice $invoice): ?array
    {
        return $this->resolveForInvoice($invoice);
    }

    /**
     * @return array{strategy: string, tier: string, units: null, bill_total: float, amount: float, description: string, service_type: string}|null
     */
    public function buildTierPayload(Invoice $invoice, string $tier, float $billTotal): ?array
    {
        $invoice->loadMissing(['file']);

        $serviceType = $this->findServiceTypeForTier($tier);
        if (! $serviceType) {
            return null;
        }

        $countryId = $invoice->file?->country_id ? (int) $invoice->file->country_id : null;
        $cityId = $invoice->file?->city_id ? (int) $invoice->file->city_id : null;

        $amount = TaxExportHelpers::resolveFileFeeAmountForInvoicePricing(
            (int) $serviceType->id,
            $countryId,
            $cityId,
        );

        if ($amount === null) {
            return null;
        }

        $serviceDate = ($invoice->file?->service_date ?? now())->format('d/m/Y');

        return [
            'strategy' => Client::FILE_FEE_STRATEGY_TIER,
            'tier' => $tier,
            'units' => null,
            'bill_total' => round($billTotal, 2),
            'amount' => $amount,
            'description' => "File Fee ({$serviceType->name}) on {$serviceDate}",
            'service_type' => $serviceType->name,
        ];
    }

    /**
     * @return array{strategy: string, tier: null, units: int, bill_total: float, amount: float, description: string, service_type: string}|null
     */
    public function buildMultiplierPayload(Invoice $invoice, float $billTotal): ?array
    {
        $invoice->loadMissing(['file']);

        $serviceType = $this->findServiceTypeForTier('simple');
        if (! $serviceType) {
            return null;
        }

        $units = $this->calculateMultiplierUnits($billTotal);
        if ($units <= 0) {
            return null;
        }

        $countryId = $invoice->file?->country_id ? (int) $invoice->file->country_id : null;
        $cityId = $invoice->file?->city_id ? (int) $invoice->file->city_id : null;

        $unitAmount = TaxExportHelpers::resolveFileFeeAmountForInvoicePricing(
            (int) $serviceType->id,
            $countryId,
            $cityId,
        );

        if ($unitAmount === null) {
            return null;
        }

        $serviceDate = ($invoice->file?->service_date ?? now())->format('d/m/Y');
        $feeLabel = $units > 1
            ? "File Fee ({$serviceType->name} × {$units})"
            : "File Fee ({$serviceType->name})";

        return [
            'strategy' => Client::FILE_FEE_STRATEGY_MULTIPLIER,
            'tier' => null,
            'units' => $units,
            'bill_total' => round($billTotal, 2),
            'amount' => round($unitAmount * $units, 2),
            'description' => "{$feeLabel} on {$serviceDate}",
            'service_type' => $serviceType->name,
        ];
    }

    /** @deprecated Use buildTierPayload() */
    public function buildFileFeePayload(Invoice $invoice, string $tier, float $billTotal): ?array
    {
        return $this->buildTierPayload($invoice, $tier, $billTotal);
    }

    /**
     * Create, update, or remove the auto-managed file fee line item for an invoice.
     */
    public function syncForInvoice(Invoice $invoice): ?InvoiceItem
    {
        if (self::$syncing || ! $invoice->exists) {
            return null;
        }

        self::$syncing = true;

        try {
            $resolved = $this->resolveForInvoice($invoice);
            $existing = $invoice->items()
                ->where('item_type', self::ITEM_TYPE_FILE_FEE)
                ->first();

            if ($resolved === null) {
                if ($existing) {
                    $existing->deleteQuietly();
                }

                return null;
            }

            $payload = [
                'description' => $resolved['description'],
                'amount' => $resolved['amount'],
                'discount' => 0,
                'tax' => 0,
                'item_type' => self::ITEM_TYPE_FILE_FEE,
            ];

            if ($existing) {
                $existing->fill($payload);

                if ($existing->isDirty()) {
                    $existing->saveQuietly();
                }

                return $existing->fresh();
            }

            return $invoice->items()->createQuietly($payload);
        } finally {
            self::$syncing = false;
        }
    }

    public function findServiceTypeForTier(string $tier): ?ServiceType
    {
        $configuredNames = config("invoice.file_fee_tiers.{$tier}.service_type_names", []);

        foreach ($configuredNames as $name) {
            $match = ServiceType::query()
                ->whereRaw('LOWER(name) = ?', [mb_strtolower(trim($name))])
                ->first();

            if ($match) {
                return $match;
            }
        }

        foreach ($configuredNames as $name) {
            $match = ServiceType::query()
                ->where('name', 'like', '%' . trim($name) . '%')
                ->first();

            if ($match) {
                return $match;
            }
        }

        return ServiceType::query()
            ->where('name', 'like', '%' . ucfirst($tier) . '%')
            ->first();
    }
}
