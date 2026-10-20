<?php

namespace App\DTOs\Transactions;

use Carbon\CarbonImmutable;

final readonly class UpdateTransferData
{
    /**
     * @param  numeric-string  $amount
     */
    public function __construct(
        public string $from_account_id,
        public string $to_account_id,
        public string $amount,
        public ?string $category_id,
        public ?string $description,
        public CarbonImmutable $date,
    ) {}

}
