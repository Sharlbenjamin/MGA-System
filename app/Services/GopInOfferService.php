<?php

namespace App\Services;

use App\Models\File;
use App\Models\FileFee;
use App\Models\Gop;
use App\Models\ProviderBranch;
use Illuminate\Support\Facades\DB;

class GopInOfferService
{
    public function suggestCostsForBranch(File $file, ProviderBranch $branch): array
    {
        $serviceTypeId = $file->service_type_id;
        $offeredCost = null;
        $fileFee = null;

        if ($serviceTypeId) {
            $service = $branch->services->firstWhere('id', $serviceTypeId)
                ?? $branch->services()->where('service_types.id', $serviceTypeId)->first();

            if ($service) {
                $minCost = $service->pivot->min_cost ?? null;
                $maxCost = $service->pivot->max_cost ?? null;
                $fileFeeAmount = $this->resolveFileFeeAmount($file, $serviceTypeId);

                if ($serviceTypeId == 2 && $fileFeeAmount) {
                    $offeredCost = (float) $fileFeeAmount;
                    $fileFee = 0.0;
                } elseif ($serviceTypeId == 1 && ($minCost || $maxCost)) {
                    $base = $minCost ?? $maxCost ?? 0;
                    $offeredCost = (float) ($base < 200 ? 300 : ceil($base / 100) * 100);
                    $fileFee = 0.0;
                } elseif ($fileFeeAmount) {
                    $max = (float) ($maxCost ?? $minCost ?? 0);
                    $mult = (int) ceil($max / 250);
                    $offeredCost = $max;
                    $fileFee = (float) ($fileFeeAmount * $mult);
                } elseif ($minCost) {
                    $offeredCost = (float) $minCost;
                    $fileFee = 0.0;
                }
            }
        }

        return [
            'offered_cost' => $offeredCost,
            'file_fee' => $fileFee,
            'total' => $offeredCost !== null
                ? round($offeredCost + ($fileFee ?? 0), 2)
                : null,
        ];
    }

    public function createOffer(
        File $file,
        int $providerBranchId,
        float $offeredCost,
        float $fileFee = 0,
        string $status = Gop::IN_STATUS_DRAFT,
        ?string $notes = null,
    ): Gop {
        return DB::transaction(function () use ($file, $providerBranchId, $offeredCost, $fileFee, $status, $notes) {
            if ($status === Gop::IN_STATUS_ACCEPTED) {
                $this->clearAcceptedOffers($file);
            }

            return $file->gops()->create([
                'type' => 'In',
                'provider_branch_id' => $providerBranchId,
                'offered_cost' => round($offeredCost, 2),
                'file_fee' => round($fileFee, 2),
                'amount' => round($offeredCost + $fileFee, 2),
                'status' => $status,
                'date' => now()->toDateString(),
                'notes' => $notes,
            ]);
        });
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
            ->with(['providerBranch.provider'])
            ->latest('id')
            ->first();
    }

    public function formatTeamCopyText(File $file, Gop $gop): string
    {
        $gop->loadMissing('providerBranch.provider');

        $provider = $gop->providerBranch?->branch_name ?? 'Unknown provider';
        $cost = number_format((float) $gop->offered_cost, 2);
        $fee = number_format((float) ($gop->file_fee ?? 0), 2);
        $total = number_format((float) $gop->amount, 2);

        $lines = [
            "File: {$file->mga_reference}",
            "Provider: {$provider}",
            "Cost: €{$cost}",
            "File fee: €{$fee}",
            "Total: €{$total}",
        ];

        if (filled($gop->notes)) {
            $lines[] = 'Notes: ' . $gop->notes;
        }

        return implode("\n", $lines);
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

    protected function resolveFileFeeAmount(File $file, int $serviceTypeId): ?float
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
