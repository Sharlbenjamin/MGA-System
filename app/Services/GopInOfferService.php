<?php

namespace App\Services;

use App\Models\File;
use App\Models\FileFee;
use App\Models\Gop;
use App\Models\GopItem;
use App\Models\ProviderBranch;
use Illuminate\Support\Facades\DB;

class GopInOfferService
{
    public function __construct(
        protected OfferPricingCalculator $pricing = new OfferPricingCalculator,
    ) {}

    /**
     * @return array{offered_cost: ?float, file_fee: ?float, total: ?float, items: list<array<string, mixed>>}
     */
    public function suggestCostsForBranch(File $file, ProviderBranch $branch, ?int $serviceTypeId = null): array
    {
        $serviceTypeId = $serviceTypeId ?? ($file->service_type_id ? (int) $file->service_type_id : null);
        $items = $this->pricing->buildSuggestedItems($file, $branch, $serviceTypeId);
        $totals = $this->pricing->totals($items);

        if ($items === []) {
            return [
                'offered_cost' => null,
                'file_fee' => null,
                'total' => null,
                'items' => [],
            ];
        }

        return [
            'offered_cost' => $totals['offered_cost'],
            'file_fee' => $totals['file_fee'],
            'total' => $totals['total'],
            'items' => $items,
        ];
    }

    public function createOffer(
        File $file,
        int $providerBranchId,
        float $offeredCost,
        float $fileFee = 0,
        string $status = Gop::IN_STATUS_DRAFT,
        ?string $notes = null,
        ?int $serviceTypeId = null,
        ?string $serviceTypeOther = null,
        array $items = [],
        ?array $offerSections = null,
    ): Gop {
        return DB::transaction(function () use (
            $file,
            $providerBranchId,
            $offeredCost,
            $fileFee,
            $status,
            $notes,
            $serviceTypeId,
            $serviceTypeOther,
            $items,
            $offerSections,
        ) {
            if ($status === Gop::IN_STATUS_ACCEPTED) {
                $this->clearAcceptedOffers($file);
            }

            $gop = $file->gops()->create([
                'type' => 'In',
                'provider_branch_id' => $providerBranchId,
                'service_type_id' => $serviceTypeId,
                'service_type_other' => filled($serviceTypeOther) ? trim($serviceTypeOther) : null,
                'offered_cost' => round($offeredCost, 2),
                'file_fee' => round($fileFee, 2),
                'amount' => round($offeredCost + $fileFee, 2),
                'status' => $status,
                'date' => now()->toDateString(),
                'notes' => $notes,
                'offer_sections' => $offerSections,
            ]);

            if ($items !== []) {
                $this->syncItems($gop, $items, $fileFee);
            }

            return $gop->fresh(['items', 'providerBranch.provider']);
        });
    }

    /**
     * Persist GOP In form data (including draft-bill items) and keep totals in sync.
     *
     * @param  array<string, mixed>  $data
     */
    public function saveInOffer(File $file, array $data, ?Gop $existing = null): Gop
    {
        return DB::transaction(function () use ($file, $data, $existing) {
            $items = array_values($data['items'] ?? []);
            unset($data['items']);

            $data['type'] = 'In';
            $data['file_id'] = $file->getKey();

            if (($data['status'] ?? null) === Gop::IN_STATUS_ACCEPTED) {
                $this->clearAcceptedOffers($file);
            }

            $fileFee = array_key_exists('file_fee', $data)
                ? round((float) ($data['file_fee'] ?? 0), 2)
                : null;

            if ($existing) {
                $existing->fill($data);
                $existing->save();
                $gop = $existing;
            } else {
                $gop = $file->gops()->create($data);
            }

            return $this->syncItems($gop, $items, $fileFee);
        });
    }

