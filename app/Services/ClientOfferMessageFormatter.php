<?php

namespace App\Services;

use App\Models\File;
use App\Models\Gop;
use App\Models\GopItem;
use App\Models\ProviderBranch;

class ClientOfferMessageFormatter
{
    public const MODE_OFFER = 'selling';

    public const MODE_REQUEST = 'cost';

    public function __construct(
        protected OfferPricingCalculator $calculator,
    ) {}

    /**
     * @return array<string, string>
     */
    public static function sectionOptions(): array
    {
        return config('offer.offer_sections', []);
    }

    /**
     * @return list<string>
     */
    public static function defaultSections(): array
    {
        return config('offer.default_offer_sections', array_keys(self::sectionOptions()));
    }

    /**
     * @param  list<string>|null  $sections
     */
    public function formatOffer(File $file, Gop $gop, ?array $sections = null): string
    {
        return $this->format($file, $gop, self::MODE_OFFER, $sections);
    }

    /**
     * Copy of the GOP / appointment request: items at cost plus file fee.
     *
     * @param  list<string>|null  $sections
     */
    public function formatRequest(File $file, Gop $gop, ?array $sections = null): string
    {
        return $this->format($file, $gop, self::MODE_REQUEST, $sections);
    }

    /**
     * @param  list<string>|null  $sections
     */
    public function format(File $file, Gop $gop, string $mode = self::MODE_OFFER, ?array $sections = null): string
    {
        $file->loadMissing(['patient', 'country', 'city', 'serviceType', 'providerBranch']);
        $gop->loadMissing(['items', 'providerBranch', 'serviceType']);

        $sections = $this->normalizeSections($sections ?? $gop->offer_sections);
        $branch = $gop->providerBranch ?: $file->providerBranch;
        $kind = $this->calculator->classifyService(
            $gop->service_type_id ? (int) $gop->service_type_id : $file->service_type_id,
            $gop->effective_service_type_name ?: $file->serviceType?->name,
        );

        $lines = [];

        if (in_array('mga_reference', $sections, true) && filled($file->mga_reference)) {
            $lines[] = "File: {$file->mga_reference}";
        }

        if (in_array('patient_name', $sections, true) && filled($file->patient?->name)) {
            $lines[] = 'Patient: '.$file->patient->name;
        }

        if (in_array('address', $sections, true) && filled($file->address)) {
            $lines[] = "Address: {$file->address}";
        }

        if (in_array('provider', $sections, true) && $branch) {
            $lines[] = 'Provider: '.($branch->branch_name ?? $branch->name ?? 'N/A');
        }

        if (in_array('provider_address', $sections, true) && filled($branch?->address)) {
            $lines[] = 'Provider address: '.$branch->address;
        }

        if (in_array('service_type', $sections, true)) {
            $serviceLabel = $gop->effective_service_type_name ?: ($file->serviceType?->name ?? 'medical service');

            if (filled($serviceLabel)) {
                $lines[] = "Service: {$serviceLabel}";
            }
        }

        if (in_array('date_time', $sections, true)) {
            $lines[] = 'Date & Time: '.$this->formatDateTime($file);
        }

        if (in_array('items', $sections, true)) {
            if ($lines !== []) {
                $lines[] = '';
            }

            foreach ($this->itemLines($file, $gop, $mode, $kind) as $itemLine) {
                $lines[] = $itemLine;
            }
        }

        if (in_array('total', $sections, true)) {
            $totals = $this->totalsForMode($gop, $mode, $kind);
            $lines[] = 'Total: '.$this->money($totals['total']);
        }

        return trim(implode("\n", $lines));
    }

    /**
     * @param  list<string>|array<int|string, mixed>|null  $sections
     * @return list<string>
     */
    public function normalizeSections(array|null $sections): array
    {
        $allowed = array_keys(self::sectionOptions());

        if ($sections === null || $sections === []) {
            return self::defaultSections();
        }

        $selected = [];

        foreach ($sections as $key => $value) {
            if (is_string($key) && ! is_numeric($key) && $value) {
                $selected[] = $key;

                continue;
            }

            if (is_string($value) && $value !== '') {
                $selected[] = $value;
            }
        }

        $selected = array_values(array_intersect($allowed, $selected));

        return $selected !== [] ? $selected : self::defaultSections();
    }

