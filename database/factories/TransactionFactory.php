<?php

// database/factories/TransactionFactory.php

namespace Database\Factories;

use App\Enums\TransactionType;
use App\Models\Transaction;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Transaction>
 */
class TransactionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'type' => TransactionType::Expense,
            'amount' => fake()->randomFloat(2, 5, 1500),
            'date' => fake()->dateTimeBetween('-3 months', 'now'),
            'description' => fake()->randomElement([
                'Supermercado', 'Gasolina', 'Cena fuera', 'Suscripción', 'Farmacia', 'Ropa',
            ]),
        ];
    }

    public function income(): static
    {
        return $this->state(fn () => [
            'type' => TransactionType::Income,
            'amount' => fake()->randomFloat(2, 8000, 25000),
            'description' => 'Depósito de nómina',
        ]);
    }
}
