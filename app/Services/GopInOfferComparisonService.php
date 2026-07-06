<?php

namespace App\Services;

use App\Models\Gop;
use App\Models\Invoice;
use App\Models\InvoiceItem;

class GopInOfferComparisonService
{
    public function __construct(
        private readonly GopInOfferService $offerService,
    ) {}

    /**
     * @return array{
     *     has_accepted: bool,
     *     accepted: ?array{provider: ?string, offered_cost: float, file_fee: float, total: float, status: string},
     *     actual: array{bill_total: float, file_fee: float, total: float},
     *     delta: array{offered_cost: float, file_fee: float, total: float},
     *     warnings: list<string>,
     *     severity: string
     * }
     */
    public function compare(Invoice $invoice): array
    {
        if (! $invoice->relationLoaded('items')) {
            $invoice->loadMissing('items');
        }

        if ($invoice->file && ! $invoice->file->relationLoaded('providerBranch')) {
            $invoice->file->loadMissing('providerBranch.provider');
        }

        $file = $invoice->file;

        $accepted = $file
            ? $this->offerService->acceptedOfferForFile($file)
            : null;

        $billTotal = round((float) $invoice->items
            ->where('item_type', InvoiceItem::TYPE_BILL)
            ->sum(fn (InvoiceItem $item) => (float) $item->amount), 2);

        $invoiceFileFee = round((float) $invoice->items
            ->where('item_type', InvoiceItem::TYPE_FILE_FEE)
            ->sum(fn (InvoiceItem $item) => (float) $item->amount), 2);

        $invoiceTotal = round((float) ($invoice->total_amount ?? 0), 2);

        $result = [
            'has_accepted' => $accepted !== null,
            'accepted' => null,
            'actual' => [
                'bill_total' => $billTotal,
                'file_fee' => $invoiceFileFee,
                'total' => $invoiceTotal,
            ],
            'delta' => [
                'offered_cost' => 0.0,
                'file_fee' => 0.0,
                'total' => 0.0,
            ],
            'warnings' => [],
            'severity' => 'none',
        ];

        if (! $accepted) {
            $result['warnings'][] = 'No accepted GOP In offer on this file.';

            return $result;
        }

        $acceptedCost = round((float) ($accepted->offered_cost ?? $accepted->amount), 2);
        $acceptedFee = round((float) ($accepted->file_fee ?? 0), 2);
        $acceptedTotal = round((float) $accepted->amount, 2);

        $result['accepted'] = [
            'provider' => $accepted->providerBranch?->branch_name,
            'offered_cost' => $acceptedCost,
            'file_fee' => $acceptedFee,
            'total' => $acceptedTotal,
            'status' => $accepted->status,
            'gop_id' => $accepted->id,
        ];

        $deltaCost = round($billTotal - $acceptedCost, 2);
        $deltaFee = round($invoiceFileFee - $acceptedFee, 2);
        $deltaTotal = round($invoiceTotal - $acceptedTotal, 2);

        $result['delta'] = [
            'offered_cost' => $deltaCost,
            'file_fee' => $deltaFee,
            'total' => $deltaTotal,
        ];

        if (
            $file?->provider_branch_id
            && $accepted->provider_branch_id
            && (int) $file->provider_branch_id !== (int) $accepted->provider_branch_id
        ) {
            $result['warnings'][] = 'Accepted offer provider does not match the file\'s confirmed provider.';
        }

        if ($deltaTotal === 0.0 && $deltaCost === 0.0 && $deltaFee === 0.0) {
            $result['severity'] = 'match';

            return $result;
        }

        $thresholdAmount = (float) config('invoice.internal_offer_variance.amount_eur', 25);
        $thresholdPercent = (float) config('invoice.internal_offer_variance.percent', 10);
        $percentDelta = $acceptedTotal > 0
            ? abs($deltaTotal / $acceptedTotal * 100)
            : ($deltaTotal !== 0.0 ? 100 : 0);

        $exceedsThreshold = abs($deltaTotal) > $thresholdAmount
            || $percentDelta > $thresholdPercent;

        if ($exceedsThreshold) {
            $result['severity'] = 'warning';
            $result['warnings'][] = sprintf(
                'Invoice differs from accepted offer by €%s (%s%%).',
                number_format(abs($deltaTotal), 2),
                number_format($percentDelta, 1),
            );
        } else {
            $result['severity'] = 'info';
            $result['warnings'][] = sprintf(
                'Invoice differs from accepted offer by €%s (%s%%).',
                number_format(abs($deltaTotal), 2),
                number_format($percentDelta, 1),
            );
        }

        return $result;
    }
}