    /**
     * @return list<string>
     */
    protected function itemLines(File $file, Gop $gop, string $mode, string $kind): array
    {
        if ($kind === OfferPricingCalculator::SERVICE_HOUSE_VISIT) {
            $amount = $mode === self::MODE_OFFER
                ? (float) ($gop->offered_cost ?? $gop->amount ?? 0)
                : $this->serviceItemsCost($gop);

            if ($mode === self::MODE_OFFER && $amount <= 0) {
                $amount = $this->serviceItemsSelling($gop);
            }

            if ($amount <= 0 && $mode === self::MODE_REQUEST) {
                $amount = (float) ($gop->offered_cost ?? 0) / max(1, (float) config('offer.selling_cost_multiplier', 2));
            }

            $label = (string) config('offer.house_visit.merged_label', 'Cost & GOP');

            return ["{$label}: ".$this->money($amount)];
        }

        $lines = [];
        $items = $this->serviceItems($gop);

        if ($items->isEmpty() && (float) ($gop->offered_cost ?? 0) > 0) {
            $label = $gop->effective_service_type_name ?: ($file->serviceType?->name ?: 'Service');
            $amount = $mode === self::MODE_OFFER
                ? (float) $gop->offered_cost
                : (float) $gop->offered_cost / max(1, (float) config('offer.selling_cost_multiplier', 2));

            $lines[] = "{$label}: ".$this->money($amount);
        } else {
            foreach ($items as $item) {
                $amount = $mode === self::MODE_OFFER
                    ? (float) $item->selling_cost
                    : (float) $item->cost;

                $lines[] = trim($item->description).': '.$this->money($amount);
            }
        }

        $fileFee = round((float) ($gop->file_fee ?? 0), 2);

        if ($fileFee <= 0 && $kind !== OfferPricingCalculator::SERVICE_TELEMEDICINE) {
            $feeItem = $gop->relationLoaded('items')
                ? $gop->items->first(fn (GopItem $item): bool => $item->isFileFeeItem())
                : null;

            if ($feeItem) {
                $fileFee = round((float) $feeItem->selling_cost ?: (float) $feeItem->cost, 2);
            }
        }

        if ($fileFee > 0) {
            $lines[] = 'File fee: '.$this->money($fileFee);
        }

        return $lines;
    }

    /**
     * @return array{total: float}
     */
    protected function totalsForMode(Gop $gop, string $mode, string $kind): array
    {
        if ($kind === OfferPricingCalculator::SERVICE_HOUSE_VISIT) {
            $amount = $mode === self::MODE_OFFER
                ? (float) ($gop->offered_cost ?? $gop->amount ?? 0)
                : $this->serviceItemsCost($gop);

            return ['total' => round($amount, 2)];
        }

        if ($mode === self::MODE_OFFER) {
            return ['total' => round((float) ($gop->amount ?? 0), 2)];
        }

        $cost = $this->serviceItemsCost($gop);
        $fileFee = round((float) ($gop->file_fee ?? 0), 2);

        if ($cost <= 0) {
            $cost = round((float) ($gop->offered_cost ?? 0), 2);
        }

        return ['total' => round($cost + $fileFee, 2)];
    }

    /**
     * @return \Illuminate\Support\Collection<int, GopItem>
     */
    protected function serviceItems(Gop $gop)
    {
        $items = $gop->relationLoaded('items') ? $gop->items : $gop->items()->get();

        return $items
            ->filter(fn (GopItem $item): bool => $item->isServiceItem())
            ->values();
    }

    protected function serviceItemsCost(Gop $gop): float
    {
        return round((float) $this->serviceItems($gop)->sum(fn (GopItem $item) => (float) $item->cost), 2);
    }

    protected function serviceItemsSelling(Gop $gop): float
    {
        return round((float) $this->serviceItems($gop)->sum(fn (GopItem $item) => (float) $item->selling_cost), 2);
    }

    protected function formatDateTime(File $file): string
    {
        if (! $file->service_date) {
            return 'N/A';
        }

        $parts = [$file->service_date->format('d/m/Y')];

        if ($file->service_time) {
            $parts[] = \Carbon\Carbon::parse($file->service_time)->format('H:i');
        }

        return implode(' at ', $parts);
    }

    protected function money(float $amount): string
    {
        $formatted = abs($amount - round($amount)) < 0.001
            ? number_format($amount, 0)
            : number_format($amount, 2);

        return $formatted.'€';
    }
}
