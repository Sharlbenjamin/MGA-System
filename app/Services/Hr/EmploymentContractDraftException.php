<?php

namespace App\Services\Hr;

use Exception;

class EmploymentContractDraftException extends Exception
{
    /**
     * @param  list<string>  $missingFields
     */
    public function __construct(
        string $message,
        public readonly array $missingFields,
    ) {
        parent::__construct($message);
    }
}