    /**
     * @param  list<array<string, mixed>>  $items
     */
    public function syncItems(Gop $gop, array $items, ?float $fileFeeOverride = null): Gop
    {
        $gop->loadMissing('file.serviceType');
        $file = $gop->file;
        $serviceTypeId = $gop->service_type_id ? (int) $gop->service_type_id : $file?->service_type_id;
        $serviceTypeName = $gop->effective_service_type_name ?: $file?->serviceType?->name;

        $normalized = [];

        if ($file) {
            $normalized = $this->pricing->withFileFeeItem(
                $items,
                $file,
                $serviceTypeId ? (int) $serviceTypeId : null,
                $serviceTypeName,
            );

            if ($fileFeeOverride !== null) {
                $normalized = array_values(array_filter(
                    $normalized,
                    fn (array $item): bool => ($item['item_type'] ?? '') !== GopItem::TYPE_FILE_FEE,
                ));

                if ($fileFeeOverride > 0) {
                    $normalized[] = [
                        'description' => 'File fee',
                        'cost' => 0.0,
                        'selling_cost' => $fileFeeOverride,
                        'item_type' => GopItem::TYPE_FILE_FEE,
                        'sort_order' => count($normalized),
                    ];
                }
            }
        } else {
            foreach (array_values($items) as $index => $item) {
                $normalized[] = [
                    'description' => trim((string) ($item['description'] ?? '')) ?: 'Item',
                    'cost' => round((float) ($item['cost'] ?? 0), 2),
                    'selling_cost' => round((float) ($item['selling_cost'] ?? 0), 2),
                    'item_type' => $item['item_type'] ?? GopItem::TYPE_SERVICE,
                    'sort_order' => (int) ($item['sort_order'] ?? $index),
                ];
            }
        }

        $gop->items()->delete();

        foreach ($normalized as $index => $item) {
            $gop->items()->create([
                'description' => $item['description'],
                'cost' => $item['cost'],
                'selling_cost' => $item['selling_cost'],
                'item_type' => $item['item_type'],
                'sort_order' => $item['sort_order'] ?? $index,
            ]);
        }

        $totals = $this->pricing->totals($normalized);
        $gop->offered_cost = $totals['offered_cost'];
        $gop->file_fee = $fileFeeOverride !== null ? $fileFeeOverride : $totals['file_fee'];
        $gop->amount = round((float) $gop->offered_cost + (float) $gop->file_fee, 2);
        $gop->save();

        return $gop->fresh(['items', 'providerBranch.provider', 'serviceType']);
    }

    public function markOffered(Gop $gop): Gop
    {
        $this->assertInOffer($gop);

        $gop->update(['status' => Gop::IN_STATUS_OFFERED]);

        return $gop->fresh();
    }

    public function markAccepted(Gop $gop): Gop
    {
        $this->assertInOffer($gop);

        return DB::transaction(function () use ($gop) {
            $this->clearAcceptedOffers($gop->file);

            $gop->update(['status' => Gop::IN_STATUS_ACCEPTED]);

            return $gop->fresh(['providerBranch.provider']);
        });
    }

    public function markRejected(Gop $gop): Gop
    {
        $this->assertInOffer($gop);

        $gop->update(['status' => Gop::IN_STATUS_REJECTED]);

        return $gop->fresh();
    }

    public function latestOfferForBranch(File $file, int $providerBranchId): ?Gop
    {
        return $file->gops()
            ->where('type', 'In')
            ->where('provider_branch_id', $providerBranchId)
            ->latest('id')
            ->first();
    }

    public function acceptedOfferForFile(File $file): ?Gop
    {
        return $file->gops()
            ->where('type', 'In')
            ->where('status', Gop::IN_STATUS_ACCEPTED)
            ->with(['providerBranch.provider', 'serviceType'])
            ->latest('id')
            ->first();
    }

    public function resolveGopInForAppointmentMessage(File $file, ProviderBranch $branch): ?Gop
    {
        $branchId = (int) $branch->id;

        $acceptedForBranch = $file->gops()
            ->where('type', 'In')
            ->where('provider_branch_id', $branchId)
            ->where('status', Gop::IN_STATUS_ACCEPTED)
            ->latest('id')
            ->first();

        if ($acceptedForBranch) {
            return $acceptedForBranch;
        }

        $latestForBranch = $file->gops()
            ->where('type', 'In')
            ->where('provider_branch_id', $branchId)
            ->where(function ($query) {
                $query->where(function ($inner) {
                    $inner->whereNotNull('offered_cost')
                        ->where('offered_cost', '>', 0);
                })->orWhere(function ($inner) {
                    $inner->whereNotNull('amount')
                        ->where('amount', '>', 0);
                });
            })
            ->latest('id')
            ->first();

        if ($latestForBranch) {
            return $latestForBranch;
        }

        $acceptedForFile = $this->acceptedOfferForFile($file);

        if ($acceptedForFile && (int) ($acceptedForFile->provider_branch_id ?? 0) === $branchId) {
            return $acceptedForFile;
        }

        return null;
    }

