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
    ) {}

    public function isFixedTerm(): bool
    {
        return $this->contractType === self::TYPE_FIXED;
    }
}
