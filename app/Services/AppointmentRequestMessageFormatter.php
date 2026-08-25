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

        $kind = app(\App\Services\OfferPricingCalculator::class)->classifyService(
            $gopIn?->service_type_id ? (int) $gopIn->service_type_id : $file->service_type_id,
            $serviceLabel,
        );

        $lines = [
            $intro,
            '',
            "Provider: {$branchName}",
            "Address: {$address}",
            "Distance: {$distanceText}",
            "Date & Time: {$dateTime}",
<<<<<<< HEAD
            "Cost: {$cost}",
            "Requested GOP: {$requestedGop}",
        ];

        $requestComment = $this->resolveRequestComment($branch);
        if ($requestComment !== null) {
            $lines[] = '';
            $lines[] = $requestComment;
=======
        ];

        if ($kind === \App\Services\OfferPricingCalculator::SERVICE_HOUSE_VISIT) {
            [$merged] = $this->formatCostAndGopForMessage($file, $branch, $gopIn);
            $lines[] = ((string) config('offer.house_visit.merged_label', 'Cost & GOP')).": {$merged}";
        } else {
            [$cost, $requestedGop] = $this->formatCostAndGopForMessage($file, $branch, $gopIn);
            $lines[] = "Cost: {$cost}";
            $lines[] = "Requested GOP: {$requestedGop}";
>>>>>>> staging
        }

        $lines[] = '';
        $lines[] = 'Please let us know if these details suits the patient in order to proceed with the booking or check for another appointment';

        return implode("\n", $lines);
    }

    /**
     * Branch request comment takes precedence over the parent provider's.
     */
    protected function resolveRequestComment(ProviderBranch $branch): ?string
    {
        if (filled($branch->request_comment)) {
            return trim((string) $branch->request_comment);
        }

        $branch->loadMissing('provider');

        if (filled($branch->provider?->request_comment)) {
            return trim((string) $branch->provider->request_comment);
        }

        return null;
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
        $serviceLabel = $this->resolveServiceLabel($file, $gopIn);
        $kind = app(\App\Services\OfferPricingCalculator::class)->classifyService(
            $gopIn?->service_type_id ? (int) $gopIn->service_type_id : $file->service_type_id,
            $serviceLabel,
        );

        if ($kind === \App\Services\OfferPricingCalculator::SERVICE_HOUSE_VISIT && $gopIn) {
            $selling = round((float) ($gopIn->offered_cost ?? $gopIn->amount ?? 0), 2);

            if ($selling <= 0) {
                [$cost, $total] = $this->gopInOfferService->resolveCostAndTotalForBranch($file, $branch, $gopIn);
                $selling = (float) ($total ?: $cost ?: 0);
            }

            if ($selling > 0) {
                $merged = number_format($selling, 0).'€';

                return [$merged, $merged];
            }
        }

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
