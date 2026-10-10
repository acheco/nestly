<?php

namespace App\DTOs\Transactions;

use App\Models\Transaction;

final readonly class TransferResultData
{
    public function __construct(
        public string $transfer_group_id,
        public Transaction $expense_transaction,
        public Transaction $income_transaction
    ) {}

}
