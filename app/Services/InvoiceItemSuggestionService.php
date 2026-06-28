<?php

namespace App\Services;

use App\Models\FileFee;
use App\Models\Invoice;
use App\Models\InvoiceItem;

class InvoiceItemSuggestionService
{
    public function __construct(
        private readonly InvoiceFileFeeService $fileFeeService,
    ) {}

    /**
     * Build suggested invoice items for a given invoice.
     *
     * @return array<int, array{description: string, amount: float, source: string, item_type?: string}>
     */
    public function suggestForInvoice(Invoice $invoice): array
    {
        $invoice->loadMissing([
            'file.country',
            'file.city',
            'file.serviceType',
            'file.bills.items',
            'items',
        ]);

        $file = $invoice->file;
        if (!$file) {
            return [];
        }

        $serviceDate = ($file->service_date ?? now())->format('d/m/Y');
        $suggestions = [];
        $billItemsTotal = 0.0;

        // 1) Directly from this file's bill items (highest confidence).
        foreach ($file->bills as $bill) {
            foreach ($bill->items as $billItem) {
                $description = trim((string) $billItem->description);
                if ($description === '') {
                    continue;
                }

                $amount = (float) $billItem->amount;
                $billItemsTotal += $amount;

                $description = $this->appendServiceDate($description, $serviceDate);
                $this->pushUniqueSuggestion($suggestions, [
                    'description' => $description,
                    'amount' => $amount,
                    'source' => 'This file bill',
                    'item_type' => InvoiceFileFeeService::ITEM_TYPE_BILL,
                ]);
            }
        }

        // Include bill-related invoice items already on this invoice.
        foreach ($invoice->items as $existingItem) {
            if ($existingItem->item_type === InvoiceFileFeeService::ITEM_TYPE_FILE_FEE) {
                continue;
            }

            $billItemsTotal += (float) $existingItem->amount;
        }

        // 2) Auto file fee from client strategy (tier or multiplier).
        if ($billItemsTotal > 0) {
            $resolved = $this->fileFeeService->resolveForInvoice($invoice);
            if ($resolved) {
                $sourceLabel = $resolved['strategy'] === \App\Models\Client::FILE_FEE_STRATEGY_MULTIPLIER
                    ? 'Auto file fee (Multiplier × ' . $resolved['units'] . ', bill total €' . number_format($resolved['bill_total'], 2) . ')'
                    : 'Auto file fee (' . ucfirst((string) $resolved['tier']) . ', bill total €' . number_format($resolved['bill_total'], 2) . ')';

                $this->pushUniqueSuggestion($suggestions, [
                    'description' => $resolved['description'],
                    'amount' => $resolved['amount'],
                    'source' => $sourceLabel,
                    'item_type' => InvoiceFileFeeService::ITEM_TYPE_FILE_FEE,
                ]);
            }
        }

        // 3) From configured file fees by service type + country (excluding tier fee types).
        if ($file->service_type_id && $file->country_id) {
            $tierServiceTypeIds = $this->tierServiceTypeIds();

            $fileFeesQuery = FileFee::query()
                ->with(['serviceType', 'country', 'city'])
                ->where('service_type_id', $file->service_type_id)
                ->where('country_id', $file->country_id)
                ->where(function ($query) use ($file) {
                    $query->whereNull('city_id');
                    if ($file->city_id) {
                        $query->orWhere('city_id', $file->city_id);
                    }
                })
                ->when($tierServiceTypeIds !== [], fn ($query) => $query->whereNotIn('service_type_id', $tierServiceTypeIds))
                ->orderByRaw('city_id IS NULL')
                ->limit(5);

            foreach ($fileFeesQuery->get() as $fileFee) {
                $serviceName = $fileFee->serviceType?->name ?: 'Service';
                $description = $this->appendServiceDate($serviceName, $serviceDate);

                $this->pushUniqueSuggestion($suggestions, [
                    'description' => $description,
                    'amount' => (float) $fileFee->amount,
                    'source' => 'File fee setup',
                    'item_type' => InvoiceFileFeeService::ITEM_TYPE_BILL,
                ]);
            }
        }

        // 4) From historical invoice items for same country + service type.
        if ($file->service_type_id && $file->country_id) {
            $historicalRows = InvoiceItem::query()
                ->select(['invoice_items.description', 'invoice_items.amount'])
                ->join('invoices', 'invoice_items.invoice_id', '=', 'invoices.id')
                ->join('files', 'invoices.file_id', '=', 'files.id')
                ->where('files.service_type_id', $file->service_type_id)
                ->where('files.country_id', $file->country_id)
                ->whereNotNull('invoice_items.description')
                ->where('invoice_items.description', '!=', '')
                ->where('invoices.id', '!=', $invoice->id)
                ->orderByDesc('invoices.created_at')
                ->limit(150)
                ->get();

            $grouped = [];
            foreach ($historicalRows as $row) {
                $normalized = $this->normalizeDescription((string) $row->description);
                if ($normalized === '') {
                    continue;
                }

                if (!isset($grouped[$normalized])) {
                    $grouped[$normalized] = ['sum' => 0.0, 'count' => 0];
                }

                $grouped[$normalized]['sum'] += (float) $row->amount;
                $grouped[$normalized]['count']++;
            }

            uasort($grouped, function (array $a, array $b): int {
                return $b['count'] <=> $a['count'];
            });

            $added = 0;
            foreach ($grouped as $normalizedDescription => $stats) {
                if ($added >= 5) {
                    break;
                }

                $avgAmount = $stats['count'] > 0
                    ? round($stats['sum'] / $stats['count'], 2)
                    : 0.0;

                $description = $this->appendServiceDate($normalizedDescription, $serviceDate);

                $this->pushUniqueSuggestion($suggestions, [
                    'description' => $description,
                    'amount' => $avgAmount,
                    'source' => 'Historical pattern',
                    'item_type' => InvoiceFileFeeService::ITEM_TYPE_BILL,
                ]);

                $added++;
            }
        }

        return array_slice($suggestions, 0, 10);
    }

    /**
     * @return list<int>
     */
    private function tierServiceTypeIds(): array
    {
        $ids = [];

        foreach (['simple', 'middle', 'complex'] as $tier) {
            $serviceType = $this->fileFeeService->findServiceTypeForTier($tier);
            if ($serviceType) {
                $ids[] = (int) $serviceType->id;
            }
        }

        return array_values(array_unique($ids));
    }

    private function normalizeDescription(string $description): string
    {
        $description = trim($description);
        // Remove trailing "on dd/mm/yyyy" to better detect reusable patterns.
        $description = preg_replace('/\s+on\s+\d{2}\/\d{2}\/\d{4}$/i', '', $description) ?? $description;

        return trim($description);
    }

    private function appendServiceDate(string $description, string $serviceDate): string
    {
        if (preg_match('/\b\d{2}\/\d{2}\/\d{4}\b/', $description)) {
            return $description;
        }

        return trim($description) . " on {$serviceDate}";
    }

    /**
     * @param array<int, array{description: string, amount: float, source: string}> $suggestions
     * @param array{description: string, amount: float, source: string} $candidate
     */
    private function pushUniqueSuggestion(array &$suggestions, array $candidate): void
    {
        $key = mb_strtolower(trim($candidate['description']));
        foreach ($suggestions as $existing) {
            if (mb_strtolower(trim($existing['description'])) === $key) {
                return;
            }
        }

        $suggestions[] = $candidate;
    }
}

