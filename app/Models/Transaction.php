<?php

namespace App\Models;

use App\Enums\TransactionType;
use Database\Factories\TransactionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $team_id
 * @property string $account_id
 * @property string|null $category_id
 * @property string|null $created_by
 * @property TransactionType $type
 * @property numeric-string $amount
 * @property string $date
 * @property string|null $description
 * @property string|null $notes
 * @property string|null $transfer_group_id
 * @property string|null $recurring_id
 * @property string|null $pot_contribution_id
 */
#[Fillable(['team_id', 'account_id', 'category_id', 'created_by', 'type', 'amount', 'date', 'description', 'notes', 'transfer_group_id', 'recurring_id', 'pot_contribution_id'])]
class Transaction extends Model
{
    /** @use HasFactory<TransactionFactory> */
    use HasFactory, HasUuids;

    protected function casts(): array
    {
        return [
            'type' => TransactionType::class,
            'amount' => 'decimal:2',
            'date' => 'date',
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
     * @return BelongsTo<Account, $this>
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    /**
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return BelongsTo<RecurringExpense, $this>
     */
    public function recurring(): BelongsTo
    {
        return $this->belongsTo(RecurringExpense::class, 'recurring_id');
    }

    public function isTransfer(): bool
    {
        return $this->transfer_group_id !== null;
    }
}
