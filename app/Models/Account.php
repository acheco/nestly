<?php

namespace App\Models;

use App\Enums\AccountType;
use App\Enums\AccountVisibility;
use App\Enums\TransactionType;
use App\Support\Money;
use Database\Factories\AccountFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $team_id
 * @property string $name
 * @property AccountType $type
 * @property numeric-string $initial_balance
 * @property numeric-string $current_balance
 * @property AccountVisibility $visibility
 * @property string|null $owner_id
 */
#[Fillable(['team_id', 'name', 'type', 'initial_balance', 'visibility', 'owner_id'])]
class Account extends Model
{
    /**
     * @use HasFactory<AccountFactory>
     */
    use HasFactory, HasUuids;

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (Account $account) {
            $account->current_balance = $account->initial_balance;
        });
    }

    protected function casts(): array
    {
        return [
            'type' => AccountType::class,
            'visibility' => AccountVisibility::class,
            'initial_balance' => 'decimal:2',
            'current_balance' => 'decimal:2',
            'archived' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * @return HasMany<Transaction, $this>
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    /**
     * Aplica el efecto de un movimiento sobre current_balance (solo en memoria).
     *
     * Precondición: la fila debe estar bloqueada con lockForUpdate()
     * dentro de una transacción de BD; si no, hay riesgo de lost update.
     * No persiste: quien llama es responsable de save().
     *
     * @param  numeric-string  $amount  Siempre positivo; el signo lo da $type.
     */
    public function applyTransaction(TransactionType $type, string $amount): static
    {
        $this->current_balance = bcadd($this->current_balance, $type->signedAmount($amount), Money::SCALE);

        return $this;
    }
}
