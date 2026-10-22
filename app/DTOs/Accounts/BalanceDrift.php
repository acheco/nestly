<?php

namespace App\DTOs\Accounts;

final readonly class BalanceDrift
{
    /**
     * @param  numeric-string  $expected
     * @param  numeric-string  $actual
     */
    public function __construct(
        public string $account_id,
        public string $expected,
        public string $actual) {}
}
