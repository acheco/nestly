<?php

namespace Database\Seeders;

use App\Actions\Transactions\CreateTransactionAction;
use App\DTOs\CreateTransactionData;
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
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DemoSeeder extends Seeder
{
    public function __construct(private readonly CreateTransactionAction $createTransaction) {}

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

        // El team personal lo crea UserFactory (afterCreating) y TeamObserver
        // siembra sus categorías por defecto.
        $team = $ana->currentTeam;
        $team->update(['currency' => 'DOP']);

        $checking = Account::factory()->create([
            'team_id' => $team->id,
            'name' => 'BHD Nómina',
            'initial_balance' => '8000.00',
        ]);

        $categorias = $team->categories()->get()->keyBy('name');

        // 3 meses de nómina — monto fijo para que el saldo sea verificable.
        for ($i = 2; $i >= 0; $i--) {
            $this->recordTransaction($team, $ana, [
                'account_id' => $checking->id,
                'category_id' => $categorias['Salario']->id,
                'type' => TransactionType::Income,
                'amount' => '45000.00',
                'date' => now()->subMonths($i)->startOfMonth()->addDays(4),
                'description' => 'Nómina',
            ]);
        }

        // Gastos de "Comida" que disparan el warning de 80% del budget
        // (límite 3000 → gastamos 2620).
        foreach (['850.00', '720.00', '640.00', '410.00'] as $index => $amount) {
            $this->recordTransaction($team, $ana, [
                'account_id' => $checking->id,
                'category_id' => $categorias['Comida']->id,
                'type' => TransactionType::Expense,
                'amount' => $amount,
                'date' => now()->startOfMonth()->addDays(2 + $index * 4),
                'description' => 'Supermercado',
            ]);
        }

        // Saldo esperado de "BHD Nómina": 8000 + 3 × 45000 − 2620 = 140380.00

        $team->budgets()->create([
            'category_id' => $categorias['Comida']->id,
            'limit_amount' => '3000.00',
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

        // Team creado explícitamente → nace is_personal=false (ERD §4.3.11).
        $team = Team::factory()->create([
            'name' => 'Familia Ramírez',
            'is_personal' => false,
            'currency' => 'DOP',
        ]);

        Membership::create(['team_id' => $team->id, 'user_id' => $luis->id, 'role' => TeamRole::Owner->value]);
        Membership::create(['team_id' => $team->id, 'user_id' => $mariana->id, 'role' => TeamRole::Admin->value]);

        $luis->update(['current_team_id' => $team->id]);
        $mariana->update(['current_team_id' => $team->id]);

        $cuentaConjunta = Account::factory()->create([
            'team_id' => $team->id,
            'name' => 'Cuenta Conjunta',
            'initial_balance' => '15000.00',
        ]);

        $ahorroLuis = Account::factory()->private()->create([
            'team_id' => $team->id,
            'owner_id' => $luis->id,
            'name' => 'Ahorro Luis',
            'initial_balance' => '5000.00',
        ]);

        $categorias = $team->categories()->get()->keyBy('name');

        // Nóminas de ambos, 3 meses.
        $salarios = [$luis->id => '52000.00', $mariana->id => '48000.00'];

        foreach ([$luis, $mariana] as $miembro) {
            for ($i = 2; $i >= 0; $i--) {
                $this->recordTransaction($team, $miembro, [
                    'account_id' => $cuentaConjunta->id,
                    'category_id' => $categorias['Salario']->id,
                    'type' => TransactionType::Income,
                    'amount' => $salarios[$miembro->id],
                    'date' => now()->subMonths($i)->startOfMonth()->addDays(4),
                    'description' => 'Nómina',
                ]);
            }
        }

        // Gastos variados registrados por ambos miembros — para demostrar
        // la policy "member solo edita las suyas". Tipo fijado a Expense
        // para que siempre coincida con categorías de gasto.
        $categoriasGasto = $categorias->filter(
            fn (Category $c) => $c->kind->value === TransactionType::Expense->value
        );

        foreach (range(1, 12) as $_) {
            $categoria = $categoriasGasto->random();

            $this->recordTransaction($team, fake()->randomElement([$luis, $mariana]), [
                'account_id' => $cuentaConjunta->id,
                'category_id' => $categoria->id,
                'type' => TransactionType::Expense,
                'amount' => fake()->numberBetween(150, 2500).'.00',
                'date' => now()->subDays(fake()->numberBetween(0, 60)),
                'description' => $categoria->name,
            ]);
        }

        // Transferencia: Cuenta Conjunta → Ahorro Luis (2 filas, mismo
        // transfer_group_id, ver ERD §4.3.3).
        // TODO: reemplazar por CreateTransferAction — hoy son dos
        // DB::transaction() separadas, no una operación atómica.
        $transferGroupId = (string) Str::uuid();

        $this->recordTransaction($team, $luis, [
            'account_id' => $cuentaConjunta->id,
            'type' => TransactionType::Expense,
            'amount' => '1000.00',
            'date' => now()->subDays(10),
            'description' => 'Transferencia a ahorro',
            'transfer_group_id' => $transferGroupId,
        ]);

        $this->recordTransaction($team, $luis, [
            'account_id' => $ahorroLuis->id,
            'type' => TransactionType::Income,
            'amount' => '1000.00',
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
            'name' => 'Netflix',
            'amount' => '549.00',
        ]);

        for ($i = 3; $i >= 1; $i--) {
            $this->recordTransaction($team, $luis, [
                'account_id' => $cuentaConjunta->id,
                'category_id' => $categorias['Entretenimiento']->id,
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
            'limit_amount' => '8000.00',
            'period' => 'monthly',
            'start_date' => now()->startOfMonth(),
            'end_date' => now()->endOfMonth(),
            'scope' => BudgetScope::Shared,
        ]);
    }

    /**
     * Registra la transacción con CreateTransactionAction para que el
     * current_balance de la cuenta quede consistente con los movimientos.
     *
     * @param  array{
     *     account_id: string,
     *     category_id?: string|null,
     *     type: TransactionType,
     *     amount: string,
     *     date: \DateTimeInterface,
     *     description?: string|null,
     *     notes?: string|null,
     *     transfer_group_id?: string|null,
     *     recurring_id?: string|null,
     * }  $attributes
     */
    private function recordTransaction(Team $team, User $user, array $attributes): Transaction
    {
        return $this->createTransaction->execute($team, $user, new CreateTransactionData(
            account_id: $attributes['account_id'],
            category_id: $attributes['category_id'] ?? null,
            type: $attributes['type'],
            // Normaliza a 2 decimales sin pasar nunca por float.
            amount: bcadd($attributes['amount'], '0', 2),
            date: CarbonImmutable::instance($attributes['date']),
            description: $attributes['description'] ?? null,
            notes: $attributes['notes'] ?? null,
            transfer_group_id: $attributes['transfer_group_id'] ?? null,
            recurring_id: $attributes['recurring_id'] ?? null,
        ));
    }
}
