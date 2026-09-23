<?php

// database/factories/RecurringExpenseFactory.php

namespace Database\Factories;

use App\Enums\RecurringFrequency;
use Illuminate\Database\Eloquent\Factories\Factory;

class RecurringExpenseFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => 'Netflix',
            'amount' => 219.00,
            'frequency' => RecurringFrequency::Monthly,
            'interval' => 1,
            'next_run_date' => now()->addMonth()->startOfMonth(),
            'notify_days_before' => 3,
            'active' => true,
            'auto_create_transaction' => true,
        ];
    }
}
