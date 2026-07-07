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
        $gopIn = $this->gopInOfferService->resolveGopInForAppointmentMessage($file, $branch);
        $serviceLabel = $this->resolveServiceLabel($file, $gopIn);
        $intro = $this->buildIntro($serviceLabel);

        $address = $branch->address ?? 'N/A';
        $distanceText = $distanceMinutes !== null && $distanceMinutes < 999999
            ? round($distanceMinutes, 0) . ' mins by car'
            : 'N/A';
        $branchName = $branch->branch_name ?? 'N/A';
        $dateTime = $this->formatDateTime($file, $serviceLabel);

        [$cost, $requestedGop] = $this->formatCostAndGopForMessage($file, $branch, $gopIn);

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
    protected function formatCostAndGopForMessage(File $file, ProviderBranch $branch, ?Gop $gopIn): array
    {
        [$cost, $total] = $this->gopInOfferService->resolveCostAndTotalForBranch($file, $branch, $gopIn);

        if ($cost === null || $cost <= 0) {
            return ['N/A', 'N/A'];
        }

        return [
            number_format($cost, 0) . '€',
            number_format($total ?? $cost, 0) . '€',
        ];
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
