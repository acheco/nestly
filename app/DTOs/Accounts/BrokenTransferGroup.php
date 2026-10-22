<?php

namespace App\DTOs\Accounts;

use App\Enums\BrokenTransferReason;

final readonly class BrokenTransferGroup
{
    /**
     * @param  list<BrokenTransferReason>  $reasons
     */
    public function __construct(
        public string $transfer_group_id,
        public array $reasons
    ) {}
}
