<?php

namespace App\Services;

use App\Models\File;
use App\Models\Gop;
use App\Models\ProviderBranch;

class AppointmentRequestMessageFormatter
{
    public function __construct(
        protected GopInOfferService $gopInOfferService,
    ) {}

    public function format(File $file, ProviderBranch $branch, ?float $distanceMinutes = null): string
    {
        $acceptedGopIn = $this->gopInOfferService->acceptedOfferForFile($file);
        $serviceLabel = $this->resolveServiceLabel($file, $acceptedGopIn);
        $intro = $this->buildIntro($serviceLabel);

        $address = $branch->address ?? 'N/A';
        $distanceText = $distanceMinutes !== null && $distanceMinutes < 999999
            ? round($distanceMinutes, 0) . ' mins by car'
            : 'N/A';
        $branchName = $branch->branch_name ?? 'N/A';
        $dateTime = $this->formatDateTime($file, $serviceLabel);

        [$cost, $requestedGop] = $this->resolveCostAndGop($file, $branch, $acceptedGopIn);

        $lines = [
            $intro,
            '',
            "Provider: {$branchName}",
            "Address: {$address}",
            "Distance: {$distanceText}",
            "Date & Time: {$dateTime}",
            "Cost: {$cost}",
            "Requested GOP: {$requestedGop}",
            '',
            'Please let us know if these details suits the patient in order to proceed with the booking or check for another appointment',
        ];

        return implode("\n", $lines);
    }

    public function resolveServiceLabel(File $file, ?Gop $acceptedGopIn = null): string
    {
        if ($acceptedGopIn) {
            $acceptedGopIn->loadMissing('serviceType');

            if (filled($acceptedGopIn->service_type_other)) {
                return trim($acceptedGopIn->service_type_other);
            }

            if ($acceptedGopIn->serviceType?->name) {
                return trim($acceptedGopIn->serviceType->name);
            }
        }

        $file->loadMissing('serviceType');

        return trim((string) ($file->serviceType?->name ?? 'medical service'));
    }

    public function buildIntro(string $serviceLabel): string
    {
        $normalized = strtolower($serviceLabel);

        $descriptor = match (true) {
            str_contains($normalized, 'house call') || str_contains($normalized, 'house visit') => 'house visit provider',
            str_contains($normalized, 'hospital') => 'hospital or medical center',
            str_contains($normalized, 'telemedicine') => 'telemedicine consultation',
            str_contains($normalized, 'dental') => 'dental clinic',
            $normalized === 'clinic visit' || $normalized === 'clinic' => 'clinic',
            default => $serviceLabel,
        };

        return "Here are the details of the nearest available {$descriptor}:";
    }

    /**
     * @return array{0: string, 1: string}
     */
    protected function resolveCostAndGop(File $file, ProviderBranch $branch, ?Gop $acceptedGopIn): array
    {
        if ($acceptedGopIn && $acceptedGopIn->offered_cost !== null) {
            $cost = number_format((float) $acceptedGopIn->offered_cost, 0) . '€';
            $requestedGop = number_format((float) $acceptedGopIn->amount, 0) . '€';

            return [$cost, $requestedGop];
        }

        return $this->calculateLegacyCostAndGop($file, $branch);
    }

    /**
     * @return array{0: string, 1: string}
     */
    protected function calculateLegacyCostAndGop(File $file, ProviderBranch $branch): array
    {
        $serviceTypeId = $file->service_type_id;
        $cost = 'N/A';
        $gop = 'N/A';

        if (! $serviceTypeId) {
            return [$cost, $gop];
        }

        $service = $branch->services->firstWhere('id', $serviceTypeId)
            ?? $branch->services()->where('service_types.id', $serviceTypeId)->first();

        if (! $service) {
            return [$cost, $gop];
        }

        $minCost = $service->pivot->min_cost;
        $maxCost = $service->pivot->max_cost;
        $fileFeeAmount = $this->gopInOfferService->resolveFileFeeAmount($file, $serviceTypeId);

        if ($serviceTypeId == 2 && $fileFeeAmount) {
            $formatted = number_format($fileFeeAmount, 0) . '€';

            return [$formatted, $formatted];
        }

        if ($serviceTypeId == 1 && ($minCost || $maxCost)) {
            $base = $minCost ?? $maxCost ?? 0;
            $rounded = $base < 200 ? 300 : ceil($base / 100) * 100;
            $formatted = number_format($rounded, 0) . '€';

            return [$formatted, $formatted];
        }

        if ($fileFeeAmount) {
            $max = $maxCost ?? $minCost ?? 0;
            $mult = ceil($max / 250);
            $fee = $fileFeeAmount * $mult;
            $cost = number_format($max, 0) . '€';
            $gop = number_format($max + $fee, 0) . '€';

            return [$cost, $gop];
        }

        if ($minCost) {
            $formatted = number_format($minCost, 0) . '€';

            return [$formatted, $formatted];
        }

        return [$cost, $gop];
    }

    protected function formatDateTime(File $file, string $serviceLabel): string
    {
        if (strcasecmp($serviceLabel, 'Hospital Visit') === 0) {
            return 'The patient will wait in the ER for assessment';
        }

        if (! $file->service_date) {
            return 'N/A';
        }

        $parts = [$file->service_date->format('d/m/Y')];

        if ($file->service_time) {
            $parts[] = \Carbon\Carbon::parse($file->service_time)->format('H:i');
        }

        return implode(' at ', $parts);
    }
}
