<?php

namespace App\DTOs;

use App\Enums\TransactionType;
use Carbon\CarbonImmutable;

/**
 * Datos editables de una transacción normal (ingreso o gasto).
 *
 * No incluye transfer_group_id ni recurring_id: los pone el sistema y no se
 * editan. Las patas de una transferencia no se editan con este DTO (TRD §3.15.6).
 */
final readonly class UpdateTransactionData
{
    /**
     * @param  numeric-string  $amount
     */
    public function __construct(
        public string $account_id,
        public ?string $category_id,
        public TransactionType $type,
        public string $amount,
        public CarbonImmutable $date,
        public ?string $description,
        public ?string $notes,
    ) {}
}
