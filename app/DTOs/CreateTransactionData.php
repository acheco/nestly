<?php

namespace App\DTOs;

use App\Enums\TransactionType;
use Carbon\CarbonImmutable;

final readonly class CreateTransactionData
{
    public function __construct(
        public string $account_id,
        public ?string $category_id,
        public TransactionType $type,
        public string $amount,
        public CarbonImmutable $date,
        public ?string $description,
        public ?string $notes,
        public ?string $transfer_group_id,
        public ?string $recurring_id,
    ) {}
}
