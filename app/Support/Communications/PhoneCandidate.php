<?php

namespace App\Support\Communications;

readonly class PhoneCandidate
{
    public function __construct(
        public string $label,
        public string $raw,
        public string $normalized,
        public string $source,
    ) {}
}