    /**
     * @return array{0: float, 1: float}
     */
    public function resolveGopInCostAndTotal(Gop $gop): array
    {
        $fileFee = round((float) ($gop->file_fee ?? 0), 2);

        if ($gop->offered_cost !== null && (float) $gop->offered_cost > 0) {
            $cost = round((float) $gop->offered_cost, 2);

            return [$cost, round($cost + $fileFee, 2)];
        }

        if ($gop->amount !== null && (float) $gop->amount > 0) {
            $amount = round((float) $gop->amount, 2);

            if ($fileFee > 0 && $amount > $fileFee) {
                return [round($amount - $fileFee, 2), $amount];
            }

            return [$amount, round($amount + $fileFee, 2)];
        }

        return [0.0, 0.0];
    }

    /**
     * GOP In dominates; branch service pricing is the fallback.
     *
     * @return array{0: ?float, 1: ?float}
     */
    public function resolveCostAndTotalForBranch(File $file, ProviderBranch $branch, ?Gop $gopIn = null): array
    {
        $gopIn ??= $this->resolveGopInForAppointmentMessage($file, $branch);

        if ($gopIn) {
            [$cost, $total] = $this->resolveGopInCostAndTotal($gopIn);

            if ($cost > 0) {
                return [$cost, $total];
            }
        }

        $suggested = $this->suggestCostsForBranch($file, $branch);

        if ($suggested['offered_cost'] !== null && (float) $suggested['offered_cost'] > 0) {
            return [
                (float) $suggested['offered_cost'],
                (float) ($suggested['total'] ?? $suggested['offered_cost']),
            ];
        }

        return [null, null];
    }

    /**
     * @return array{offered_cost: ?float, file_fee: ?float, total: ?float, items: list<array<string, mixed>>}
     */
    public function suggestCostsForGopForm(File $file, ?int $providerBranchId, ?int $serviceTypeId = null): array
    {
        $empty = [
            'offered_cost' => null,
            'file_fee' => null,
            'total' => null,
            'items' => [],
        ];

        if (! $providerBranchId) {
            return $empty;
        }

        $branch = ProviderBranch::query()
            ->with(['services' => fn ($query) => $query->when(
                $serviceTypeId ?? $file->service_type_id,
                fn ($serviceQuery, $id) => $serviceQuery->where('service_types.id', $id),
            )])
            ->find($providerBranchId);

        if (! $branch) {
            return $empty;
        }

        return $this->suggestCostsForBranch($file, $branch, $serviceTypeId);
    }

    public function formatTeamCopyText(File $file, Gop $gop): string
    {
        return app(ClientOfferMessageFormatter::class)->formatOffer($file, $gop);
    }

    public function formatRequestCopyText(File $file, Gop $gop): string
    {
        return app(ClientOfferMessageFormatter::class)->formatRequest($file, $gop);
    }

    protected function clearAcceptedOffers(File $file): void
    {
        $file->gops()
            ->where('type', 'In')
            ->where('status', Gop::IN_STATUS_ACCEPTED)
            ->update(['status' => Gop::IN_STATUS_REJECTED]);
    }

    protected function assertInOffer(Gop $gop): void
    {
        if ($gop->type !== 'In') {
            throw new \InvalidArgumentException('GOP In offer actions only apply to type In.');
        }
    }

    public function resolveFileFeeAmount(File $file, int $serviceTypeId): ?float
    {
        $countryId = $file->country_id;
        $cityId = $file->city_id;

        if ($countryId && $cityId) {
            $fileFee = FileFee::query()
                ->where('service_type_id', $serviceTypeId)
                ->where('country_id', $countryId)
                ->where('city_id', $cityId)
                ->first();

            if ($fileFee) {
                return (float) $fileFee->amount;
            }
        }

        if ($countryId) {
            $fileFee = FileFee::query()
                ->where('service_type_id', $serviceTypeId)
                ->where('country_id', $countryId)
                ->whereNull('city_id')
                ->first();

            if ($fileFee) {
                return (float) $fileFee->amount;
            }
        }

        $fileFee = FileFee::query()
            ->where('service_type_id', $serviceTypeId)
            ->whereNull('country_id')
            ->whereNull('city_id')
            ->first();

        return $fileFee ? (float) $fileFee->amount : null;
    }
}
