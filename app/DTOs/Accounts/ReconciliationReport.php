<?php

namespace App\DTOs\Accounts;

final readonly class ReconciliationReport
{
    public function __construct(
        public array $drifts
    ) {}

    public function isClean(): bool
    {
        return $this->drifts === [];
    }
}
