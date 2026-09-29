<?php

namespace App\Data\Hr;

use Carbon\Carbon;

final class EmploymentContractExportChoices
{
    public const TYPE_FIXED = 'fixed';

    public const TYPE_INDEFINITE = 'indefinite';

    public function __construct(
        public string $contractType,
        public ?int $fixedTermMonths,
        public ?string $fixedTermReason,
        public ?Carbon $startDateOverride,
        public string $employerLegalName,
        public string $employerAddress,
        public ?string $employerRegistration,
        public string $signatoryName,
        public string $signatoryTitle,
    ) {}

    public function isFixedTerm(): bool
    {
        return $this->contractType === self::TYPE_FIXED;
    }

    public static function defaultEmployerLegalName(): string
    {
        return (string) config('hr.employer.legal_name');
    }

    public static function defaultEmployerAddress(): string
    {
        return (string) (config('hr.employer.address') ?? '');
    }

    public static function defaultEmployerRegistration(): ?string
    {
        $value = config('hr.employer.registration');

        return filled($value) ? (string) $value : null;
    }

    public static function defaultSignatoryName(): string
    {
        return (string) config('hr.employer.signatory_name');
    }

    public static function defaultSignatoryTitle(): string
    {
        return (string) config('hr.employer.signatory_title');
    }
}
