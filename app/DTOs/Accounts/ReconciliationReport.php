<?php

namespace App\DTOs\Accounts;

final readonly class ReconciliationReport
{
    /**
     * @param  list<BalanceDrift>  $drifts
     * @param  list<BrokenTransferGroup>  $brokenGroups
     * @param  list<string>  $orphanPrivateTransactions
     */
    public function __construct(
        public array $drifts,
        public array $brokenGroups,
        public array $orphanPrivateTransactions
    ) {}

    public function isClean(): bool
    {
        return $this->drifts === [] && $this->brokenGroups === [] && $this->orphanPrivateTransactions === [];
    }
}
