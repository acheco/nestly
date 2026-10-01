<?php

// database/factories/BudgetFactory.php

namespace Database\Factories;

use App\Enums\BudgetPeriod;
use App\Enums\BudgetScope;
use App\Models\Budget;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Budget>
 */
class BudgetFactory extends Factory
{
    public function definition(): array
    {
        return [
            'limit_amount' => 5000.00,
            'period' => BudgetPeriod::Monthly,
            'start_date' => now()->startOfMonth(),
            'end_date' => now()->endOfMonth(),
            'rollover' => false,
            'scope' => BudgetScope::Shared,
        ];
    }
}
