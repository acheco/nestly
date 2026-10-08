<?php

namespace Database\Factories;

use App\Enums\AccountType;
use App\Enums\AccountVisibility;
use App\Models\Account;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Account>
 */
class AccountFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->randomElement(['Cuenta Nómina', 'Ahorro', 'Efectivo']),
            'type' => AccountType::Checking,
            'initial_balance' => fake()->randomFloat(2, 500, 20000),
            'visibility' => AccountVisibility::Team,
            'archived' => false,
        ];
    }

    public function private(): static
    {
        return $this->state(fn () => ['visibility' => AccountVisibility::Private]);
    }

    public function archived(): static
    {
        return $this->state(fn () => ['archived' => true]);
    }
}
