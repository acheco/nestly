<?php

namespace App\DTOs;

use Carbon\CarbonImmutable;

final readonly class CreateTransferData
{
    /**
     * @param  numeric-string  $amount
     */
    public function __construct(
        public string $from_account_id,
        public string $to_account_id,
        public string $amount,
        public ?string $description,
        public CarbonImmutable $date,
    ) {}

}
