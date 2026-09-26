<?php

namespace Database\Seeders;

use App\Enums\BudgetScope;
use App\Enums\TeamRole;
use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\Category;
use App\Models\Membership;
use App\Models\RecurringExpense;
use App\Models\Team;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedAna();
        $this->seedFamiliaRamirez();

        $this->command?->info('Demo sembrada. Password de todos los usuarios: password');
    }

    private function seedAna(): void
    {
        $ana = User::factory()->create([
            'name' => 'Ana López',
            'email' => 'ana@nestly.test',
            'password' => Hash::make('password'),
        ]);

        // El team personal ya se crea automáticamente al registrar el
        // usuario (comportamiento del starter kit) — lo tomamos tal cual.
        $team = $ana->currentTeam;
        $team->update(['currency' => 'DOP']);

        $checking = Account::factory()->create([
            'team_id' => $team->id,
            'name' => 'BHD Nómina',
            'initial_balance' => 8000,
        ]);

        $comida = Category::where('team_id', $team->id)->where('name', 'Comida')->first();
        $salario = Category::where('team_id', $team->id)->where('name', 'Salario')->first();

        // 3 meses de nómina
        for ($i = 2; $i >= 0; $i--) {
            Transaction::factory()->income()->create([
                'team_id' => $team->id,
                'account_id' => $checking->id,
                'category_id' => $salario->id,
                'created_by' => $ana->id,
                'date' => now()->subMonths($i)->startOfMonth()->addDays(4),
            ]);
        }

        // Gastos de "Comida" suficientes para disparar el warning de 80%
        // del budget que creamos abajo (límite 3000 → gastamos ~2600).
        foreach ([850, 720, 640, 410] as $amount) {
            Transaction::factory()->create([
                'team_id' => $team->id,
                'account_id' => $checking->id,
                'category_id' => $comida->id,
                'created_by' => $ana->id,
                'amount' => $amount,
                'date' => now()->startOfMonth()->addDays(fake()->numberBetween(1, 20)),
            ]);
        }

        $team->budgets()->create([
            'category_id' => $comida->id,
            'limit_amount' => 3000,
            'period' => 'monthly',
            'start_date' => now()->startOfMonth(),
            'end_date' => now()->endOfMonth(),
            'scope' => BudgetScope::Personal,
            'owner_id' => $ana->id,
        ]);
    }

    private function seedFamiliaRamirez(): void
    {
        $luis = User::factory()->create([
            'name' => 'Luis Ramírez',
            'email' => 'luis@nestly.test',
            'password' => Hash::make('password'),
        ]);

        $mariana = User::factory()->create([
            'name' => 'Mariana Ramírez',
            'email' => 'mariana@nestly.test',
            'password' => Hash::make('password'),
        ]);

        // El team familiar se crea explícitamente (no es el personal
        // autogenerado de nadie) → nace is_personal=false.
        $team = Team::factory()->create([
            'name' => 'Familia Ramírez',
            'is_personal' => false,
            'currency' => 'DOP',
        ]);

        // Membership directo (no ->attach(), ver nota sobre HasUuids arriba).
        Membership::create(['team_id' => $team->id, 'user_id' => $luis->id, 'role' => TeamRole::Owner->value]);
        Membership::create(['team_id' => $team->id, 'user_id' => $mariana->id, 'role' => TeamRole::Admin->value]);

        $luis->update(['current_team_id' => $team->id]);
        $mariana->update(['current_team_id' => $team->id]);

        $cuentaConjunta = Account::factory()->create([
            'team_id' => $team->id,
            'name' => 'Cuenta Conjunta',
            'initial_balance' => 15000,
        ]);

        $ahorroLuis = Account::factory()->private()->create([
            'team_id' => $team->id,
            'owner_id' => $luis->id,
            'name' => 'Ahorro Luis',
            'initial_balance' => 5000,
        ]);

        $categorias = Category::where('team_id', $team->id)->get()->keyBy('name');

        // Nóminas de ambos, 3 meses
        foreach ([$luis, $mariana] as $miembro) {
            for ($i = 2; $i >= 0; $i--) {
                Transaction::factory()->income()->create([
                    'team_id' => $team->id,
                    'account_id' => $cuentaConjunta->id,
                    'category_id' => $categorias['Salario']->id,
                    'created_by' => $miembro->id,
                    'date' => now()->subMonths($i)->startOfMonth()->addDays(4),
                ]);
            }
        }

        // Gastos variados, registrados por ambos miembros — para
        // demostrar la policy "member solo edita las suyas".
        $categoriasGasto = $categorias->filter(fn (Category $c) => $c->kind->value === 'expense');
        foreach (range(1, 12) as $_) {
            Transaction::factory()->create([
                'team_id' => $team->id,
                'account_id' => $cuentaConjunta->id,
                'category_id' => $categoriasGasto->random()->id,
                'created_by' => fake()->randomElement([$luis->id, $mariana->id]),
            ]);
        }

        // Transferencia: Cuenta Conjunta → Ahorro Luis (2 filas, mismo
        // transfer_group_id, ver ERD §4.3.3).
        $transferGroupId = (string) Str::uuid();
        Transaction::create([
            'team_id' => $team->id,
            'account_id' => $cuentaConjunta->id,
            'created_by' => $luis->id,
            'type' => TransactionType::Expense,
            'amount' => 1000,
            'date' => now()->subDays(10),
            'description' => 'Transferencia a ahorro',
            'transfer_group_id' => $transferGroupId,
        ]);
        Transaction::create([
            'team_id' => $team->id,
            'account_id' => $ahorroLuis->id,
            'created_by' => $luis->id,
            'type' => TransactionType::Income,
            'amount' => 1000,
            'date' => now()->subDays(10),
            'description' => 'Transferencia desde cuenta conjunta',
            'transfer_group_id' => $transferGroupId,
        ]);

        // Netflix: recurrente con 3 ejecuciones históricas ya generadas.
        $netflix = RecurringExpense::factory()->create([
            'team_id' => $team->id,
            'account_id' => $cuentaConjunta->id,
            'category_id' => $categorias['Entretenimiento']->id,
            'created_by' => $luis->id,
        ]);

        for ($i = 3; $i >= 1; $i--) {
            Transaction::create([
                'team_id' => $team->id,
                'account_id' => $cuentaConjunta->id,
                'category_id' => $categorias['Entretenimiento']->id,
                'created_by' => $luis->id,
                'recurring_id' => $netflix->id,
                'type' => TransactionType::Expense,
                'amount' => $netflix->amount,
                'date' => now()->subMonths($i)->startOfMonth()->addDays(3),
                'description' => 'Netflix',
            ]);
        }

        // Budget compartido de Vivienda.
        $team->budgets()->create([
            'category_id' => $categorias['Vivienda']->id,
            'limit_amount' => 8000,
            'period' => 'monthly',
            'start_date' => now()->startOfMonth(),
            'end_date' => now()->endOfMonth(),
            'scope' => BudgetScope::Shared,
        ]);
    }
}
